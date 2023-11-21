<?php

/**
 *
 */
class WP_REST_OAuth1_CLI extends WP_CLI_Command {

	/**
	 * ## OPTIONS
	 *
	 * [--name=<name>]
	 * : Consumer name
	 *
	 * [--description=<description>]
	 * : Consumer description
	 */
	public function add( $_, $args ) {
		$consumer = WP_REST_OAuth1_Client::create( $args );
		if ( is_wp_error( $consumer ) ) {
			WP_CLI::Error( $consumer );
		}
		WP_CLI::line( sprintf( 'ID: %d', $consumer->ID ) );
		WP_CLI::line( sprintf( 'Key: %s', $consumer->key ) );
		WP_CLI::line( sprintf( 'Secret: %s', $consumer->secret ) );
	}
}
