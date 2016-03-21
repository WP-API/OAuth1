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

function rest_oauth1_profile_section( $user ) {
	global $wpdb;

	$results = $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'oauth1_access_%'", 0 );
	$results = array_map( 'unserialize', $results );
	$approved = array_filter( $results, function ( $row ) use ( $user ) {
		return $row['user'] === $user->ID;
	} );

	$authenticator = new WP_REST_OAuth1();

	?>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row"><?php _e( 'Authorized Applications', 'rest_oauth1' ) ?></th>
				<td>
					<?php if ( ! empty( $approved ) ): ?>
						<table class="widefat">
							<thead>
							<tr>
								<th style="padding-left:10px;"><?php esc_html_e( 'Application Name', 'rest_oauth1' ); ?></th>
								<th></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $approved as $row ): ?>
								<?php
								$application = get_post($row['consumer']);
								?>
								<tr>
									<td><?php echo esc_html( $application->post_title ) ?></td>
									<td><button class="button" name="rest_oauth1_revoke" value="<?php echo esc_attr( $row['key'] ) ?>"><?php esc_html_e( 'Revoke', 'rest_oauth1' ) ?></button>
								</tr>

							<?php endforeach ?>
							</tbody>
						</table>
					<?php else: ?>
						<p class="description"><?php esc_html_e( 'No applications authorized.', 'rest_oauth1' ) ?></p>
					<?php endif ?>
				</td>
			</tr>
			</tbody>
		</table>
	<?php
}

function rest_oauth1_profile_messages() {
	global $pagenow;
	if ( $pagenow !== 'profile.php' && $pagenow !== 'user-edit.php' ) {
		return;
	}

	if ( ! empty( $_GET['rest_oauth1_revoked'] ) ) {
		echo '<div id="message" class="updated"><p>' . __( 'Token revoked.', 'rest_oauth1' ) . '</p></div>';
	}
	if ( ! empty( $_GET['rest_oauth1_revocation_failed'] ) ) {
		echo '<div id="message" class="updated"><p>' . __( 'Unable to revoke token.', 'rest_oauth1' ) . '</p></div>';
	}
}

function rest_oauth1_profile_save( $user_id ) {
	if ( empty( $_POST['rest_oauth1_revoke'] ) ) {
		return;
	}

	$key = wp_unslash( $_POST['rest_oauth1_revoke'] );

	$authenticator = new WP_REST_OAuth1();

	$result = $authenticator->revoke_access_token( $key );
	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'rest_oauth1_revocation_failed', true, get_edit_user_link( $user_id ) );
	}
	else {
		$redirect = add_query_arg( 'rest_oauth1_revoked', $key, get_edit_user_link( $user_id ) );
	}
	wp_redirect($redirect);
	exit;
}
