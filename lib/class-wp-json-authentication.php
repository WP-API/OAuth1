<?php

/**
 * Helper class for authentication handlers
 *
 * Includes common authentication tasks, including managing consumers
 *
 * @package WordPress
 * @subpackage JSON API
 */
abstract class WP_JSON_Authentication {
	/**
	 * Authentication type
	 *
	 * (e.g. oauth1, oauth2, basic, etc)
	 * @var string
	 */
	protected $type = '';

	protected $consumers = array();

	public function __construct() {
		if ( empty( $this->type ) ) {
			_doing_it_wrong( 'WP_JSON_Authentication::__construct', __( 'The type of authentication must be set' ), 'WPAPI-0.9' );
			return;
		}

		add_filter( 'json_check_authentication', array( $this, 'authenticate' ), 0 );
		add_filter( 'rest_authentication_errors', array( $this, 'get_authentication_errors' ), 0 );
	}

	abstract public function authenticate( $user );

	abstract public function get_authentication_errors( $value );

	public function get_consumer( $key ) {
		$this->should_attempt = false;

		$query = new WP_Query();
		$consumers = $query->query( array(
			'post_type' => 'json_consumer',
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key' => 'key',
					'value' => $key,
				),
				array(
					'key' => 'type',
					'value' => $this->type,
				),
			),
		) );

		$this->should_attempt = true;

		if ( empty( $consumers ) || empty( $consumers[0] ) )
			return new WP_Error( 'json_consumer_notfound', __( 'Consumer Key is invalid' ), array( 'status' => 401 ) );

		return $consumers[0];
	}

	public function add_consumer( $params ) {
		$default = array(
			'name' => '',
			'description' => '',
			'meta' => array(),
		);
		$params = wp_parse_args( $params, $default );

		$data = array(
			'post_type' => 'json_consumer',
			'post_title' => $params['name'],
			'post_content' => $params['description'],
		);

		$ID = wp_insert_post( $data );
		if ( is_wp_error( $ID ) ) {
			return $ID;
		}

		$meta = $params['meta'];
		$meta['type'] = $this->type;
		$meta = apply_filters( 'json_consumer_meta', $meta, $ID, $params );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $ID, $key, $value );
		}

		return get_post( $ID );
	}
}
