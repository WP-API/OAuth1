<?php

class Wombat_ListTable extends WP_List_Table {
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
			'name'        => __( 'Name' ),
			'description' => __( 'Description' ),
		);

		return $c;
	}

	public function column_cb( $item ) {
		?>
		<label class="screen-reader-text"
			for="cb-select-<?php echo esc_attr( $item->ID ) ?>"><?php esc_html_e( 'Select consumer', 'wombat' ); ?></label>

		<input id="cb-select-<?php echo esc_attr( $item->ID ) ?>" type="checkbox"
			name="consumers[]" value="<?php echo $item->ID ?>" />

		<?php
	}

	protected function column_name( $item ) {
		$title = get_the_title( $item->ID );
		if ( empty( $title ) ) {
			$title = '<em>' . esc_html__( 'Untitled', 'wombat' ) . '</em>';
		}

		$edit_link = add_query_arg(
			array(
				'page'   => 'wombat-apps',
				'action' => 'edit',
				'id'     => $item->ID,
			),
			admin_url( 'users.php' )
		);
		$delete_link = add_query_arg(
			array(
				'page'   => 'wombat-apps',
				'action' => 'delete',
				'id'     => $item->ID,
			),
			admin_url( 'users.php' )
		);
		$delete_link = wp_nonce_url( $delete_link, 'wombat-delete:' . $item->ID );

		$actions = array(
			'edit' => sprintf( '<a href="%s">%s</a>', $edit_link, esc_html__( 'Edit', 'wombat' ) ),
			'delete' => sprintf( '<a href="%s">%s</a>', $delete_link, esc_html__( 'Delete', 'wombat' ) ),
		);
		$action_html = $this->row_actions( $actions );

		return $title . ' ' . $action_html;
	}

	protected function column_description( $item ) {
		return $item->post_content;
	}
}
