<?php

function rh_calculate_points( $subtotal ) {
	$points_settings            = get_option( 'rogershood_points_settings' );
	$points_per_dollar_redeemed = $points_settings['points_earned_per_dollar'] ?? 1;

	return floor( $subtotal ) * $points_per_dollar_redeemed;
}

function rh_calculate_discount_from_points( $points ) {

	$points_per_dollar_redeemed = rh_get_points_per_dollar_redeemed();

	if ( class_exists( 'WC_Payments_Multi_Currency' ) ) {
		$currency = WC_Payments_Multi_Currency();
		$price    = $points / $points_per_dollar_redeemed;
		$price    = $currency->get_price( $price, 'coupon' );

		return round( $price, 2 );
	} else {
		return $points / $points_per_dollar_redeemed;
	}
}

function rh_get_points_per_dollar_redeemed() {
	$points_settings = get_option( 'rogershood_points_settings' );

	return $points_settings['points_per_dollar_redeemed'] ?? 1;
}

function rh_get_potential_points($product) {

	// if product is class wc_product
	if ( is_a( $product, 'WC_Product' ) ) {
		$settings          = get_option( 'rogershood_points_settings' );
		$points_per_dollar = $settings['points_earned_per_dollar'] ?? 1;

		return $product->get_data()['price'] * $points_per_dollar;
	}

	return 0;

}