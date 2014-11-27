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
			'name' => __( 'API Clients' ),
			'singular_name' => __( 'API Client' ),
			'add_new' => __( 'Add New' ),
			'add_new_item' => __( 'Add New Client' ),
			'edit_item' => __( 'Edit Client' ),
			'new_item' => __( 'New Client' ),
			'view_item' => __( 'View Client' ),
			'search_items' => __( 'Search Clients' ),
			'not_found' => __( 'No clients found' ),
			'not_found_in_trash' => __( 'No clients found in Trash' )
		),
		'public' => true,
		'exclude_from_search' => true,
		'publicly_queryable' => false,
		'show_in_nav_menus' => false,
		'show_in_admin_bar' => false,
		'hierarchical' => false,
		'rewrite' => false,
		'delete_with_user' => true,
		'query_var' => false,
		'can_export' => false,
		'menu_icon' => 'dashicons-admin-network',
		'menu_position' => 80,
		'supports' => array('title'),
		'register_meta_box_cb' => 'json_key_secret_metabox'
		)
	);
}
add_action( 'init', 'json_oauth_server_setup_authentication' );


/**
 * json_above_the_title function.
 *
 * @access public
 * @return void
 */
function json_text_above_the_title_editor( $post ) {
	if( $post->post_type == 'json_consumer' ) {
 		_e('<p>Enter a client name and publish. A key and secret will be generated for you.</p>');
 	}
}
add_action( 'edit_form_top', 'json_text_above_the_title_editor' );


/**
 * json_enter_title_here_filter function.
 *
 * @access public
 * @param mixed $label
 * @param mixed $post
 * @return void
 */
function json_enter_title_here_filter( $label, $post ){
	if( $post->post_type == 'json_consumer' )
		$label= __( 'Enter Client Name Here', 'oauth' );
	return $label;
}
add_filter( 'enter_title_here', 'json_enter_title_here_filter', 2, 2 );



/**
 * json_remove_permalink_from_title function.
 * 
 * @access public
 * @param mixed $return
 * @return void
 */
function json_remove_permalink_from_title( $return, $id ) {
	$post = get_post( $id );
	if( $post->post_type == 'json_consumer' )
		$return = '';
	return $return;
}
add_filter( 'get_sample_permalink_html', 'json_remove_permalink_from_title', 10, 2 );



/**
 * json_custom_publish_box function.
 * 
 * removes unneccesary items from publish meta box
 * @access public
 * @return void
 */
function json_custom_publish_box() {

	if ( 'json_consumer' == get_post_type() ) {

		 if( !is_admin() )
			 return;

		 $style = '';
		 $style .= '<style type="text/css">';
		 $style .= '#edit-slug-box, #minor-publishing-actions, #visibility, .num-revisions, .curtime';
		 $style .= '{display: none; }';
		 $style .= '</style>';

		 echo $style;
	}
}
add_action( 'admin_head', 'json_custom_publish_box' );


/**
 * json_remove_list_row_actions function.
 *
 * @access public
 * @param mixed $actions
 * @param mixed $post
 * @return void
 */
function json_remove_list_row_actions( $actions, $post ) {
	if( $post->post_type == 'json_consumer' ) {
		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['view'] );
	}
	return $actions;
}
add_filter( 'page_row_actions', 'json_remove_list_row_actions', 10, 2 );
add_filter( 'post_row_actions', 'json_remove_list_row_actions', 10, 2 );


/**
 * json_key_secret_metabox function.
 *
 * @access public
 * @param mixed $post
 * @return void
 */
function json_key_secret_metabox( $post ){

	if( $post->post_type == 'json_consumer' ) {

		add_meta_box(
			'api-client',
			__( 'API Keys', 'oauth' ),
			'json_key_secret_metabox_callback',
			'json_consumer'
		);

	}
}


/**
 * json_key_secret_metabox_callback function.
 *
 * @access public
 * @param mixed $post
 * @return void
 */
function json_key_secret_metabox_callback( $post ){

	$secret = get_post_meta( $post->ID, 'secret', true );
	$key = get_post_meta( $post->ID, 'key', true );

	if ( $key ) {
		 echo '<ul>';
			 echo '<li> key:	 ' . $key . '</li>';
			 echo '<li> secret:	 ' . $secret . '</li></br>';
		 echo '</ul>';
		 echo 'Renew Keys? <input type="checkbox" id="renewkey" name="renewkey">';
	} else {
		echo 'Keys are created when client is published.';
	}

}


/**
 * json_create_key_secret function.
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function json_create_key_secret( $post_id ) {


	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check the user's permissions.
	if ( isset( $_POST['publish'] ) || isset( $_POST['save'] ) && $_POST['post_type'] === 'json_consumer' ) {

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// check if keys exist or to renew them
		$keytrue = get_post_meta( $post_id, 'key' );

		if( isset( $_REQUEST['renewkey'] ) || !$keytrue ) {
			$key = wp_generate_password( 12, false );
			$secret = wp_generate_password( 48, false );

			update_post_meta( $post_id, 'key', $key );
			update_post_meta( $post_id, 'secret', $secret );
		}
	}


}
add_action( 'save_post', 'json_create_key_secret', 99 );


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
