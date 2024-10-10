<?php

class Rogershood_Points {

	protected $user_id;
	protected $current_points = 0;
	protected $total_points = 0;
	protected $redeemed_points = 0;

	public function __construct( $user_id = null ) {
		// If user_id is passed, initialize the user's points data
		if ( $user_id ) {
			$this->find( $user_id );
		}
	}

	public function find( $user_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT * FROM " . USER_POINTS_TABLE . " WHERE user_id = %d", array( $user_id ) );
		$row = $wpdb->get_row( $sql );

		// Assign properties based on the query result or set defaults
		if ( $row ) {
			$this->user_id         = $row->user_id;
			$this->current_points  = $row->current_points;
			$this->total_points    = $row->total_points;
			$this->redeemed_points = $row->redeemed_points;
		} else {
			$this->user_id = $user_id;
		}

		return $this;
	}

	public function get_current_points() {
		return $this->current_points;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function get_total_points() {
		return $this->total_points;
	}

	public function get_redeemed_points() {
		return $this->redeemed_points;
	}

	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
	}

	public function set_current_points( $current_points ) {
		$this->current_points = $current_points;
	}

	public function set_total_points( $total_points ) {
		$this->total_points = $total_points;
	}

	public function set_redeemed_points( $redeemed_points ) {
		$this->redeemed_points = $redeemed_points;
	}

	public function earn_points( $points ) {
		$this->current_points += $points;
		$this->total_points   += $points;

		return $this->save();
	}

	public function reverse_redeem_points( $points ) {
		$this->current_points  += $points;
		$this->redeemed_points -= $points;

		return $this->save();
	}

	public function redeem_points( $points ) {
		if ( $this->current_points < $points ) {
			return false;
		}

		$this->current_points  -= $points;
		$this->redeemed_points += $points;

		return $this->save();
	}

	public function reverse_earned_points( $points ) {

		$this->current_points -= $points;
		$this->total_points   -= $points;

		return $this->save();
	}

	public function save() {
		global $wpdb;

		// Attempt to update the row
		$updated = $wpdb->update(
			USER_POINTS_TABLE,
			array(
				'current_points'  => $this->current_points,
				'total_points'    => $this->total_points,
				'redeemed_points' => $this->redeemed_points,
			),
			array( 'user_id' => $this->user_id ),
			array( '%d', '%d', '%d' ),
			array( '%d' )
		);

		// If the update was not successful, insert a new row
		if ( ! $updated ) {
			// We use 'replace' instead of 'insert' to avoid duplicate entries.
			$wpdb->replace(
				USER_POINTS_TABLE,
				array(
					'user_id'         => $this->user_id,
					'current_points'  => $this->current_points,
					'total_points'    => $this->total_points,
					'redeemed_points' => $this->redeemed_points,
				),
				array( '%d', '%d', '%d', '%d' )
			);
		}

		return true;
	}

}
