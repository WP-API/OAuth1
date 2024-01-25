<?php
/**
 * Admin class.
 *
 * @package WordPress
 * @subpackage JSON API
 */

/**
 * Admin class.
 */
class WP_REST_OAuth1_Admin {
	/**
	 * Base slug.
	 */
	const BASE_SLUG = 'rest-oauth1-apps';

	/**
	 * Register the admin page
	 */
	public static function register() {
		/**
		 * Include anything we need that relies on admin classes/functions
		 */
		include_once __DIR__ . '/class-wp-rest-oauth1-listtable.php';

		$class = __CLASS__;

		$hook = add_users_page(
			// Page title.
			__( 'Registered OAuth Applications', 'rest_oauth1' ),
			// Menu title.
			_x( 'Applications', 'menu title', 'rest_oauth1' ),
			// Capability.
			'list_users',
			// Menu slug.
			self::BASE_SLUG,
			// Callback.
			array( $class, 'dispatch' )
		);

		add_action( 'load-' . $hook, array( $class, 'load' ) );
	}

	/**
	 * Get the URL for an admin page.
	 *
	 * @param array|string $params Map of parameter key => value, or wp_parse_args string.
	 * @return string Requested URL.
	 */
	protected static function get_url( $params = array() ) {
		$url    = admin_url( 'users.php' );
		$params = array( 'page' => self::BASE_SLUG ) + wp_parse_args( $params );
		return add_query_arg( urlencode_deep( $params ), $url );
	}

	/**
	 * Get the current page action.
	 *
	 * @return string One of 'add', 'edit', 'delete', or '' for default (list)
	 */
	protected static function current_action() {
		return isset( $_GET['action'] ) ? $_GET['action'] : '';
	}

	/**
	 * Load data for our page.
	 */
	public static function load() {
		switch ( self::current_action() ) {
			case 'add':
			case 'edit':
				self::render_edit_page();
				break;
			case 'delete':
				self::handle_delete();
				break;
			case 'regenerate':
				self::handle_regenerate();
				break;
			default:
				global $wp_list_table;

				$wp_list_table = new WP_REST_OAuth1_ListTable();
				$wp_list_table->prepare_items();
		}
	}

	/**
	 * Render callback.
	 */
	public static function dispatch() {
		if ( in_array( self::current_action(), array( 'add', 'edit', 'delete' ), true ) ) {
			return;
		}

		self::render();
	}

	/**
	 * Render the list page.
	 */
	public static function render() {
		global $wp_list_table;

		?>
		<div class="wrap">
			<h2>
				<?php
				esc_html_e( 'Registered Applications', 'rest_oauth1' );

				if ( current_user_can( 'create_users' ) ) {
					?>
					<a href="<?php echo esc_url( self::get_url( 'action=add' ) ); ?>"
						class="add-new-h2"><?php echo esc_html_x( 'Add New', 'application', 'rest_oauth1' ); ?></a>
					<?php
				}
				?>
			</h2>
			<?php
			if ( ! empty( $_GET['deleted'] ) ) {
				echo '<div id="message" class="updated"><p>' . esc_html__( 'Deleted application.', 'rest_oauth1' ) . '</p></div>';
			}
			?>

			<?php $wp_list_table->views(); ?>

			<form action="" method="get">

				<?php $wp_list_table->search_box( __( 'Search Applications', 'rest_oauth1' ), 'rest_oauth1' ); ?>

				<?php $wp_list_table->display(); ?>

			</form>

			<br class="clear" />

		</div>
		<?php
	}

	/**
	 * Validate parameters.
	 *
	 * @param array $params Parameters.
	 * @return array|WP_Error
	 */
	protected static function validate_parameters( $params ) {
		$error = new WP_Error();
		if ( empty( $params['name'] ) ) {
			$error->add( 'rest_oauth1_missing_name', __( 'Consumer name is required', 'rest_oauth1' ) );
		}

		if ( empty( $params['description'] ) ) {
			$error->add( 'rest_oauth1_missing_description', __( 'Consumer description is required', 'rest_oauth1' ) );
		}

		if ( empty( $params['callback'] ) ) {
			$error->add( 'rest_oauth1_missing_callback', __( 'Consumer callback is required and must be a valid URL.', 'rest_oauth1' ) );
		}

		if ( count( $error->get_error_codes() ) > 0 ) {
			return $error;
		}

		return array(
			'name'        => wp_filter_post_kses( $params['name'] ),
			'description' => wp_filter_post_kses( $params['description'] ),
			'callback'    => $params['callback'],
		);
	}

