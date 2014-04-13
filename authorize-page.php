<?php
/**
 * Authorization page handler
 *
 * Takes care of UI and related elements for the authorization step of OAuth.
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_JSON_Authentication_Authorize {
	/**
	 * Request token for the current authorization request
	 *
	 * @var array
	 */
	protected $token;

	/**
	 * Consumer post object for the current authorization request
	 *
	 * @var WP_Post
	 */
	protected $consumer;

	/**
	 * Register required actions and filters
	 */
	public function register_hooks() {
		add_action( 'login_form_oauth1_authorize', array( $this, 'render_page' ) );
		add_action( 'oauth1_authorize_form', array( $this, 'page_fields' ) );
	}

	/**
	 * Render authorization page
	 *
	 * Callback for login form hook. Must exit.
	 */
	public function render_page() {
		// Check required fields
		if ( empty( $_REQUEST['oauth_token'] ) ) {
			$error = new WP_Error( 'json_oauth1_missing_param', sprintf( __( 'Missing parameter %s' ), 'oauth_token' ), array( 'status' => 400 ) );
			$this->display_error( $error );
			exit;
		}

		// Set up fields
		$token = $_REQUEST['oauth_token'];
		$scope = '*';
		if ( ! empty( $_REQUEST['wp_scope'] ) ) {
			$scope = $_REQUEST['wp_scope'];
		}

		$authenticator = new WP_JSON_Authentication_OAuth1();
		$errors = array();
		$token = $authenticator->get_request_token( $_REQUEST['oauth_token'] );
		if ( is_wp_error( $token ) ) {
			$this->display_error( $token );
			exit;
		}

		// Fetch consumer
		$consumer = get_post( $token['consumer'] );

		if ( ! empty( $_POST['wp-submit'] ) ) {
			check_admin_referer( 'json_oauth1_authorize' );

			$authenticator->authorize_request_token( $_REQUEST['oauth_token'] );
			exit;
		}

		$file = locate_template( 'oauth1-authorize.php' );
		if ( empty( $file ) ) {
			$file = dirname( __FILE__ ) . '/theme/oauth1-authorize.php';
		}

		include $file;

		exit;
	}

	/**
	 * Output required hidden fields
	 *
	 * Outputs the required hidden fields for the authorization page, including
	 * nonce field.
	 */
	public function page_fields() {
		echo '<input type="hidden" name="consumer" value="' . absint( $consumer->ID ) . '" />';
		wp_nonce_field( 'json_oauth1_authorize' );
	}

	/**
	 * Display an error using login page wrapper
	 *
	 * @param WP_Error $error Error object
	 */
	public function display_error( WP_Error $error ) {
		login_header( __( 'Error' ), '', $error );
?>

<?php
		login_footer();
	}
}
