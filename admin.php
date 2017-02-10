<?php
/**
 * Administration UI and utilities
 */

require dirname( __FILE__ ) . '/lib/class-wp-rest-oauth1-admin.php';

add_action( 'admin_menu', array( 'WP_REST_OAuth1_Admin', 'register' ) );

add_action( 'personal_options', 'rest_oauth1_profile_section', 50 );

add_action( 'all_admin_notices', 'rest_oauth1_profile_messages' );

add_action( 'personal_options_update',  'rest_oauth1_profile_save', 10, 1 );
add_action( 'edit_user_profile_update', 'rest_oauth1_profile_save', 10, 1 );

/**
 * Displays the list of authorized applications on user profile page.
 *
 * @param object $user User object.
 */
function rest_oauth1_profile_section( $user ) {
	global $wpdb;

	$results = $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'oauth1_access_%'", 0 ); // WPCS: db call ok, cache ok.
	$approved = array();
	foreach ( $results as $result ) {
		$row = unserialize( $result ); // @codingStandardsIgnoreLine
		if ( $row['user'] === $user->ID ) {
			$approved[] = $row;
		}
	}

	?>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Authorized Applications', 'rest_oauth1' ) ?></th>
				<td>
					<?php if ( ! empty( $approved ) ) : ?>
						<table class="widefat">
							<thead>
							<tr>
								<th style="padding-left:10px;"><?php esc_html_e( 'Application Name', 'rest_oauth1' ); ?></th>
								<th></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $approved as $row ) : ?>
								<?php
								$application = get_post( $row['consumer'] );
								?>
								<tr>
									<td><?php echo esc_html( $application->post_title ) ?></td>
									<td><button class="button" name="rest_oauth1_revoke" value="<?php echo esc_attr( $row['key'] ) ?>"><?php esc_html_e( 'Revoke', 'rest_oauth1' ) ?></button>
								</tr>

							<?php endforeach ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No applications authorized.', 'rest_oauth1' ) ?></p>
					<?php endif ?>
				</td>
			</tr>
			</tbody>
		</table>
	<?php
}

/**
 * Displays the notification message on the screen.
 */
function rest_oauth1_profile_messages() {
	global $pagenow;
	if ( 'profile.php' !== $pagenow && 'user-edit.php' !== $pagenow ) {
		return;
	}

	if ( ! empty( filter_input( INPUT_GET, 'rest_oauth1_revoked' ) ) ) {
		echo '<div id="message" class="updated"><p>' . esc_html__( 'Token revoked.', 'rest_oauth1' ) . '</p></div>';
	}
	if ( ! empty( filter_input( INPUT_GET, 'rest_oauth1_revocation_failed' ) ) ) {
		echo '<div id="message" class="updated"><p>' . esc_html__( 'Unable to revoke token.', 'rest_oauth1' ) . '</p></div>';
	}
}

/**
 * Revoke the access.
 *
 * @param $user_id
 */
function rest_oauth1_profile_save( $user_id ) {
	$rest_oauth1_revoke = filter_input( INPUT_POST, 'rest_oauth1_revoke' );
	if ( empty( $rest_oauth1_revoke ) ) {
		return;
	}

	$key = wp_unslash( $rest_oauth1_revoke );

	$authenticator = new WP_REST_OAuth1();

	$result = $authenticator->revoke_access_token( $key );
	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'rest_oauth1_revocation_failed', true, get_edit_user_link( $user_id ) );
	} else {
		$redirect = add_query_arg( 'rest_oauth1_revoked', $key, get_edit_user_link( $user_id ) );
	}
	wp_safe_redirect( $redirect );
	exit;
}
