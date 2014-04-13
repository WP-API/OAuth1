<?php

class WP_JSON_Authentication_Authorize {
	public function register_hooks() {
		add_action( 'login_form_oauth1_authorize', array( $this, 'render_page' ) );
		add_action( 'oauth1_authorize_form', array( $this, 'page_fields' ) );
	}

	public function render_page() {
		// Check required fields
		if ( empty( $_REQUEST['oauth_token'] ) ) {
			$error = new WP_Error( 'json_oauth_missing_param', sprintf( __( 'Missing parameter %s' ), 'oauth_token' ), array( 'status' => 400 ) );
			$this->display_error( $error );
			exit;
		}

		// Set up fields
		$token = $_REQUEST['oauth_token'];
		$scope = '*';
		if ( ! empty( $_REQUEST['wp_scope'] ) ) {
			$scope = $_REQUEST['wp_scope'];
		}

		$authenticator = new WP_JSON_Authentication_OAuth1();
		$errors = array();
		$token = $authenticator->get_consumer( $_REQUEST['oauth_token'] );
		if ( is_wp_error( $token ) ) {
			$this->display_error( $token );
			exit;
		}

		$consumer = get_post(5);

		if ( ! empty( $_POST['wp-submit'] ) ) {
			check_admin_referer( $_POST['wp-submit'] );

			$token->authorize( $scope );
		}

		$file = locate_template( 'oauth1-authorize.php' );
		if ( empty( $file ) ) {
			$file = dirname( __FILE__ ) . '/theme/oauth1-authorize.php';
		}

		include $file;

		exit;
	}

	public function page_fields( $consumer ) {
		echo '<input type="hidden" name="consumer" value="' . absint( $consumer->ID ) . '" />';
		wp_nonce_field( 'oauth1_authorize' );
	}

	public function display_error( WP_Error $error ) {
		login_header( __( 'Error' ), '', $error );
?>

<?php
		login_footer();
	}
}
