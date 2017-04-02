<?php
login_header(
	__('Authorize', 'oauth'),
	'',
	$errors
);

$current_user = wp_get_current_user();

$url = site_url( 'wp-login.php?action=oauth1_authorize', 'login_post' );
$url = add_query_arg( 'oauth_token', $token_key, $url );

// Filterable Prefs
$pref_show_avatar 	= apply_filters( 'json_oauth1.theme.authorize.show_avatar', true, $consumer );
$pref_title 		= apply_filters( 'json_oauth1.theme.authorize.title', sprintf( __('Connect %1$s'), $consumer->post_title ), $consumer );
$pref_description	= apply_filters( 'json_oauth1.theme.authorize.description', sprintf( __( '<strong>%1$s</strong>,<br/> "%2$s" would like to connect to %3$s.' ), $current_user->user_login, $consumer->post_title, get_bloginfo( 'name' ) ), $consumer );

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

	<h2 class="login-title"><?php echo esc_html( $pref_title ) ?></h2>

	<?php do_action( 'json_oauth1.theme.authorize.before_login_info', $consumer ); ?>

	<div class="login-info">

		<?php if ( $pref_show_avatar ): ?><?php echo get_avatar( $current_user->ID, '78' ); ?><?php endif; ?>

		<p><?php echo $pref_description; ?></p>

	</div>

	<?php do_action( 'json_oauth1.theme.authorize.after_login_info', $consumer ); ?>

	<?php
	/**
	 * Fires inside the lostpassword <form> tags, before the hidden fields.
	 *
	 * @since 2.1.0
	 */
	do_action( 'oauth1_authorize_form', $consumer ); ?>
	<p class="submit">
		<button type="submit" name="wp-submit" value="authorize" class="button button-primary button-large"><?php _e('Authorize'); ?></button>
		<button type="submit" name="wp-submit" value="cancel" class="button button-large"><?php _e('Cancel'); ?></button>
	</p>

</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url( $url, true ) ); ?>"><?php _e( 'Switch user' ) ?></a>
<?php
if ( get_option( 'users_can_register' ) ) :
	$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );
	/**
	 * Filter the registration URL below the login form.
	 *
	 * @since 1.5.0
	 *
	 * @param string $registration_url Registration URL.
	 */
	echo ' | ' . apply_filters( 'register', $registration_url );
endif;
?>
</p>

<?php
login_footer();