	/**
	 * Handle submission of the add page

	 * @param WP_REST_Client $consumer Consumer user.
	 *
	 * @return array|null List of errors. Issues a redirect and exits on success.
	 */
	protected static function handle_edit_submit( $consumer ) {
		if ( empty( $consumer ) ) {
			$did_action = 'add';
			check_admin_referer( 'rest-oauth1-add' );
		} else {
			$did_action = 'edit';
			check_admin_referer( 'rest-oauth1-edit-' . $consumer->ID );
		}

		// Check that the parameters are correct first.
		$params = self::validate_parameters( wp_unslash( $_POST ) );
		if ( is_wp_error( $params ) ) {
			return $params->get_error_messages();
		}

		if ( empty( $consumer ) ) {
			// Create the consumer.
			$data     = array(
				'name'        => $params['name'],
				'description' => $params['description'],
				'meta'        => array(
					'callback' => $params['callback'],
				),
			);
			$consumer = WP_REST_OAuth1_Client::create( $data );
			$result   = $consumer;
		} else {
			// Update the existing consumer post.
			$data   = array(
				'name'        => $params['name'],
				'description' => $params['description'],
				'meta'        => array(
					'callback' => $params['callback'],
				),
			);
			$result = $consumer->update( $data );
		}

		if ( is_wp_error( $result ) ) {
			return $result->get_error_messages();
		}

		// Success, redirect to alias page.
		$location = self::get_url(
			array(
				'action'     => 'edit',
				'id'         => $consumer->ID,
				'did_action' => $did_action,
			)
		);
		wp_safe_redirect( $location );
		exit;
	}

