<?php

class Rogershood_Points_Order {
	public function __construct() {
	}

	public function init() {

		// Add earned points for completed orders
		add_action( 'woocommerce_order_status_processing', array( $this, 'add_points_for_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'add_points_for_order' ), 10, 1 );

		// Remove earned points for cancelled and refunded orders
		add_action( 'woocommerce_order_status_cancelled', array(
			$this,
			'deduct_earned_points_for_refunded_order'
		), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array(
			$this,
			'deduct_earned_points_for_refunded_order'
		), 10, 1 );

		// Deduct redeemed points for orders
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'deduct_redeemed_points' ), 10, 1 );

		// Add points for cancelled and refunded orders
		add_action( 'woocommerce_order_status_cancelled', array(
			$this,
			'restore_redeemed_points_for_refunded_order'
		), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array(
			$this,
			'restore_redeemed_points_for_refunded_order'
		), 10, 1 );


	}

	public function deduct_redeemed_points( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_total() == 0 ) {
			return;
		}

		// Check if the user already received points for this order
		$transactions = Rogershood_Points_Transaction::find_by( array( 'order_id' => $order_id ), 1, 0, 'transaction_date', 'DESC' );

		if ( ! empty( $transactions ) && $transactions[0]->get_transaction_type() == 'debit' ) {
			return;
		}

		$redeemed_points = $order->get_meta( 'redeemed_points' );
		$user_id         = $order->get_user_id();
		$user            = get_userdata( $user_id );

		if ( empty( $redeemed_points ) || empty( $user ) ) {
			return;
		}

		// Deduct the redeemed points from the user's account
		$points_instance = new Rogershood_Points( $user_id );
		$points_instance->redeem_points( $redeemed_points );

		// Log the transaction
		$transaction = new Rogershood_Points_Transaction($user_id, $redeemed_points, 'debit', 'redeem_point', $order_id);
		$transaction->save();

		WC()->session->set( 'redeemed_points', 0 );

	}

	public function restore_redeemed_points_for_refunded_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id         = $order->get_user_id();
		$redeemed_points = $order->get_meta( 'redeemed_points' );

		if ( empty( $redeemed_points ) ) {
			return;
		}

		// Find the points transaction associated with this order
		$transactions = Rogershood_Points_Transaction::find_by( array( 'order_id' => $order_id, 'action_type' => 'redeem_point' ), 1, 0, 'transaction_date', 'DESC' );

		if ( ! empty( $transactions ) && $transactions[0]->get_transaction_type() == 'credit' ) {
			return;
		}

		// Add the redeemed points back to the user's account
		$points_instance = new Rogershood_Points( $user_id );
		$points_instance->reverse_redeem_points( $redeemed_points );

		// Log the transaction
		$transaction = new Rogershood_Points_Transaction($user_id, $redeemed_points, 'credit', 'restore_points', $order_id);
		$transaction->save();
	}

	public function add_points_for_order( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order   = wc_get_order( $order_id );
		$user_id = $order->get_user_id();

		if ( ! $user_id || $order->get_subtotal() == 0 ) {
			$order->update_meta_data( 'earned_points', 0 );

			return;
		}

		// Check if the user already received points for this order
		$transactions = Rogershood_Points_Transaction::find_by( array( 'order_id' => $order_id ), 1, 0, 'transaction_date', 'DESC' );

		if ( ! empty( $transactions ) && $transactions[0]->get_transaction_type() == 'credit' ) {
			return;
		}

		// Calculate points based on the order subtotal
		$order_total_before_shipping = $order->get_total() - $order->get_shipping_total();

		if ( $order->get_currency() !== 'USD' ) {
			$exchange_rate = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true );
			if ( ! empty( $exchange_rate ) ) {
				$order_total_before_shipping = number_format( $order_total_before_shipping / $exchange_rate, 2 );
			}
		}

		$earned_points = rh_calculate_points( $order_total_before_shipping );

		if ( $earned_points > 0 ) {
			// Add points to the user's account
			$points_instance = new Rogershood_Points( $user_id );
			$points_instance->earn_points( $earned_points );

			// Log the transaction with the order ID
			$transaction = new Rogershood_Points_Transaction($user_id, $earned_points, 'credit', 'point_for_purchase', $order_id);
			$transaction->save();

			$order->update_meta_data( 'earned_points', $earned_points );
			$order->save();
		}
	}

	function deduct_earned_points_for_refunded_order( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order         = wc_get_order( $order_id );
		$user_id       = $order->get_user_id();
		$earned_points = $order->get_meta( 'earned_points' );

		if ( ! $user_id || empty($earned_points) ) {
			return;
		}

		// Find the points transaction associated with this order
		$transactions = Rogershood_Points_Transaction::find_by( array(
			'order_id'    => $order_id,
			'action_type' => 'point_for_purchase'
		), 1, 0, 'transaction_date', 'DESC' );

		if ( ! empty( $transactions ) && $transactions[0]->get_transaction_type == 'debit' ) {
			return;
		}

		// Deduct points from the user's account
		$user_points     = new Rogershood_Points( $user_id );
		$points_deducted = $transactions[0]->get_points(); // Points to be deducted
		$user_points->reverse_earned_points( $points_deducted, false );

		// Log the deduction transaction
		$transaction = new Rogershood_Points_Transaction( $user_id, $points_deducted, 'debit', 'deduct_for_refund', $order_id );
		$transaction->save();
	}

}
