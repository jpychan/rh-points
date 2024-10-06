<?php

class Rogershood_Points_Checkout {

	public function __construct() {
	}

	public function init() {

		// enqueue script
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// cart
		add_action( 'woocommerce_before_cart', array( $this, 'show_potential_points' ), 10 );
		add_action( 'woocommerce_after_cart_table', array( $this, 'redeem_points_form' ), 100 );

		// checkout
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'redeem_points_form' ), 10 );

		add_action( 'wc_ajax_apply_points_discount', array( $this, 'apply_points_discount' ), 10 );
		add_action( 'wc_ajax_remove_points_discount', array( $this, 'remove_points_discount' ), 10 );

		add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'add_remove_link_to_redeemed_points' ), 10, 2 );

		add_action( 'woocommerce_cart_updated', array( $this, 'check_cart_amount_and_update_points' ), 10 );
		add_action( 'woocommerce_applied_coupon', array( $this, 'check_cart_amount_and_update_points' ), 10 );

		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_points_discount_to_cart' ), 10 );

		// validate checkout
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_points_during_checkout' ), 10 );

		// after checkout
		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_points_discount_line_item' ), 10 );

		// thank you page
		add_action( 'woocommerce_before_thankyou', array( $this, 'show_earned_points_on_thankyou_page' ), 20, 1 );

	}

	public function enqueue_scripts() {
		if ( is_checkout() || is_cart() ) {
			wp_enqueue_script( 'rogershood-points-js', RH_POINTS_PLUGIN_DIR . 'public/assets/rogershood-points.js', array( 'jquery' ), '1.0', true );
			wp_localize_script( 'rogershood-points-js', 'rogershood_points', array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'redeem_points_nonce' => wp_create_nonce( 'redeem_points_nonce' ),
				'remove_points_nonce' => wp_create_nonce( 'remove_points_nonce' )
			) );
		}
	}

	public function show_potential_points() {

		$default_currency           = get_option( 'woocommerce_currency' ); // Should return 'USD'
		$current_currency           = get_woocommerce_currency();
		$required_points_per_dollar = rh_get_points_per_dollar_redeemed();

		$settings          = get_option( 'rogershood_points_settings' );
		$points_per_dollar = $settings['points_earned_per_dollar'] ?? 1;

		if ( $default_currency === $current_currency ) {
			$redeemed_points = intval( WC()->session->get( 'redeemed_points' ) );
			$total_discount  = $redeemed_points ? $redeemed_points / $required_points_per_dollar : 0;
			$total           = WC()->cart->get_cart_contents_total() - $total_discount;
			$total_points    = $total * $points_per_dollar;
		} else {
			// get cart total
			// loop through each cart item and get the price
			$products          = WC()->cart->get_cart_contents();
			$cart_total_in_usd = 0;
			foreach ( $products as $product ) {
				$product_id        = $product['product_id'];
				$product           = wc_get_product( $product_id );
				$price             = $product->get_data()['price'];
				$cart_total_in_usd += $price;
			}

			// get coupon
			$coupons               = WC()->cart->get_applied_coupons();
			$total_discount_in_usd = 0;
			foreach ( $coupons as $coupon ) {
				$coupon                = new WC_Coupon( $coupon );
				$total_discount_in_usd += $coupon->get_discount_amount( $cart_total_in_usd );
			}

			$redeemed_points          = intval( WC()->session->get( 'redeemed_points' ) );
			$redeemed_points_discount = $redeemed_points ? $redeemed_points / $required_points_per_dollar : 0;
			$total_after_discount     = $cart_total_in_usd - $total_discount_in_usd - $redeemed_points_discount;

			$total_points = $total_after_discount * $points_per_dollar;
		}

		echo "<p>You could earn $total_points points with this order.</p>";

	}


	public function add_remove_link_to_redeemed_points( $cart_totals_fee_html, $fee ) {

		if ( $fee->id = 'redeemed-points' ) {
			$cart_totals_fee_html .= ' [<a href="javascript:void(0)" id="remove_points_discount">Remove</a>]';
		}

		return $cart_totals_fee_html;
	}

	public function calculate_cart_total_in_usd() {

		$default_currency = get_option( 'woocommerce_currency' ); // Should return 'USD'
		$current_currency = get_woocommerce_currency();

		// If the current currency is already USD, just display the cart total
		if ( $current_currency === $default_currency ) {
			return WC()->cart->get_total();
		}

		// Loop through each cart item and get the price in the store's default currency (USD)
		$total_in_usd = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'];
			$product    = wc_get_product( $product_id );

			// Get the product price in USD (the stored price)
			$price_in_usd = $product->get_data()['price'];

			// Add the price to the total, considering quantity
			$total_in_usd += $price_in_usd * $cart_item['quantity'];
		}

		return $total_in_usd;

	}

	public function redeem_points_form() {

		$cart_total = WC()->cart->get_cart_contents_total();
		// skip if user is not logged in
		// skip if order total is 0
		if ( ! is_user_logged_in() || $cart_total == 0 ) {
			return;
		}

		$user_id                    = get_current_user_id();
		$points_instance            = new Rogershood_Points( $user_id );
		$user_points                = $points_instance->get_current_points();
		$required_points_per_dollar = rh_get_points_per_dollar_redeemed();

		// skip if user has less than 500 points
		if ( $user_points < $required_points_per_dollar ) {
			return;
		}

		// ok, so if they have already redeemed points, but added items to the cart, they need to be able to redeem MORE points
		// Redeemed points
		// - order is already free
		// - order is not free
		// Have not redeemed points yet

		$redeemed_points       = WC()->session->get( 'redeemed_points', 0 );
		$remaining_user_points = $user_points - $redeemed_points;

		if ( $redeemed_points > 0 && $remaining_user_points > 0 ) {
			$redeemed_points_amount = $redeemed_points / $required_points_per_dollar;
			$cart_total             = $cart_total - $redeemed_points_amount;
		}

		$max_redeemable_points = $required_points_per_dollar * $cart_total;

		if ( $user_points > $max_redeemable_points ) {
			$redeemable_points = $max_redeemable_points;
		} else {
			$redeemable_points = $user_points;
		}

		$discount = rh_calculate_discount_from_points( $redeemable_points );

		$button_text = 'Redeem ' . $redeemable_points . ' Points for $' . $discount . ' off';
		$style       = $redeemable_points > 0 ? '' : 'display:none';

		?>
        <div id="redeem-points-container" style="<?php echo $style ?>">
            <button type="button" class="button" id="redeem-points-button" data-user-points="<?php echo $user_points ?>"
                    data-points-to-redeem="<?php echo $redeemable_points ?>"><?php echo $button_text ?></button>
            <div id="points-message"></div>
        </div>

		<?php
	}

	public function apply_points_discount() {

		// Check if the user is logged in
		if ( ! is_user_logged_in() ) {
			wc_add_notice( 'You need to be logged in to redeem points.', 'error' );
			wp_send_json_error( array( 'message' => 'You need to be logged in to redeem points.' ) );

			return;
		}

		$redeemed_points = WC()->session->get( 'redeemed_points', 0 );

		$user_id          = get_current_user_id();
		$points_instance  = new Rogershood_Points( $user_id );
		$user_points      = $points_instance->get_current_points();
		$points_to_redeem = $_POST['points'];

		if ( $user_points <= 0 ) {
			wc_add_notice( 'You have no points to redeem.', 'error' );
			wp_send_json_error( array( 'message' => 'You have no points to redeem' ) );

			return;
		} else if ( $points_to_redeem > $user_points ) {
			wc_add_notice( 'You do not have enough points to redeem.', 'error' );
			wp_send_json_error( array( 'message' => 'You do not have enough points to redeem.' ) );

			return;
		}

		$cart_total = WC()->cart->get_cart_contents_total();

		$settings                        = get_option( 'rogershood_points_settings' );
		$required_points_per_dollar      = $settings['points_per_dollar_redeemed'] ?? 500;
		$total_points_required_for_order = $cart_total * $required_points_per_dollar;

		// if user has already redeemed pointss
		if ( $redeemed_points > 0 ) {
			$existing_discount = rh_calculate_discount_from_points( $redeemed_points );
			$cart_total        = $cart_total - $existing_discount;

			$new_discount = rh_calculate_discount_from_points( $points_to_redeem );

			if ( $new_discount <= $cart_total ) {
				$total_redeemed_points = $points_to_redeem + $redeemed_points;
			} else {
				$total_redeemed_points = $total_points_required_for_order;
			}

			WC()->session->set( 'redeemed_points', $total_redeemed_points );

		} else {

			if ( $total_points_required_for_order < $points_to_redeem ) {
				$points_to_redeem = $total_points_required_for_order;
			}

			WC()->session->set( 'redeemed_points', $points_to_redeem );
		}

		// get the cart content total
		wc_add_notice( 'Points successfully applied as a discount.' );
		wp_send_json_success( array( 'message' => __( 'Points applied successfully!', 'woocommerce' ) ) );

	}

	public function remove_points_discount() {
		// Remove the points discount (assumed to be a fee or coupon)
		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
			if ( 'redeemed-points' === $fee->id ) {
				WC()->cart->remove_fee( $fee_key );
			}
		}

		// Optionally, reset the session data for redeemed points
		WC()->session->set( 'redeemed_points', 0 );

		// Recalculate cart totals
		WC()->cart->calculate_totals();

		// Return a success response
		wc_add_notice( 'Points discount removed.', 'success' );
		wp_send_json_success( array( 'message' => __( 'Points discount removed.', 'woocommerce' ) ) );
	}

	public function apply_points_discount_to_cart() {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$points          = WC()->session->get( 'redeemed_points' );
		$discount_amount = rh_calculate_discount_from_points( $points );

		if ( $discount_amount > 0 ) {
			WC()->cart->add_fee( 'Redeemed Points', - $discount_amount, false, '' );
		}
	}

	public function add_points_discount_line_item( $order ) {
		$points = WC()->session->get( 'redeemed_points' );

		if ( $points > 0 ) {
			$discount_amount = rh_calculate_discount_from_points( $points );

			if ( $discount_amount > $order->get_total() ) {
				$discount_amount = $order->get_total();
				$order->update_meta_data( 'recalculate_redeemed_points', true );
			}

			if ( $discount_amount > 0 ) {
				// Add a discount line item to the order
				$order->add_item( new WC_Order_Item_Fee( array(
					'name'    => 'Discount from Redeemed Points',
					'amount'  => - $discount_amount,
					'taxable' => false,
				) ) );
			}
		}

		// add to order meta
		$order->update_meta_data( 'redeemed_points', $points );
	}

	function validate_points_during_checkout() {

		$redeemed_points = WC()->session->get( 'redeemed_points', 0 );

		if ( $redeemed_points > 0 ) {
			if ( ! is_user_logged_in() ) {
				wc_add_notice( 'You must be logged in to redeem points.', 'error' );
				WC()->session->set( 'redeemed_points', 0 );

				return;
			}

			$user_id         = get_current_user_id();
			$points_instance = new Rogershood_Points( $user_id );
			$user_points     = $points_instance->get_current_points();

			$redeemed_points = WC()->session->get( 'redeemed_points', 0 );

			if ( $redeemed_points > $user_points ) {
				wc_add_notice( 'You do not have ' . $redeemed_points . ' points to redeem. Please try again.', 'error' );
				// remove the redeemed points from the session
				WC()->session->set( 'redeemed_points', 0 );

				return;
			}
		}

	}

	// if cart items are free items, remove points
	public function check_cart_amount_and_update_points() {

		$redeemed_points = WC()->session->get( 'redeemed_points', 0 );

		if ( empty( $redeemed_points ) ) {
			return;
		}

		// Get the cart items
		$cart            = WC()->cart->get_cart();
		$only_free_items = true;

		// Check if all the items in the cart are free (e.g., price is 0 or ebooks)
		foreach ( $cart as $cart_item ) {
			$product       = wc_get_product( $cart_item['product_id'] );
			$product_price = $product->get_price();

			// If any item has a price greater than 0, then it's not only free items
			if ( $product_price > 0 ) {
				$only_free_items = false;
				break;
			}
		}

		// If only free items are in the cart, remove the points discount
		if ( $only_free_items ) {
			// Remove the applied points discount (this depends on how you applied the discount, e.g., via a fee or coupon)
			foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
				if ( 'Redeemed Points' === $fee->name ) {
					WC()->session->set( 'redeemed_points', 0 );
				}
			}
		} else {

			$settings                   = get_option( 'rogershood_points_settings' );
			$required_points_per_dollar = $settings['points_per_dollar_redeemed'] ?? 500;
			$total_points_required      = WC()->cart->get_cart_contents_total() * $required_points_per_dollar;
			if ( $redeemed_points > $total_points_required ) {
				WC()->session->set( 'redeemed_points', $total_points_required );
			}
		}
	}

	public function show_earned_points_on_thankyou_page( $order_id ) {

		$transactions = Rogershood_Points_Transaction::find_by( array( 'order_id'         => $order_id,
		                                                               'transaction_type' => 'credit'
		), 1, 0, 'transaction_date', 'DESC' );

		if ( ! empty( $transactions ) ) {
			$points = $transactions[0]->get_points();

			$points_instance = new Rogershood_Points( get_current_user_id() );

			if ( $points > 0 ) {
				echo '<p>Congratulations! You have earned ' . $points . ' points with this order. You have a total of ' . $points_instance->get_current_points() . ' points.</p>';
			}
		}
	}
}


