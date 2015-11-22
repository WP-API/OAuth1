<?php

/**
 * Get a client by key.
 *
 * @param string $type Client type.
 * @param string $key Client key.
 * @return WP_Post|WP_Error
 */
function rest_get_client( $type, $key ) {
	// $this->should_attempt = false;

	$query = new WP_Query();
	$consumers = $query->query( array(
		'post_type' => 'json_consumer',
		'post_status' => 'any',
		'meta_query' => array(
			array(
				'meta_key' => 'key',
				'meta_value' => $key,
			),
			array(
				'meta_key' => 'type',
				'meta_value' => $type,
			),
		),
	) );

	// $this->should_attempt = true;

	if ( empty( $consumers ) || empty( $consumers[0] ) ) {
		return new WP_Error( 'json_consumer_notfound', __( 'Consumer Key is invalid' ), array( 'status' => 401 ) );
	}

	return $consumers[0];
}

/**
 * Create a new client.
 *
 * @param string $type Client type.
 * @param array $params {
 *     @type string $name Client name
 *     @type string $description Client description
 *     @type array $meta Metadata for the client (map of key => value)
 * }
 * @return WP_Post|WP_Error
 */
function rest_create_client( $type, $params ) {
	$default = array(
		'name' => '',
		'description' => '',
		'meta' => array(),
	);
	$params = wp_parse_args( $params, $default );

	$data = array();
	$data['post_title'] = $params['name'];
	$data['post_content'] = $params['description'];
	$data['post_type'] = 'json_consumer';

	$ID = wp_insert_post( $data );
	if ( is_wp_error( $ID ) ) {
		return $ID;
	}

	$meta = $params['meta'];
	$meta['type'] = $type;
	$meta = apply_filters( 'json_consumer_meta', $meta, $ID, $params );

	foreach ( $meta as $key => $value ) {
		update_post_meta( $ID, $key, $value );
	}

	return get_post( $ID );
}

/**
 * Delete a client.
 *
 * @param string $type Client type.
 * @param int $id Client post ID.
 * @return bool True if delete, false otherwise.
 */
function rest_delete_client( $id ) {
	$post = get_post( $id );
	if ( empty( $id ) || empty( $post ) || $post->post_type !== 'json_consumer' ) {
		return false;
	}

	return (bool) wp_delete_post( $id, true );
}
