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