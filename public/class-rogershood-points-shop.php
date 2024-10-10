<?php

class Rogershood_Points_Shop {

	public function init() {
		add_action( 'woocommerce_after_shop_loop_item', array(
			$this,
			'show_potential_points_after_product_title'
		), 11 );
	}

	public function show_potential_points_after_product_title() {
		global $product;

		$points = rh_get_potential_points( $product );

		if ( $points > 0 ) {
			printf( 'Purchase & earn %s points!', esc_html( $points ) );
		}
	}

}
