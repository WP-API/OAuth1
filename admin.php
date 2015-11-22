<?php
/**
 * Administration UI and utilities
 */

add_action( 'admin_menu', 'json_oauth_admin_register' );
add_action( 'admin_init', 'json_oauth_admin_prerender' );

add_action( 'admin_action_json-oauth-add', 'json_oauth_admin_edit_page' );
add_action( 'admin_action_json-oauth-edit', 'json_oauth_admin_edit_page' );

add_action( 'personal_options', 'json_oauth_profile_section', 50 );

add_action( 'all_admin_notices', 'json_oauth_profile_messages' );

add_action( 'personal_options_update',  'json_oauth_profile_save', 10, 1 );
add_action( 'edit_user_profile_update', 'json_oauth_profile_save', 10, 1 );

/**
 * Register the admin page
 */
function json_oauth_admin_register() {
	/**
	 * Include anything we need that relies on admin classes/functions
	 */
	include_once dirname( __FILE__ ) . '/lib/class-wp-json-authentication-oauth1-listtable.php';

	add_users_page(
		// Page title
		__( 'Registered OAuth Applications', 'json_oauth' ),

		// Menu title
		_x( 'Applications', 'menu title', 'json_oauth' ),

		// Capability
		'list_users',

		// Menu slug
		'json-oauth',

		// Callback
		'json_oauth_admin_render'
	);
}

function json_oauth_admin_prerender() {
	$hook = get_plugin_page_hook( 'json-oauth', 'users.php' );

	add_action( 'load-' . $hook, 'json_oauth_admin_load' );
}

function json_oauth_admin_load() {
	global $wp_list_table;

	$wp_list_table = new WP_JSON_Authentication_OAuth1_ListTable();

	$wp_list_table->prepare_items();
}

function json_oauth_admin_render() {
	global $wp_list_table;

	// ...
	?>
	<div class="wrap">
		<h2>
			<?php
			esc_html_e( 'Registered OAuth Applications', 'json_oauth' );

			if ( current_user_can( 'create_users' ) ): ?>
				<a href="<?php echo admin_url( 'admin.php?action=json-oauth-add' ) ?>"
					class="add-new-h2"><?php echo esc_html_x( 'Add New', 'application', 'json_oauth' ); ?></a>
			<?php
			endif;
			?>
		</h2>

		<?php $wp_list_table->views(); ?>

		<form action="" method="get">

			<?php $wp_list_table->search_box( __( 'Search Applications', 'json_oauth' ), 'json_oauth' ); ?>

			<?php $wp_list_table->display(); ?>

		</form>

		<br class="clear" />

	</div>
	<?php
}

function json_oauth_admin_validate_parameters( $params ) {
	$valid = array();

	if ( empty( $params['name'] ) ) {
		return new WP_Error( 'json_oauth_missing_name', __( 'Consumer name is required' ) );
	}
	$valid['name'] = wp_filter_post_kses( $params['name'] );

	if ( empty( $params['description'] ) ) {
		return new WP_Error( 'json_oauth_missing_description', __( 'Consumer description is required' ) );
	}
	$valid['description'] = wp_filter_post_kses( $params['description'] );

	return $valid;
}

/**
 * Handle submission of the add page
 *
 * @return array|null List of errors. Issues a redirect and exits on success.
 */
function json_oauth_admin_handle_edit_submit( $consumer ) {
	$messages = array();
	if ( empty( $consumer ) ) {
		$did_action = 'add';
		check_admin_referer( 'json-oauth-add' );
	}
	else {
		$did_action = 'edit';
		check_admin_referer( 'json-oauth-edit-' . $consumer->ID );
	}

	// Check that the parameters are correct first
	$params = json_oauth_admin_validate_parameters( wp_unslash( $_POST ) );
	if ( is_wp_error( $params ) ) {
		$messages[] = $params->get_error_message();
		return $messages;
	}

	if ( empty( $consumer ) ) {
		$authenticator = new WP_REST_OAuth1();

		// Create the consumer
		$data = array(
			'name' => $params['name'],
			'description' => $params['description'],
		);
		$consumer = $result = $authenticator->add_consumer( $data );
	}
	else {
		// Update the existing consumer post
		$data = array(
			'ID' => $consumer->ID,
			'post_title' => $params['name'],
			'post_content' => $params['description'],
		);
		$result = wp_update_post( $data, true );
	}

	if ( is_wp_error( $result ) ) {
		$messages[] = $result->get_error_message();

		return $messages;
	}

	// Success, redirect to alias page
	$location = add_query_arg(
		array(
			'action'     => 'json-oauth-edit',
			'id'         => $consumer->ID,
			'did_action' => $did_action,
			'processed'  => 1,
			'_wpnonce'   => wp_create_nonce( 'json-oauth-edit-' . $id ),
		),
		network_admin_url( 'admin.php' )
	);
	wp_safe_redirect( $location );
	exit;
}

/**
 * Output alias editing page
 */
