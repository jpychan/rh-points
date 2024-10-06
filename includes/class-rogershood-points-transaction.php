<?php

class Rogershood_Points_Transaction {

	protected $id;
	protected $user_id;
	protected $points;
	protected $transaction_type;
	protected $action_type;
	protected $order_id;
	protected $transaction_date;
	protected $table_name;

	public function __construct( $user_id = null, $points = null, $transaction_type = null, $action_type = null, $order_id = null ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'rogershood_points_transactions';

		$this->user_id          = $user_id;
		$this->points           = $points;
		$this->transaction_type = $transaction_type;
		$this->action_type      = $action_type;
		$this->order_id         = $order_id;
		$this->transaction_date = current_time( 'mysql' );
	}

	public static function find( $id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rogershood_points_transactions';

		$sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id );
		$row = $wpdb->get_row( $sql, ARRAY_A );

		// If found, return a new instance populated with the data
		if ( $row ) {
			$instance = new self(
				$row['user_id'],
				$row['points'],
				$row['transaction_type'],
				$row['action_type'],
				$row['order_id'],
			);

			$instance->set_transaction_date( $row['transaction_date'] );
			$instance->set_id( $row['id'] );

			return $instance;
		}

		// Return null if no transaction is found
		return null;
	}

	public function save() {
		global $wpdb;

		$data = array(
			'user_id'          => $this->user_id,
			'points'           => $this->points,
			'transaction_type' => $this->transaction_type,
			'action_type'      => $this->action_type,
			'transaction_date' => $this->transaction_date,
			'order_id'         => $this->order_id
		);

		if ( $this->id ) {
			// Update existing transaction
			$wpdb->update( $this->table_name, $data, array( 'id' => $this->id ), array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d'
			), array( '%d' ) );
		} else {
			// Insert new transaction
			$wpdb->insert( $this->table_name, $data, array( '%d', '%d', '%s', '%s', '%s', '%d' ) );

			$this->id = $wpdb->insert_id;
		}
	}

	public function delete() {
		if ( $this->id ) {
			global $wpdb;
			$wpdb->delete( $this->table_name, array( 'id' => $this->id ), array( '%d' ) );
		}
	}

	public static function find_by( $conditions, $limit = 10, $offset = 0, $orderby = '', $order = 'ASC' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rogershood_points_transactions';

		$query  = "SELECT * FROM {$table_name} WHERE 1=1";
		$values = [];

		foreach ( $conditions as $column => $value ) {
			$query    .= " AND {$column} = %s";
			$values[] = $value;
		}

		if ( ! empty( $orderby ) ) {
			$query .= " ORDER BY $orderby $order";
		}

		if ( $limit > - 1 ) {
			$query .= " LIMIT %d OFFSET %d";
		}

		$values[] = $limit;
		$values[] = $offset;

		// Prepare the query with the accumulated values
		$prepared_query = $wpdb->prepare( $query, ...$values );

		$results = $wpdb->get_results( $prepared_query );

		$transactions = array();

		foreach ( $results as $row ) {
			$transaction = new Rogershood_Points_Transaction();

			// Populate the review object with data from $row
			foreach ( $row as $column => $value ) {
				if ( property_exists( $transaction, $column ) ) {
					$transaction->$column = $value;
				}
			}

			$transactions[] = $transaction;
		}

		return $transactions;
	}


	// Getter methods
	public function get_id() {
		return $this->id;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function get_points() {
		return $this->points;
	}

	public function get_transaction_type() {
		return $this->transaction_type;
	}

	public function get_action_type() {
		return $this->action_type;
	}

	public function get_transaction_date() {
		return $this->transaction_date;
	}

	public function get_order_id() {
		return $this->order_id;
	}

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
	}

	public function set_points( $points ) {
		$this->points = $points;
	}

	public function set_transaction_type( $transaction_type ) {
		$this->transaction_type = $transaction_type;
	}

	public function set_action_type( $action_type ) {
		$this->action_type = $action_type;
	}

	public function set_transaction_date( $transaction_date ) {
		$this->transaction_date = $transaction_date;
	}

	public function set_order_id( $order_id ) {
		$this->order_id = $order_id;
	}

	public function get_transaction_action_label() {
		return match ( $this->get_action_type() ) {
			'admin_adjustment' => 'Adjusted by Admin',
			'signup' => 'Earned for Signup',
			'referral' => 'Earned for Referral',
			'redeem_point' => 'Redeemed Points',
			'point_for_purchase' => 'Earned for Purchase',
			'deduct_for_refund' => 'Deducted for Refund',
			'restore_points' => 'Restored Redeemed Points for Refund',
			'revoke_coupon' => 'Revoked Coupon for Points',
			'followup_share' => 'Earned for Following Social Media Account',
			'restore_unused_coupon' => 'Restored Points from Unused Coupon',
			default => $this->get_transaction_type(),
		};
	}
}
