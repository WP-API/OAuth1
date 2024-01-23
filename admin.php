<?php
/**
 * Administration UI and utilities
 */

require __DIR__ . '/lib/class-wp-rest-oauth1-admin.php';

add_action( 'admin_menu', array( 'WP_REST_OAuth1_Admin', 'register' ) );

add_action( 'personal_options', 'rest_oauth1_profile_section', 50 );

add_action( 'all_admin_notices', 'rest_oauth1_profile_messages' );

add_action( 'personal_options_update', 'rest_oauth1_profile_save', 10, 1 );
add_action( 'edit_user_profile_update', 'rest_oauth1_profile_save', 10, 1 );

/**
 * Add a section to user profile.
 *
 * @param WP_User $user User object.
 */
function rest_oauth1_profile_section( $user ) {
	global $wpdb;

	$results  = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'oauth1_access_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$approved = array();
	foreach ( $results as $option_name ) {
		$option = get_option( $option_name );
		if ( ! is_array( $option ) || ! isset( $option['user'] ) ) {
			continue;
		}
		if ( $option['user'] === $user->ID ) {
			$approved[] = $option;
		}
	}

	?>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row"><?php _e( 'Authorized Applications', 'rest_oauth1' ); ?></th>
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
								if ( ! $application ) {
									continue;
								}
								?>
								<tr>
									<td><?php echo esc_html( $application->post_title ); ?></td>
									<td><button class="button" name="rest_oauth1_revoke" value="<?php echo esc_attr( $row['key'] ); ?>"><?php esc_html_e( 'Revoke', 'rest_oauth1' ); ?></button>
								</tr>

							<?php endforeach ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No applications authorized.', 'rest_oauth1' ); ?></p>
					<?php endif ?>
				</td>
			</tr>
			</tbody>
		</table>
	<?php
}

/**
 * REST oauth profile message callback.
 */
function rest_oauth1_profile_messages() {
	global $pagenow;
	if ( 'profile.php' !== $pagenow && 'user-edit.php' !== $pagenow ) {
		return;
	}

	if ( ! empty( $_GET['rest_oauth1_revoked'] ) ) {
		printf( '<div id="message" class="updated"><p>%s</p></div>', esc_html__( 'Token revoked.', 'rest_oauth1' ) );
	}
	if ( ! empty( $_GET['rest_oauth1_revocation_failed'] ) ) {
		printf( '<div id="message" class="updated"><p>%s</p></div>', esc_html__( 'Unable to revoke token.', 'rest_oauth1' ) );
	}
}

/**
 * REST oauth profile save callback.
 *
 * @param int $user_id User ID.
 */
function rest_oauth1_profile_save( $user_id ) {
	if ( empty( $_POST['rest_oauth1_revoke'] ) ) {
		return;
	}

	$key = wp_unslash( $_POST['rest_oauth1_revoke'] );

	$authenticator = new WP_REST_OAuth1();

	$result = $authenticator->revoke_access_token( $key );
	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'rest_oauth1_revocation_failed', true, get_edit_user_link( $user_id ) );
	} else {
		$redirect = add_query_arg( 'rest_oauth1_revoked', $key, get_edit_user_link( $user_id ) );
	}
	wp_redirect( $redirect );
	exit;
}
