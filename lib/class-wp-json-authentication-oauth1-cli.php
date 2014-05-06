<?php

class WP_JSON_Authentication_OAuth1_CLI extends WP_CLI_Command {

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
		$authenticator = new WP_JSON_Authentication_OAuth1();
		$consumer = $authenticator->add_consumer( $args );
		WP_CLI::line( sprintf( 'ID: %d',     $consumer->ID ) );
		WP_CLI::line( sprintf( 'Key: %s',    $consumer->key ) );
		WP_CLI::line( sprintf( 'Secret: %s', $consumer->secret ) );
	}
}