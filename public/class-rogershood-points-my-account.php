<?php

class Rogershood_Points_My_Account {

	public function __construct() {
	}

	public function init() {
		add_action( 'init', array( $this, 'user_points_endpoint' ) );
		add_action( 'query_vars', array( $this, 'user_points_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_user_points_to_menu' ) );
		add_action( 'woocommerce_account_user-points_endpoint', array( $this, 'user_points_content' ) );

	}

	public function user_points_endpoint() {
		add_rewrite_endpoint( 'user-points', EP_ROOT | EP_PAGES );
	}

	public function user_points_query_vars( $vars ) {
		$vars[] = 'user-points';

		return $vars;
	}

	public function add_user_points_to_menu( $items ) {
		$items['user-points'] = 'Points';

		return $items;
	}

	public function user_points_content() {
		$points            = new Rogershood_Points( get_current_user_id() );
		$transactions      = Rogershood_Points_Transaction::find_by( array( 'user_id' => get_current_user_id() ), 10, 0, 'transaction_date', 'DESC' );
		$current_discount  = rh_calculate_discount_from_points( $points->get_current_points() );
		$redeemed_discount = rh_calculate_discount_from_points( $points->get_redeemed_points() );
		$total_discount    = rh_calculate_discount_from_points( $points->get_total_points() );
		?>
        <style>
            .user-points-balances {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                grid-gap: 20px;
            }

            .user-points-balance__available,
            .user-points-balance__redeemed {
                border-right: 1px solid #333;
            }

            .user-points-balance__number {
                font-size: 1.5em;
                font-weight: bold;
            }

            .user-points-transaction {
                display: grid;
                grid-template-columns: 8rem 5rem 1fr;
                grid-column-gap: 1rem;
            }
        </style>
        <h2>Points Balance</h2>
        <div class="user-points-balances">

            <div class="user-points-balance__available">
                <p class="user-points-balance__heading">Available Points</p>
                <p class="user-points-balance__number"><?php echo $points->get_current_points(); ?></p>
                <p class="">= <?php echo '$' . number_format( $current_discount, 2 ); ?></p>
            </div>
            <div class="user-points-balance__redeemed">
                <p class="user-points-balance__heading">Redeemed Points</p>
                <p class="user-points-balance__number"><?php echo $points->get_redeemed_points(); ?></p>
                <p class="">= <?php echo '$' . number_format( $redeemed_discount, 2 ); ?></p>
            </div>
            <div class="user-points-balance__total">
                <p class="user-points-balance__heading">Total Points</p>
                <p class="user-points-balance__number"><?php echo $points->get_total_points(); ?></p>
                <p class="">= <?php echo '$' . number_format( $total_discount, 2 ); ?></p>

            </div>
        </div>
        <div class="user-points-transactions">
            <h2>Points Transaction History</h2>
			<?php
			foreach ( $transactions as $transaction ) {
				// change from MYSQL date to Sep 25, 2024
				$date_string    = strtotime( $transaction->get_transaction_date() );
				$date_formatted = date( 'M j, Y', $date_string );
				$points_string  = $transaction->get_transaction_type() === 'credit' ? $transaction->get_points() : '-' . $transaction->get_points();
				$action_type    = $transaction->get_action_type();
				$action_string  = $transaction->get_transaction_action_label();

				if ( $action_type !== 'admin_adjustment' && $action_type !== 'signup' && $action_type !== 'referral' ) {
					$order_id = $transaction->get_order_id();
					$order    = wc_get_order( $order_id );

					if ( $order instanceof WC_Order ) {
						$order_url     = $order->get_view_order_url();
						$action_string .= " (Order <a href='{$order_url}'>#{$order_id}</a>)";
					}
				}

				?>
                <div class="user-points-transaction">
                    <p class="user-points-transaction__date"><?php echo $date_formatted; ?></p>
                    <p class="user-points-transaction__points"><?php echo $points_string ?></p>
                    <p class="user-points-transaction__action"><?php echo $action_string; ?></p>
                </div>
				<?php
			}
			?>

        </div>
		<?php
	}

}