<?php

/**
 * Class that extends WP_REST_Client that implements OAuth1.
 *
 * @see   WP_REST_Client
 */
class WP_REST_OAuth1_Client extends WP_REST_Client {
	/**
	 * Consumer key length.
	 *
	 * @var int
	 */
	const CONSUMER_KEY_LENGTH = 12;
	/**
	 * Consumer secret length.
	 *
	 * @var int
	 */
	const CONSUMER_SECRET_LENGTH = 48;

	/**
	 * Regenerate the secret for the client.
	 *
	 * @return bool|WP_Error True on success, error otherwise.
	 */
	public function regenerate_secret() {
		$params = array(
			'meta' => array(
				'secret' => wp_generate_password( self::CONSUMER_SECRET_LENGTH, false ),
			),
		);

		return $this->update( $params );
	}

	/**
	 * Get the client type.
	 *
	 * @return string
	 */
	protected static function get_type() {
		return 'oauth1';
	}

	/**
	 * Delete a client.
	 *
	 * @since 0.4.0
	 *
	 * @return bool True if delete, false otherwise.
	 */
	public function delete() {
		global $wpdb;
		$results       = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'oauth1_access_%' OR option_name LIKE 'oauth1_request_%'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$delete_option = array();
		foreach ( $results as $option_name ) {
			$option = get_option( $option_name );
			if ( ! is_array( $option ) || ! isset( $option['consumer'] ) ) {
				continue;
			}
			if ( $this->post->ID === $option['consumer'] ) {
				$delete_option[] = $option_name;
			}
		}

		if ( (bool) wp_delete_post( $this->post->ID, true ) ) {
			array_map( 'delete_option', $delete_option );
			return true;
		}

		return false;
	}

	/**
	 * Add extra meta to a post.
	 *
	 * Adds the key and secret for a client to the meta on creation. Only adds
	 * them if they're not set, allowing them to be overridden for consumers
	 * with a pre-existing pair (such as via an import).
	 *
	 * @param array $meta Metadata for the post.
	 * @param array $params Parameters used to create the post.
	 * @return array Metadata to actually save.
	 */
	protected static function add_extra_meta( $meta, $params ) {
		if ( empty( $meta['key'] ) && empty( $meta['secret'] ) ) {
			$meta['key']    = wp_generate_password( self::CONSUMER_KEY_LENGTH, false );
			$meta['secret'] = wp_generate_password( self::CONSUMER_SECRET_LENGTH, false );
		}
		return parent::add_extra_meta( $meta, $params );
	}
}
