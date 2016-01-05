<?php

class WP_REST_OAuth1_ListTable extends WP_List_Table {
	public function prepare_items() {
		$paged = $this->get_pagenum();

		$args = array(
			'post_type' => 'json_consumer',
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key' => 'type',
					'value' => 'oauth1',
				),
			),

			'paged' => $paged,
		);

		$query = new WP_Query();
		$this->items = $query->query( $args );
	}

	/**
	 * Get a list of columns for the list table.
	 *
	 * @since  3.1.0
	 * @access public
	 *
	 * @return array Array in which the key is the ID of the column,
	 *               and the value is the description.
	 */
	public function get_columns() {
		$c = array(
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Name', 'rest_oauth1' ),
			'description' => __( 'Description', 'rest_oauth1' ),
		);

		return $c;
	}

	public function column_cb( $item ) {
		?>
		<label class="screen-reader-text"
			for="cb-select-<?php echo esc_attr( $item->ID ) ?>"><?php esc_html_e( 'Select consumer', 'rest_oauth1' ); ?></label>

		<input id="cb-select-<?php echo esc_attr( $item->ID ) ?>" type="checkbox"
			name="consumers[]" value="<?php echo esc_attr( $item->ID ) ?>" />

		<?php
	}

	protected function column_name( $item ) {
		$title = get_the_title( $item->ID );
		if ( empty( $title ) ) {
			$title = '<em>' . esc_html__( 'Untitled', 'rest_oauth1' ) . '</em>';
		}

		$edit_link = add_query_arg(
			array(
				'page'   => 'rest-oauth1-apps',
				'action' => 'edit',
				'id'     => $item->ID,
			),
			admin_url( 'users.php' )
		);
		$delete_link = add_query_arg(
			array(
				'page'   => 'rest-oauth1-apps',
				'action' => 'delete',
				'id'     => $item->ID,
			),
			admin_url( 'users.php' )
		);
		$delete_link = wp_nonce_url( $delete_link, 'rest-oauth1-delete:' . $item->ID );

		$actions = array(
			'edit' => sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html__( 'Edit', 'rest_oauth1' ) ),
			'delete' => sprintf( '<a href="%s">%s</a>', esc_url( $delete_link ), esc_html__( 'Delete', 'rest_oauth1' ) ),
		);
		$action_html = $this->row_actions( $actions );

		return $title . ' ' . $action_html;
	}

	protected function column_description( $item ) {
		return $item->post_content;
	}
}
