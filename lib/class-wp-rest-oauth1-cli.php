<?php

/**
 *
 */
class WP_REST_OAuth1_CLI extends WP_CLI_Command {

	/**
	 * Creates a new OAuth1 Client.
	 *
	 * ## OPTIONS
	 *
	 * [--name=<name>]
	 * : Consumer name
	 *
	 * [--description=<description>]
	 * : Consumer description
	 *
	 * [--callback=<callback>]
	 * : Consumer callback
	 */
	public function add( $args, $assoc_args ) {
		$consumer = WP_REST_OAuth1_Client::create( $assoc_args );
		if ( is_wp_error( $consumer ) ) {
			WP_CLI::error( $consumer );
		}

		WP_CLI::line(
			sprintf(
			/* translators: %d: client ID **/
				__( 'ID: %d', 'rest_oauth1' ),
				$consumer->ID
			)
		);
		WP_CLI::line(
			sprintf(
			/* translators: %d: client key **/
				__( 'Key: %s', 'rest_oauth1' ),
				$consumer->key
			)
		);
		WP_CLI::line(
			sprintf(
			/* translators: %d: client secret **/
				__( 'Secret: %s', 'rest_oauth1' ),
				$consumer->secret
			)
		);
	}

	/**
	 * Delete a new OAuth1 Client.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Database ID for the client.
	 */
	public function delete( $args ) {
		$consumer = WP_REST_OAuth1_Client::get( $args[0] );
		if ( is_wp_error( $consumer ) ) {
			WP_CLI::error( $consumer );
		}
		if ( ! $consumer->delete() ) {
			WP_CLI::error(
				sprintf(
				/* translators: %d: client ID **/
					__( 'Unable to delete client with ID: %d', 'rest_oauth1' ),
					$consumer->ID
				)
			);
		}

		WP_CLI::success(
			sprintf(
			/* translators: %d: client ID **/
				__( 'Client deleted with ID: %d', 'rest_oauth1' ),
				$consumer->ID
			)
		);
	}
}
