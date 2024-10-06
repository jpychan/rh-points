<?php

class Rogershood_Points_Shop {

	public function __construct() {
	}

	public function init() {
		add_action( 'woocommerce_after_shop_loop_item_title', array(
			$this,
			'show_potential_points_after_product_title'
		), 11 );
	}

	public function show_potential_points_after_product_title() {
		global $product;

		$settings = get_option( 'rogershood_points_settings' );
		$points_per_dollar = $settings['points_earned_per_dollar'] ?? 1;

		$points = $product->get_data()['price'] * $points_per_dollar;

		if ( $points > 0 ) {
			echo "Purchase & earn $points points!";
		}
	}

}