	/**
	 * Output alias editing page.
	 */
	public static function render_edit_page() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'rest_oauth1' ) );
		}

		// Are we editing?
		$consumer          = null;
		$regenerate_action = '';
		$form_action       = self::get_url( 'action=add' );
		if ( ! empty( $_REQUEST['id'] ) ) {
			$id       = absint( $_REQUEST['id'] );
			$consumer = WP_REST_OAuth1_Client::get( $id );
			if ( is_wp_error( $consumer ) ) {
				wp_die( $consumer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			if ( empty( $consumer ) ) {
				wp_die( esc_html__( 'Invalid consumer ID.', 'rest_oauth1' ) );
			}

			$form_action       = self::get_url(
				array(
					'action' => 'edit',
					'id'     => $id,
				)
			);
			$regenerate_action = self::get_url(
				array(
					'action' => 'regenerate',
					'id'     => $id,
				)
			);
		}

		// Handle type of notice.
		$notice_type = 'info';
		$messages    = array();
		if ( ! empty( $_POST['submit'] ) ) {
			$messages    = self::handle_edit_submit( $consumer );
			$notice_type = 'error';
		}
		if ( ! empty( $_GET['did_action'] ) ) {
			switch ( $_GET['did_action'] ) {
				case 'edit':
					$messages[]  = __( 'Updated application.', 'rest_oauth1' );
					$notice_type = 'info';

					break;

				case 'regenerate':
					$messages[]  = __( 'Regenerated secret.', 'rest_oauth1' );
					$notice_type = 'success';
					break;

				default:
					$messages[]  = __( 'Successfully created application.', 'rest_oauth1' );
					$notice_type = 'success';
					break;
			}
		}

		$data = array();

		if ( empty( $consumer ) || ! empty( $_POST['_wpnonce'] ) ) {
			foreach ( array( 'name', 'description', 'callback' ) as $key ) {
				$data[ $key ] = empty( $_POST[ $key ] ) ? '' : wp_unslash( $_POST[ $key ] );
			}
		} else {
			$data['name']        = $consumer->post_title;
			$data['description'] = $consumer->post_content;
			$data['callback']    = $consumer->callback;
		}

		// Header time!
		global $title, $parent_file, $submenu_file;
		$title        = $consumer ? __( 'Edit Application', 'rest_oauth1' ) : __( 'Add Application', 'rest_oauth1' );
		$parent_file  = 'users.php';
		$submenu_file = self::BASE_SLUG;

		include ABSPATH . 'wp-admin/admin-header.php';
		?>

	<div class="wrap">
		<h2 id="edit-site"><?php echo esc_html( $title ); ?></h2>

		<?php
		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				printf( '<div id="message" class="notice is-dismissible notice-%s"><p>%s</p></div>', esc_attr( $notice_type ), esc_html( $msg ) );
			}
		}
		?>

		<form method="post" action="<?php echo esc_url( $form_action ); ?>">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="oauth-name"><?php echo esc_html_x( 'Consumer Name', 'field name', 'rest_oauth1' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text"
							name="name" id="oauth-name"
							value="<?php echo esc_attr( $data['name'] ); ?>" />
						<p class="description"><?php esc_html_e( 'This is shown to users during authorization and in their profile.', 'rest_oauth1' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oauth-description"><?php echo esc_html_x( 'Description', 'field name', 'rest_oauth1' ); ?></label>
					</th>
					<td>
						<textarea class="regular-text" name="description" id="oauth-description"
							cols="30" rows="5" style="width: 500px"><?php echo esc_textarea( $data['description'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oauth-callback"><?php echo esc_html_x( 'Callback', 'field name', 'rest_oauth1' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text"
							name="callback" id="oauth-callback"
							value="<?php echo esc_attr( $data['callback'] ); ?>" />
						<p class="description"><?php esc_html_e( "Your application's callback URL. The callback passed with the request token must match the scheme, host, port, and path of this URL.", 'rest_oauth1' ); ?></p>
					</td>
				</tr>
			</table>

			<?php

			if ( empty( $consumer ) ) {
				wp_nonce_field( 'rest-oauth1-add' );
				submit_button( __( 'Add Consumer', 'rest_oauth1' ) );
			} else {
				echo '<input type="hidden" name="id" value="' . esc_attr( $consumer->ID ) . '" />';
				wp_nonce_field( 'rest-oauth1-edit-' . $consumer->ID );
				submit_button( __( 'Save Consumer', 'rest_oauth1' ) );
			}

			?>
		</form>

		<?php if ( ! empty( $consumer ) ) { ?>
			<form method="post" action="<?php echo esc_url( $regenerate_action ); ?>">
				<h3><?php esc_html_e( 'OAuth Credentials', 'rest_oauth1' ); ?></h3>

				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Client Key', 'rest_oauth1' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( $consumer->key ); ?></code>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Client Secret', 'rest_oauth1' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( $consumer->secret ); ?></code>
						</td>
					</tr>
				</table>

				<?php
				wp_nonce_field( 'rest-oauth1-regenerate:' . $consumer->ID );
				submit_button( __( 'Regenerate Secret', 'rest_oauth1' ), 'delete' );
				?>
			</form>
		<?php } ?>
	</div>

		<?php
	}

	/**
	 * Handle delete of client.
	 */
	public static function handle_delete() {
		if ( empty( $_GET['id'] ) ) {
			return;
		}

		$id = $_GET['id'];
		check_admin_referer( 'rest-oauth1-delete:' . $id );

		if ( ! current_user_can( 'delete_post', $id ) ) {
			$code = is_user_logged_in() ? 403 : 401;
			wp_die(
				sprintf(
					'<h1>%s</h1><p>%s</p>',
					esc_html__( 'You are not allowed to delete this application.', 'rest_oauth1' ),
					esc_html__( 'An error has occurred.', 'rest_oauth1' )
				),
				'',
				array( 'response' => (int) $code )
			);
		}

		$client = WP_REST_OAuth1_Client::get( $id );
		if ( is_wp_error( $client ) ) {
			wp_die( $client ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! $client->delete() ) {
			$code = is_user_logged_in() ? 403 : 401;
			wp_die(
				sprintf(
					'<h1>%s</h1><p>%s</p>',
					esc_html__( 'An error has occurred.', 'rest_oauth1' ),
					esc_html__( 'Invalid consumer ID', 'rest_oauth1' )
				),
				'',
				array( 'response' => (int) $code )
			);
		}

		wp_safe_redirect( self::get_url( 'deleted=1' ) );
		exit;
	}

	/**
	 * Handle regeneration of OAuth secret.
	 */
	public static function handle_regenerate() {
		if ( empty( $_GET['id'] ) ) {
			return;
		}

		$id = $_GET['id'];
		check_admin_referer( 'rest-oauth1-regenerate:' . $id );

		if ( ! current_user_can( 'edit_post', $id ) ) {
			$code = is_user_logged_in() ? 403 : 401;
			wp_die(
				sprintf(
					'<h1>%s</h1><p>%s</p>',
					esc_html__( 'An error has occurred.', 'rest_oauth1' ),
					esc_html__( 'You are not allowed to edit this application.', 'rest_oauth1' )
				),
				'',
				array( 'response' => (int) $code )
			);
		}

		$client = WP_REST_OAuth1_Client::get( $id );
		if ( is_wp_error( $client ) ) {
			wp_die( $client ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$result = $client->regenerate_secret();
		if ( is_wp_error( $result ) ) {
			wp_die( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		wp_safe_redirect(
			self::get_url(
				array(
					'action'     => 'edit',
					'id'         => $id,
					'did_action' => 'regenerate',
				)
			)
		);
		exit;
	}
}
