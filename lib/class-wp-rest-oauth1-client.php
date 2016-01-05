<?php

class WP_REST_OAuth1_Client extends WP_REST_Client {
	const CONSUMER_KEY_LENGTH = 12;
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
			$meta['key'] = wp_generate_password( self::CONSUMER_KEY_LENGTH, false );
			$meta['secret'] = wp_generate_password( self::CONSUMER_SECRET_LENGTH, false );
		}
		return parent::add_extra_meta( $meta, $params );
	}
}