function json_oauth_admin_edit_page() {
	if ( ! current_user_can( 'edit_users' ) )
		wp_die( __( 'You do not have permission to access this page.' ) );

	// Are we editing?
	$consumer = null;
	$form_action = admin_url( 'admin.php?action=json-oauth-add' );
	if ( ! empty( $_REQUEST['id'] ) ) {
		$id = absint( $_REQUEST['id'] );
		$consumer = get_post( $id );
		if ( is_wp_error( $consumer ) || empty( $consumer ) ) {
			wp_die( __( 'Invalid consumer ID.' ) );
		}

		$form_action = admin_url( 'admin.php?action=json-oauth-edit' );
	}

	// Handle form submission
	$messages = array();
	if ( ! empty( $_POST['submit'] ) ) {
		$messages = json_oauth_admin_handle_edit_submit( $consumer );
	}

	$data = array();

	if ( empty( $consumer ) || ! empty( $_POST['_wpnonce'] ) ) {
		foreach ( array( 'name', 'description' ) as $key ) {
			$data[ $key ] = empty( $_POST[ $key ] ) ? '' : wp_unslash( $_POST[ $key ] );
		}
	}
	else {
		$data['name'] = $consumer->post_title;
		$data['description'] = $consumer->post_content;
	}

	// Header time!
	global $title, $parent_file, $submenu_file;
	$title = $consumer ? __( 'Edit Consumer' ) : __( 'Add Consumer' );
	$parent_file = 'users.php';
	$submenu_file = 'json-oauth';

	include( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
	<h2 id="edit-site"><?php echo esc_html( $title ) ?></h2>

	<?php
	if ( ! empty( $messages ) ) {
		foreach ( $messages as $msg )
			echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
	}
	?>

	<form method="post" action="<?php echo esc_url( $form_action ) ?>">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="oauth-name"><?php echo esc_html_x( 'Consumer Name', 'field name' ) ?></label>
				</th>
				<td>
					<input type="text" class="regular-text"
						name="name" id="oauth-name"
						value="<?php echo esc_attr( $data['name'] ) ?>" />
					<p class="description"><?php echo esc_html( 'This is shown to users during authorization and in their profile.' ) ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="oauth-description"><?php echo esc_html_x( 'Description', 'field name' ) ?></label>
				</th>
				<td>
					<textarea class="regular-text" name="description" id="oauth-description"
						cols="30" rows="5" style="width: 500px"><?php echo esc_textarea( $data['description'] ) ?></textarea>
				</td>
			</tr>

			<?php if ( ! empty( $consumer ) ): ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( 'Client Key' ) ?>
					</th>
					<td>
						<code><?php echo esc_html( $consumer->key ) ?></code>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( 'Client Secret' ) ?>
					</th>
					<td>
						<code><?php echo esc_html( $consumer->secret ) ?></code>
					</td>
				</tr>
			<?php endif ?>
		</table>

		<?php

		if ( empty( $consumer ) ) {
			wp_nonce_field( 'json-oauth-add' );
			submit_button( __( 'Add Consumer' ) );
		}
		else {
			echo '<input type="hidden" name="id" value="' . esc_attr( $consumer->ID ) . '" />';
			wp_nonce_field( 'json-oauth-edit-' . $consumer->ID );
			submit_button( __( 'Save Consumer' ) );
		}

		?>
	</form>
</div>

<?php

	include(ABSPATH . 'wp-admin/admin-footer.php');
}

function json_oauth_profile_section( $user ) {
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
				<th scope="row"><?php _e( 'Authorized Applications', 'json_oauth' ) ?></th>
				<td>
					<?php if ( ! empty( $approved ) ): ?>
						<table class="widefat sessions-table">
							<thead>
							<tr>
								<th scope="col"><?php _e( 'Application Name', 'wpsm' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $approved as $row ): ?>
								<?php
								$application = rest_get_client( 'oauth1', $row['consumer'] );
								?>
								<tr>
									<td><?php echo esc_html( $application->post_title ) ?></td>
									<td><button class="button" name="oauth_revoke" value="<?php echo esc_attr( $row['key'] ) ?>"><?php esc_html_e( 'Revoke', 'json_oauth' ) ?></button>
								</tr>

							<?php endforeach ?>
							</tbody>
						</table>
					<?php else: ?>
						<p class="description"><?php esc_html_e( 'No applications authorized.' ) ?></p>
					<?php endif ?>
				</td>
			</tr>
			</tbody>
		</table>
	<?php
}

function json_oauth_profile_messages() {
	global $pagenow;
	if ( $pagenow !== 'profile.php' && $pagenow !== 'user-edit.php' ) {
		return;
	}

	if ( ! empty( $_GET['oauth_revoked'] ) ) {
		echo '<div id="message" class="updated"><p>' . __( 'Token revoked.' ) . '</p></div>';
	}
	if ( ! empty( $_GET['oauth_revocation_failed'] ) ) {
		echo '<div id="message" class="updated"><p>' . __( 'Unable to revoke token.' ) . '</p></div>';
	}
}

function json_oauth_profile_save( $user_id ) {
	if ( empty( $_POST['oauth_revoke'] ) ) {
		return;
	}

	$key = wp_unslash( $_POST['oauth_revoke'] );

	$authenticator = new WP_REST_OAuth1();

	$result = $authenticator->revoke_access_token( $key );
	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'oauth_revocation_failed', true, get_edit_user_link( $user_id ) );
	}
	else {
		$redirect = add_query_arg( 'oauth_revoked', $key, get_edit_user_link( $user_id ) );
	}
	wp_redirect($redirect);
	exit;
}
