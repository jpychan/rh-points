<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Rogershood_Points_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'User Point', 'sp' ),
			'plural'   => __( 'User Points', 'sp' ),
			'ajax'     => false
		) );
	}

	public function get_columns() {
		$columns = array(
			'user_id'         => __( 'User ID', 'sp' ),
			'user_email' => __( 'Email', 'sp' ),
			'username'    => __( 'User Name', 'sp' ),
			'current_points'  => __( 'Current Points', 'sp' ),
			'total_points'    => __( 'Total Points', 'sp' ),
			'redeemed_points' => __( 'Redeemed Points', 'sp' ),
		);

		return $columns;
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'user_id':
				return esc_html( $item['ID'] );
			case 'user_email':
				return esc_html( $item['user_email'] );
			case 'username':
				return esc_html( $item['user_nicename'] );
			case 'current_points':
				return $item['current_points'] ? esc_html( $item['current_points'] ) : 0;
			case 'total_points':
				return $item['total_points'] ? esc_html( $item['total_points'] ) : 0;
			case 'redeemed_points':
				return $item['redeemed_points'] ? esc_html( $item['redeemed_points'] ) : 0;
			default:
				return ''; // or display an error for unknown columns
		}
	}

	public function prepare_items() {
		$per_page     = $this->get_items_per_page( 'user_points_per_page', 10 );
		$current_page = $this->get_pagenum();
		$columns      = $this->get_columns();

		// Handle sorting
		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'user_id';
		$order   = ! empty( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'asc';

		// Fetch the points by the specified column and direction
		global $wpdb;

		$table_name  = $wpdb->prefix . 'rogershood_user_points';
		$query       = "SELECT * FROM $wpdb->users LEFT JOIN $table_name ON $table_name.user_id = $wpdb->users.ID";
		$count_query = "SELECT COUNT(*) FROM $wpdb->users";

		if ( isset( $_REQUEST['s'] ) ) {
			// search by user_nicename and user email
			$search_query = $wpdb->prepare( " WHERE user_nicename LIKE %s OR user_email LIKE %s", '%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%', '%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%' );
			$query        .= $search_query;
			$count_query  .= $search_query;
		}

		$query   .= $wpdb->prepare( " ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, ( $current_page - 1 ) * $per_page );
		$results = $wpdb->get_results( $query, 'ARRAY_A' );

		// Set the items to be displayed in the table
		$this->items = $results;

		// Get total number of items
		$total_items = $wpdb->get_var( $count_query );

		// Set pagination arguments
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );

		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$primary  = 'user_id';

		$this->_column_headers = array( $columns, $hidden, $sortable, $primary );
	}


	public function get_sortable_columns() {
		return array(
			'username'   => array( 'username', false ),
			'user_id'        => array( 'user_id', false ),
			'current_points' => array( 'current_points', false ),
		);
	}

	public function column_user_id( $item ) {

		$actions = array(
			'edit' => sprintf(
				'<a href="?page=rogershood_user_points_edit&user_id=%s">Edit</a>',
				absint( $item['ID'] ),
			),
		);

		return sprintf( '%1$s %2$s', $item['ID'], $this->row_actions( $actions ) );
	}


}
