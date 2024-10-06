<?php

class Rogershood_Points_Registration {

	public function __construct() {
	}

	public function init() {
		add_action( 'user_register', array( $this, 'award_points_for_registration' ), 10, 1 );
	}

	/**
	 * Award points when a user registers.
	 *
	 * @param int $user_id The ID of the newly registered user.
	 */
	public function award_points_for_registration( $user_id ) {
		$points_settings    = get_option( 'rogershood_points_settings' );
		$points_upon_signup = $points_settings['points_upon_signup'] ?? 250;

		// Add points to the user's account
		$points_instance = new Rogershood_Points( $user_id );
		$points_instance->earn_points( $points_upon_signup );

		// Log the transaction

		$transaction = new Rogershood_Points_Transaction( $user_id, $points_upon_signup, 'credit', 'signup' );
		$transaction->save();
	}
}
