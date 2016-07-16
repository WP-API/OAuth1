<?php
/**
 * Page post type handlers
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_REST_OAuth1 {
	const TOKEN_KEY_LENGTH = 24;
	const TOKEN_SECRET_LENGTH = 48;
	const VERIFIER_LENGTH = 24;

	/**
	 * Authentication type
	 *
	 * (e.g. oauth1, oauth2, basic, etc)
	 * @var string
	 */
	protected $type = 'oauth1';

	/**
	 * Errors that occurred during authentication
	 * @var WP_Error|null|boolean True if succeeded, WP_Error if errored, null if not OAuth
	 */
	protected $auth_status = null;

	/**
	 * Should we attempt to run?
	 *
	 * Stops infinite recursion in certain circumstances.
	 * @var boolean
	 */
	protected $should_attempt = true;

	/**
	 * Parse the Authorization header into parameters
	 *
	 * @param string $header Authorization header value (not including "Authorization: " prefix)
	 * @return array|boolean Map of parameter values, false if not an OAuth header
	 */
	public function parse_header( $header ) {
		if ( substr( $header, 0, 6 ) !== 'OAuth ' ) {
			return false;
		}

		// From OAuth PHP library, used under MIT license
		$params = array();
		if ( preg_match_all( '/(oauth_[a-z_-]*)=(:?"([^"]*)"|([^,]*))/', $header, $matches ) ) {
			foreach ($matches[1] as $i => $h) {
				$params[$h] = urldecode( empty($matches[3][$i]) ? $matches[4][$i] : $matches[3][$i] );
			}
			if (isset($params['realm'])) {
				unset($params['realm']);
			}
		}
		return $params;

	}

	/**
	 * Get the authorization header
	 *
	 * On certain systems and configurations, the Authorization header will be
	 * stripped out by the server or PHP. Typically this is then used to
	 * generate `PHP_AUTH_USER`/`PHP_AUTH_PASS` but not passed on. We use
	 * `getallheaders` here to try and grab it out instead.
	 *
	 * @return string|null Authorization header if set, null otherwise
	 */
	public function get_authorization_header() {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();

			// Check for the authoization header case-insensitively
			foreach ( $headers as $key => $value ) {
				if ( strtolower( $key ) === 'authorization' ) {
					return $value;
				}
			}
		}

		return null;
	}

	public function get_parameters( $require_token = true, $extra = array() ) {
		$params = array_merge( $_GET, $_POST );
		$params = wp_unslash( $params );

		$header = $this->get_authorization_header();

		if ( ! empty( $header ) ) {
			// Trim leading spaces
			$header = trim( $header );

			$header_params = $this->parse_header( $header );
			if ( ! empty( $header_params ) ) {
				$params = array_merge( $params, $header_params );
			}
		}

		$param_names = array(
			'oauth_consumer_key',
			'oauth_timestamp',
			'oauth_nonce',
			'oauth_signature',
			'oauth_signature_method'
		);

		if ( $require_token ) {
			$param_names[] = 'oauth_token';
		}

		if ( ! empty( $extra ) ) {
			$param_names = array_merge( $param_names, (array) $extra );
		}

		$errors = array();
		$have_one = false;

		// check for required OAuth parameters
		foreach ( $param_names as $param_name ) {
			if ( empty( $params[ $param_name ] ) )
				$errors[] = $param_name;
			else
				$have_one = true;
		}

		// All keys are missing, so we're probably not even trying to use OAuth
		if ( ! $have_one ) {
			return null;
		}

		// If we have at least one supplied piece of data, and we have an error,
		// then it's a failed authentication
		if ( ! empty( $errors ) ) {
			$message = sprintf(
				_n(
					__( 'Missing OAuth parameter %s', 'rest_oauth1' ),
					__( 'Missing OAuth parameters %s', 'rest_oauth1' ),
					count( $errors )
				),
				implode(', ', $errors )
			);
			return new WP_Error( 'json_oauth1_missing_parameter', $message, array( 'status' => 401 ) );
		}

		return $params;
	}

	/**
	 * Check OAuth authentication
	 *
	 * This follows the spec for simple OAuth 1.0a authentication (RFC 5849) as
	 * closely as possible, with two exceptions.
	 *
	 * @link http://tools.ietf.org/html/rfc5849 OAuth 1.0a Specification
	 *
	 * @param WP_User|null Already authenticated user (will be passed through), or null to perform OAuth authentication
	 * @return WP_User|null|WP_Error Authenticated user on success, null if no OAuth data supplied, error otherwise
	 */
	public function authenticate( $user ) {
		if ( ! empty( $user ) || ! $this->should_attempt ) {
			return $user;
		}

		// Skip authentication for OAuth meta requests
		if ( get_query_var( 'json_oauth_route' ) ) {
			return null;
		}

		$params = $this->get_parameters();
		if ( ! is_array( $params ) ) {
			$this->auth_status = $params;
			return null;
		}

		// Fetch user by token key
		$token = $this->get_access_token( $params['oauth_token'] );
		if ( is_wp_error( $token ) ) {
			$this->auth_status = $token;
			return null;
		}

		$result = $this->check_token( $token, $params['oauth_consumer_key'] );
		if ( is_wp_error( $result ) ) {
			$this->auth_status = $result;
			return null;
		}
		list( $consumer, $user ) = $result;

		// Perform OAuth validation
		$error = $this->check_oauth_signature( $consumer, $params, $token );
		if ( is_wp_error( $error ) ) {
			$this->auth_status = $error;
			return null;
		}

		$error = $this->check_oauth_timestamp_and_nonce( $user, $params['oauth_timestamp'], $params['oauth_nonce'] );
		if ( is_wp_error( $error ) ) {
			$this->auth_status = $error;
			return null;
		}

		$this->auth_status = true;
		return $user->ID;
	}

	/**
	 * Report authentication errors to the JSON API
	 *
	 * @param WP_Error|mixed $result Error from another authentication handler, null if we should handle it, or another value if not
	 * @return WP_Error|boolean|null {@see WP_JSON_Server::check_authentication}
	 */
	public function get_authentication_errors( $value ) {
		if ( $value !== null ) {
			return $value;
		}

		return $this->auth_status;
	}

	/**
	 * Serve an OAuth request
	 *
	 * Either returns data to be served, or redirects and exits. Non-reentrant
	 * for the `authorize` route.
	 *
	 * @param string $route Type of request; `authorize`, `request` or `access`
	 * @return mixed Response data (typically WP_Error or an array). May exit.
	 */
	public function dispatch( $route ) {
		// if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
		// 	return new WP_Error( 'oauth1_invalid_method', __( 'Invalid request method for OAuth endpoint' ), array( 'status' => 405 ) );
		// }

		switch ( $route ) {
			case 'authorize':
				$url = site_url( 'wp-login.php?action=oauth1_authorize', 'login_post' );
				$url .= '&' . $_SERVER['QUERY_STRING'];
				wp_safe_redirect( $url );
				exit;

			case 'request':
				$params = $this->get_parameters( false );

				if ( is_wp_error( $params ) ) {
					return $params;
				}
				if ( empty( $params ) ) {
					return new WP_Error( 'json_oauth1_missing_parameter', __( 'No OAuth parameters supplied', 'rest_oauth1' ), array( 'status' => 400 ) );
				}

				return $this->generate_request_token( $params );

			case 'access':
				$params = $this->get_parameters( true, array( 'oauth_verifier' ) );

				if ( is_wp_error( $params ) ) {
					return $params;
				}
				if ( empty( $params ) ) {
					return new WP_Error( 'json_oauth1_missing_parameter', __( 'No OAuth parameters supplied', 'rest_oauth1' ), array( 'status' => 400 ) );
				}

				return $this->generate_access_token( $params );

			default:
				return new WP_Error( 'json_oauth1_invalid_route', __( 'Route is invalid', 'rest_oauth1' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Check a token against the database
	 *
	 * @param string $token Token object
	 * @param string $consumer_key Consumer ID
	 * @return array Array of consumer object, user object
	 */
	public function check_token( $token, $consumer_key ) {
		$this->should_attempt = false;
		$consumer = WP_REST_OAuth1_Client::get_by_key( $consumer_key );
		$this->should_attempt = true;

		if ( is_wp_error( $consumer ) ) {
			return $consumer;
		}

		if ( ! hash_equals( (string) $token['consumer'], (string) $consumer->ID ) ) {
			return new WP_Error( 'json_oauth1_consumer_mismatch', __( 'Token is not registered for the given consumer', 'rest_oauth1' ), array( 'status' => 401 ) );
		}

		return array( $consumer, new WP_User( $token['user'] ) );
	}

	/**
	 * Retrieve a request token's data
	 *
	 * @param string $key Token ID
	 * @return array|WP_Error Request token data on success, error otherwise
	 */
	public function get_request_token( $key ) {
		$data = get_option( 'oauth1_request_' . $key, null );

		if ( empty( $data ) ) {
			return new WP_Error( 'json_oauth1_invalid_token', __( 'Invalid token', 'rest_oauth1' ), array( 'status' => 400 ) );
		}

		// Check expiration
		if ( $data['expiration'] < time() ) {
			$this->remove_request_token( $key );
			return new WP_Error( 'json_oauth1_expired_token', __( 'OAuth request token has expired', 'rest_oauth1' ), array( 'status' => 401 ) );
		}

		return $data;
	}

	/**
	 * Generate a new request token
	 *
	 * @param array $params Request parameters, from {@see get_parameters}
	 * @return array|WP_Error Array of token data on success, error otherwise
	 */
	public function generate_request_token( $params ) {
		$consumer = WP_REST_OAuth1_Client::get_by_key( $params['oauth_consumer_key'] );
		if ( is_wp_error( $consumer ) ) {
			return $consumer;
		}

		// Check the OAuth request signature against the current request
		$result = $this->check_oauth_signature( $consumer, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$error = $this->check_oauth_timestamp_and_nonce( $consumer, $params['oauth_timestamp'], $params['oauth_nonce'] );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		// Generate token
		$key = apply_filters( 'json_oauth1_request_token_key', wp_generate_password( self::TOKEN_KEY_LENGTH, false ) );
		$data = array(
			'key'        => $key,
			'secret'     => wp_generate_password( self::TOKEN_SECRET_LENGTH, false ),
			'consumer'   => $consumer->ID,
			'authorized' => false,
			'expiration' => time() + 24 * HOUR_IN_SECONDS,
			'callback'   => null,
			'verifier'   => null,
			'user'       => null,
		);
		$data = apply_filters( 'json_oauth1_request_token_data', $data );
		add_option( 'oauth1_request_' . $key, $data, null, 'no' );
		if ( ! empty( $params['oauth_callback'] ) ) {
			$error = $this->set_request_token_callback( $key, $params['oauth_callback'] );
			if ( $error ) {
				return $error;
			}
		}

		$data = array(
			'oauth_token' => self::urlencode_rfc3986($key),
			'oauth_token_secret' => self::urlencode_rfc3986($data['secret']),
			'oauth_callback_confirmed' => 'true',
		);
		return $data;
	}

	public function set_request_token_callback( $key, $callback ) {
		$token = $this->get_request_token( $key );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$consumer = $token['consumer'];
		if ( ! $this->check_callback( $callback, $consumer ) ) {
			return new WP_Error( 'json_oauth1_invalid_callback', __( 'Callback URL is invalid', 'rest_oauth1' ) );
		}

		$token['callback'] = $callback;
		update_option( 'oauth1_request_' . $key, $token );
		return $token['verifier'];
	}

	/**
	 * Validate a callback URL.
	 *
	 * Based on {@see wp_http_validate_url}, but less restrictive around ports
	 * and hosts. In particular, it allows any scheme, host or port rather than
	 * just HTTP with standard ports.
	 *
	 * @param string $url URL for the callback.
	 * @return bool True for a valid callback URL, false otherwise.
	 */
	public static function validate_callback( $url ) {
		if ( strpos( $url, ':' ) === false ) {
			return false;
		}

		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url || empty( $parsed_url['host'] ) )
			return false;

		if ( isset( $parsed_url['user'] ) || isset( $parsed_url['pass'] ) )
			return false;

		if ( false !== strpbrk( $parsed_url['host'], ':#?[]' ) )
			return false;

		return true;
	}

	/**
	 * Check whether a callback is valid for a given consumer.
	 *
	 * @param string $url Supplied callback.
	 * @param int|WP_Post $consumer_id Consumer post ID or object.
	 * @return bool True if valid, false otherwise.
	 */
	public function check_callback( $url, $consumer_id ) {
		$consumer = get_post( $consumer_id );
		if ( empty( $consumer ) || $consumer->post_type !== 'json_consumer' || $consumer->type !== $this->type ) {
			return false;
		}

		$registered = $consumer->callback;
		if ( empty( $registered ) ) {
			return false;
		}

		// Out-of-band isn't a URL, but is still valid
		if ( $registered === 'oob' || $url === 'oob' ) {
			// Ensure both the registered URL and requested are 'oob'
			return ( $registered === $url );
		}

		// Validate the supplied URL
		if ( ! $this->validate_callback( $url ) ) {
			return false;
		}

		$registered = wp_parse_url( $registered );
		$supplied = wp_parse_url( $url );

		// Check all components except query and fragment
		$parts = array( 'scheme', 'host', 'port', 'user', 'pass', 'path' );
		$valid = true;
		foreach ( $parts as $part ) {
			if ( isset( $registered[ $part ] ) !== isset( $supplied[ $part ] ) ) {
				$valid = false;
				break;
			}

			if ( ! isset( $registered[ $part ] ) ) {
				continue;
			}

			if ( $registered[ $part ] !== $supplied[ $part ] ) {
				$valid = false;
				break;
			}
		}

		/**
		 * Filter whether a callback is counted as valid.
		 *
		 * By default, the URLs must match scheme, host, port, user, pass, and
		 * path. Query and fragment segments are allowed to be different.
		 *
		 * To change this behaviour, filter this value. Note that consumers must
		 * have a callback registered, even if you relax this restruction. It is
		 * highly recommended not to change this behaviour, as clients will
		 * expect the same behaviour across all WP sites.
		 *
		 * @param boolean $valid True if the callback URL is valid, false otherwise.
		 * @param string $url Supplied callback URL.
		 * @param WP_Post $consumer Consumer post; stored callback saved as `consumer` meta value.
		 */
		return apply_filters( 'rest_oauth.check_callback', $valid, $url, $consumer );
	}

	/**
	 * Authorize a request token
	 *
	 * Enables the request token to be used to generate an access token
	 * @param string $key Token ID
	 * @return string|WP_Error Verification code on success, error otherwise
	 */
	public function authorize_request_token( $key, $user = null ) {
		$token = $this->get_request_token( $key );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( empty( $user ) ) {
			$user = get_current_user_id();
		}
		elseif ( is_a( $user, 'WP_User' ) ) {
			$user = $user->ID;
		}

		if ( empty( $user ) ) {
			return new WP_Error( 'json_oauth1_invalid_user', __( 'Invalid user specified for access token', 'rest_oauth1' ) );
		}

		$token['authorized'] = true;
		$token['verifier'] = wp_generate_password( self::VERIFIER_LENGTH, false );
		$token['user'] = $user;
		$token = apply_filters( 'oauth_request_token_authorized_data', $token );
		update_option( 'oauth1_request_' . $key, $token );
		return $token['verifier'];
	}

	/**
	 * Delete a request token
	 *
	 * @param string $key Token ID
	 */
	public function remove_request_token( $key ) {
		delete_option( 'oauth1_request_' . $key );
	}

	/**
	 * Retrieve an access token's data
	 *
	 * @param string $oauth_token Token ID
	 * @return array|null Token data on success, null otherwise
	 */
	public function get_access_token( $oauth_token ) {
		$data = get_option( 'oauth1_access_' . $oauth_token, null );
		if ( empty( $data ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Generate a new access token
	 *
	 * @param string $oauth_consumer_key Consumer key
	 * @param string $oauth_token Request token key
	 * @return WP_Error|array OAuth token data on success, error otherwise
	 */
	public function generate_access_token( $params ) {
		$consumer = WP_REST_OAuth1_Client::get_by_key( $params['oauth_consumer_key'] );
		if ( is_wp_error( $consumer ) ) {
			return $consumer;
		}

		$token = $this->get_request_token( $params['oauth_token'] );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		// Check the OAuth request signature against the current request
		$result = $this->check_oauth_signature( $consumer, $params, $token );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$error = $this->check_oauth_timestamp_and_nonce( $consumer, $params['oauth_timestamp'], $params['oauth_nonce'] );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		// Check verification
		if ( $token['authorized'] !== true ) {
			return new WP_Error( 'json_oauth1_unauthorized_token', __( 'OAuth token has not been authorized', 'rest_oauth1' ), array( 'status' => 401 ) );
		}

		if ( ! hash_equals( (string) $params['oauth_verifier'], (string) $token['verifier'] ) ) {
			return new WP_Error( 'json_oauth1_invalid_verifier', __( 'OAuth verifier does not match', 'rest_oauth1' ), array( 'status' => 400 ) );
		}

		$this->should_attempt = false;
		$consumer = WP_REST_OAuth1_Client::get_by_key( $params['oauth_consumer_key'] );
		$this->should_attempt = true;

		if ( is_wp_error( $consumer ) ) {
			return $consumer;
		}

		// Issue access token
		$key = apply_filters( 'json_oauth1_access_token_key', wp_generate_password( self::TOKEN_KEY_LENGTH, false ) );
		$data = array(
			'key' => $key,
			'secret' => wp_generate_password( self::TOKEN_SECRET_LENGTH, false ),
			'consumer' => $consumer->ID,
			'user' => $token['user'],
		);
		$data = apply_filters( 'json_oauth1_access_token_data', $data );
		add_option( 'oauth1_access_' . $key, $data, null, 'no' );

		// Delete the request token
		$this->remove_request_token( $params['oauth_token'] );

		// Return the new token's data
		$data = array(
			'oauth_token' => self::urlencode_rfc3986( $key ),
			'oauth_token_secret' => self::urlencode_rfc3986( $data['secret'] ),
		);
		return $data;
	}

	/**
	 * Revoke an access token
	 *
	 * @param string $key Access token identifier
	 * @return WP_Error|boolean True on success, error otherwise
	 */
	public function revoke_access_token( $key ) {
		$data = $this->get_access_token( $key );
		if ( empty( $data ) ) {
			return new WP_Error( 'json_oauth1_invalid_token', __( 'Access token does not exist', 'rest_oauth1' ), array( 'status' => 401 ) );
		}

		delete_option( 'oauth1_access_' . $key );
		do_action( 'json_oauth1_revoke_token', $data, $key );

		return true;
	}

	/**
	 * Verify that the consumer-provided request signature matches our generated signature, this ensures the consumer
	 * has a valid key/secret
	 *
	 * @param WP_User $user
	 * @param array $params the request parameters
	 * @return boolean|WP_Error True on success, error otherwise
	 */
	public function check_oauth_signature( $consumer, $oauth_params, $token = null ) {

		$http_method = strtoupper( $_SERVER['REQUEST_METHOD'] );

		switch ( $http_method ) {
			case 'GET':
			case 'HEAD':
			case 'DELETE':
				$params = wp_unslash( $_GET );
				break;

			case 'POST':
			case 'PUT':
				$params = wp_unslash( $_POST );
				break;
			default:
				return new WP_Error( 'rest_oauth1_unknown_http_method',
					sprintf( __( 'Unknown http method: %s', 'rest_oauth1' ), $http_method ),
					array( 'status' => 401 ) );
		}

		$params = array_merge( $params, $oauth_params );

		$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$wp_base = get_home_url( null, '/', 'relative' );
		if ( substr( $request_path, 0, strlen( $wp_base ) ) === $wp_base ) {
			$request_path = substr( $request_path, strlen( $wp_base ) );
		}
		$base_request_uri = self::urlencode_rfc3986( get_home_url( null, $request_path ) );

		// get the signature provided by the consumer and remove it from the parameters prior to checking the signature
		$consumer_signature = rawurldecode( $params['oauth_signature'] );
		unset( $params['oauth_signature'] );

		// normalize parameter key/values
		array_walk_recursive( $params, array( $this, 'normalize_parameters' ) );

		// sort parameters
		if ( ! uksort( $params, 'strcmp' ) )
			return new WP_Error( 'json_oauth1_failed_parameter_sort', __( 'Invalid Signature - failed to sort parameters', 'rest_oauth1' ), array( 'status' => 401 ) );

		$query_string = $this->create_signature_string( $params );

		$token = (array) $token;
		$string_to_sign = $http_method . '&' . $base_request_uri . '&' . $query_string;
		$key_parts = array(
			$consumer->secret,
			( $token ? $token['secret'] : '' )
		);
		$key = implode( '&', $key_parts );

		switch ($params['oauth_signature_method']) {
			case 'HMAC-SHA1':
				$hash_algorithm = 'sha1';
				break;

			case 'HMAC-SHA256':
				$hash_algorithm = 'sha256';
				break;

			default:
				return new WP_Error( 'json_oauth1_invalid_signature_method', __( 'Signature method is invalid', 'rest_oauth1' ), array( 'status' => 401 ) );
		}

		$signature = base64_encode( hash_hmac( $hash_algorithm, $string_to_sign, $key, true ) );

		if ( ! hash_equals( $signature, $consumer_signature ) ) {
			return new WP_Error( 'json_oauth1_signature_mismatch', __( 'OAuth signature does not match', 'rest_oauth1' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Creates a signature string from all query parameters
	 *
	 * @since  0.1
	 * @param  array  $params Array of query parameters
	 * @return string         Signature string
	 */
	public function create_signature_string( $params ) {
		return implode( '%26', $this->join_with_equals_sign( $params ) ); // join with ampersand
	}

	/**
	 * Creates an array of urlencoded strings out of each array key/value pairs
	 *
	 * @since  0.1.0
	 * @param  array  $params       Array of parameters to convert.
	 * @param  array  $query_params Array to extend.
	 * @param  string $key          Optional Array key to append
	 * @return string               Array of urlencoded strings
	 */
	public function join_with_equals_sign( $params, $query_params = array(), $key = '' ) {
		foreach ( $params as $param_key => $param_value ) {
			if ( is_array( $param_value ) ) {
				$query_params = $this->join_with_equals_sign( $param_value, $query_params, $param_key );
			} else {
				if ( $key ) {
					$param_key = $key . '%5B' . $param_key . '%5D'; // Handle multi-dimensional array
				}
				$string = $param_key . '=' . $param_value; // join with equals sign
				$query_params[] = self::urlencode_rfc3986($string );
			}
		}
		return $query_params;
	}

	/**
	 * Normalize each parameter by assuming each parameter may have already been encoded, so attempt to decode, and then
	 * re-encode according to RFC 3986
	 *
	 * @since 2.1
	 * @see rawurlencode()
	 * @param string $key
	 * @param string $value
	 */
	protected function normalize_parameters( &$key, &$value ) {
		$key = self::urlencode_rfc3986( rawurldecode( $key ) );
		$value = self::urlencode_rfc3986( rawurldecode( $value ) );
	}

	/**
	 * Verify that the timestamp and nonce provided with the request are valid
	 *
	 * This prevents replay attacks against the request. A timestamp is only
	 * valid within 15 minutes of the current time, and a nonce is valid if it
	 * has not been used within the last 15 minutes.
	 *
	 * @param WP_User $consumer
	 * @param int $timestamp the unix timestamp for when the request was made
	 * @param string $nonce a unique (for the given user) 32 alphanumeric string, consumer-generated
	 * @return boolean|WP_Error True on success, error otherwise
	 */
	public function check_oauth_timestamp_and_nonce( $consumer, $timestamp, $nonce ) {
		$valid_window = apply_filters( 'json_oauth1_timestamp_window', 15 * MINUTE_IN_SECONDS );

		if ( ( $timestamp < time() - $valid_window ) ||  ( $timestamp > time() + $valid_window ) )
			return new WP_Error( 'json_oauth1_invalid_timestamp', __( 'Invalid timestamp', 'rest_oauth1' ), array( 'status' => 401 ) );

		$used_nonces = $consumer->nonces;

		if ( empty( $used_nonces ) )
			$used_nonces = array();

		if ( in_array( $nonce, $used_nonces ) )
			return new WP_Error( 'json_oauth1_nonce_already_used', __( 'Invalid nonce - nonce has already been used', 'rest_oauth1' ), array( 'status' => 401 ) );

		$used_nonces[ $timestamp ] = $nonce;
		
		// Get the current time
		$current_time = time();
		
		// Remove expired nonces
		foreach ( $used_nonces as $nonce_timestamp => $nonce ) {
			
			// If the nonce timestamp is expired
			if ( $nonce_timestamp < $current_time - $valid_window )
				unset( $used_nonces[ $nonce_timestamp ] );
		}

		update_user_meta( $consumer->ID, 'nonces', $used_nonces );

		return true;
	}

	protected static function urlencode_rfc3986( $value ) {
		return str_replace( array( '+', '%7E' ), array( ' ', '~' ), rawurlencode( $value ) );
	}
}
