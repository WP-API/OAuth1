<?php
/**
 * Template file for OAuth1 screen.
 *
 * @package WordPress
 * @subpackage JSON API
 *
 * @global string $errors
 * @global string $token_key
 * @global WP_Post $consumer
 */

login_header(
	__( 'Authorize', 'rest_oauth1' ),
	'',
	$errors
);

$this_user = wp_get_current_user();

$url = site_url( 'wp-login.php?action=oauth1_authorize', 'login_post' );
$url = add_query_arg( 'oauth_token', $token_key, $url );

?>

<style>

	.login-title {
		margin-bottom: 15px;
	}

	.login-info .avatar {
		margin-right: 15px;
		margin-bottom: 15px;
		float: left;
	}

	#login form .login-info p {
		margin-bottom: 15px;
	}

	/** Note - login scope has not yet been implemented. **/
	.login-scope {
		clear: both;
		margin-bottom: 15px;
	}

	.login-scope h4 {
		margin-bottom: 10px;
	}

	.login-scope ul {
		margin-left: 1.5em;
	}

	.submit {
		clear: both;
	}

	.submit .button {
		margin-right: 10px;
		float: left;
	}

</style>

<form name="oauth1_authorize_form" id="oauth1_authorize_form" action="<?php echo esc_url( $url ); ?>" method="post">

	<h2 class="login-title">
	<?php
	echo esc_html(
		/* translators: %s: Consumer name.**/
		sprintf( __( 'Connect %1$s', 'rest_oauth1' ), $consumer->post_title )
	);
	?>
	</h2>

	<div class="login-info">

		<?php echo get_avatar( $this_user->ID, '78' ); ?>

		<p>
		<?php
			printf(
				// translators: 1: username. 2: consumer name.
				wp_kses( __( 'Howdy <strong>%1$s</strong>,<br/> "%2$s" would like to connect to %3$s.', 'rest_oauth1' ), array( 'strong', 'br' ) ),
				esc_html( $this_user->user_login ),
				esc_html( $consumer->post_title ),
				esc_html( get_bloginfo( 'name' ) )
			)
			?>
		</p>

	</div>

	<?php
	/**
	 * Fires inside the lostpassword <form> tags, before the hidden fields.
	 *
	 * @since 2.1.0
	 */
	do_action( 'oauth1_authorize_form', $consumer );
	?>
	<p class="submit">
		<button type="submit" name="wp-submit" value="authorize" class="button button-primary button-large"><?php _e( 'Authorize', 'rest_oauth1' ); ?></button>
		<button type="submit" name="wp-submit" value="cancel" class="button button-large"><?php _e( 'Cancel', 'rest_oauth1' ); ?></button>
	</p>

</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url( $url, true ) ); ?>"><?php _e( 'Switch user', 'rest_oauth1' ); ?></a>
<?php
if ( get_option( 'users_can_register' ) ) :
	$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register', 'rest_oauth1' ) );
	/**
	 * Filter the registration URL below the login form.
	 *
	 * @since 1.5.0
	 *
	 * @param string $registration_url Registration URL.
	 */
	echo ' | ' . esc_url( apply_filters( 'register', $registration_url ) );
endif;
?>
</p>

<?php
login_footer();
