<?php
/**
 * Plugin Name: OAuth Server
 * Version 0.1
 */

include_once( dirname( __FILE__ ) . '/lib/class-wp-json-authentication.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-authentication-oauth1.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-authentication-oauth1-authorize.php' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include_once( dirname( __FILE__ ) . '/lib/class-wp-json-authentication-oauth1-cli.php' );

	WP_CLI::add_command( 'oauth1', 'WP_JSON_Authentication_OAuth1_CLI' );
}

/**
 * Register our rewrite rules for the API
 */
function json_oauth_server_init() {
	json_oauth_server_register_rewrites();

	global $wp;
	$wp->add_query_var('json_oauth_route');
}
add_action( 'init', 'json_oauth_server_init' );

function json_oauth_server_register_rewrites() {
	add_rewrite_rule( '^oauth1/authorize/?$','index.php?json_oauth_route=authorize','top' );
	add_rewrite_rule( '^oauth1/request/?$','index.php?json_oauth_route=request','top' );
	add_rewrite_rule( '^oauth1/access/?$','index.php?json_oauth_route=access','top' );
}

function json_oauth_server_setup_authentication() {
	register_post_type( 'json_consumer', array(
		'labels' => array(
			'name' => __( 'Consumer' ),
			'singular_name' => __( 'Consumers' ),
		),
		'public' => false,
		'hierarchical' => false,
		'rewrite' => false,
		'delete_with_user' => true,
		'query_var' => false,
	) );
}
add_action( 'init', 'json_oauth_server_setup_authentication' );

/**
 * Register the authorization page
 *
 * Alas, login_init is too late to register pages, as the action is already
 * sanitized before this.
 */
function json_oauth_load() {
	global $wp_json_authentication_oauth1;

	$wp_json_authentication_oauth1 = new WP_JSON_Authentication_OAuth1();
	add_filter( 'determine_current_user', array( $wp_json_authentication_oauth1, 'authenticate' ) );
	add_filter( 'json_authentication_errors', array( $wp_json_authentication_oauth1, 'get_authentication_errors' ) );
}
add_action( 'init', 'json_oauth_load' );

/**
 * Force reauthentication after we've registered our handler
 *
 * We could have checked authentication before OAuth was loaded. If so, let's
 * try and reauthenticate now that OAuth is loaded.
 */
function json_oauth_force_reauthentication() {
	if ( is_user_logged_in() ) {
		// Another handler has already worked successfully, no need to
		// reauthenticate.

		return;
	}

	// Force reauthentication
	global $current_user;
	$current_user = null;
	get_currentuserinfo();
}
add_action( 'init', 'json_oauth_force_reauthentication', 100 );

/**
 * Load the JSON API
 */
function json_oauth_server_loaded() {
	if ( empty( $GLOBALS['wp']->query_vars['json_oauth_route'] ) )
		return;

	$authenticator = new WP_JSON_Authentication_OAuth1();
	$response = $authenticator->dispatch( $GLOBALS['wp']->query_vars['json_oauth_route'] );

	if ( is_wp_error( $response ) ) {
		$error_data = $response->get_error_data();
		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status = $error_data['status'];
		}
		else {
			$status = 500;
		}

		status_header( $status );
		echo $response->get_error_message();
		die();
	}

	header( 'Content-Type: application/x-www-form-urlencoded; charset=utf-8' );
	$response = http_build_query( $response, '', '&' );

	echo $response;

	// Finish off our request
	die();
}
add_action( 'template_redirect', 'json_oauth_server_loaded', -100 );

/**
 * Register API routes
 *
 * @param array $data Index data
 * @return array Filtered data
 */
function json_oauth_api_routes( $data ) {
	if (empty($data['authentication'])) {
		$data['authentication'] = array();
	}

	$data['authentication']['oauth1'] = array(
		'request' => home_url( 'oauth1/request' ),
		'authorize' => home_url( 'oauth1/authorize' ),
		'access' => home_url( 'oauth1/access' ),
		'version' => '0.1',
	);
	return $data;
}
add_filter( 'json_index', 'json_oauth_api_routes' );

/**
 * Register the authorization page
 *
 * Alas, login_init is too late to register pages, as the action is already
 * sanitized before this.
 */
function json_oauth_load_authorize_page() {
	$authorizer = new WP_JSON_Authentication_OAuth1_Authorize();
	$authorizer->register_hooks();
}
add_action( 'init', 'json_oauth_load_authorize_page' );

/**
 * Register routes and flush the rewrite rules on activation.
 */
function json_oauth_server_activation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {

			switch_to_blog( $mu_blog['blog_id'] );
			json_oauth_server_register_rewrites();
			flush_rewrite_rules();
		}

		restore_current_blog();

	} else {

		json_oauth_server_register_rewrites();
		flush_rewrite_rules();
	}
}
register_activation_hook( __FILE__, 'json_oauth_server_activation' );

/**
 * Flush the rewrite rules on deactivation
 */
function json_oauth_server_deactivation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {

			switch_to_blog( $mu_blog['blog_id'] );
			flush_rewrite_rules();
		}

		restore_current_blog();

	} else {

		flush_rewrite_rules();
	}
}
register_deactivation_hook( __FILE__, 'json_oauth_server_deactivation' );
