<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class Rogershood_Points_Migrate_Command extends WP_CLI_Command {


	/**
	 * Migrate points and transactions from the WPLoyalty plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rogershood points migrate_all
	 *
	 * @subcommand migrate_all
	 */
	public function migrate_all() {

		WP_CLI::log( 'Migrating points...' );
//		$this->migrate_points();
		WP_CLI::log( 'Migrating transactions...' );
		$this->migrate_transactions();
	}

	/**
	 * Migrate points from the WPLoyalty plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rogershood points migrate points
	 *
	 * @subcommand migrate_points
	 */
	public function migrate_points() {
		global $wpdb;

		$wployalty_table = $wpdb->prefix . 'wlr_users';
		$user_table      = $wpdb->prefix . 'users';
		$batch_size      = 2000;
		$offset          = 0;

		// truncate the points table
		$wpdb->query( "TRUNCATE TABLE " . USER_POINTS_TABLE );

		do {
			// Fetch 2000 records at a time with the current offset
			$query = $wpdb->prepare(
				"SELECT u.ID, wu.points, wu.used_total_points, wu.earn_total_point FROM $user_table u 
                LEFT JOIN $wployalty_table wu ON wu.user_email = u.user_email 
                LIMIT %d OFFSET %d",
				$batch_size, $offset
			);

			$rows = $wpdb->get_results( $query, ARRAY_A );

			// Process the rows
			foreach ( $rows as $row ) {
				// Insert row into rogershood_user_points
				$wpdb->insert(
					USER_POINTS_TABLE,
					array(
						'user_id'         => $row['ID'],
						'current_points'  => $row['points'] ?? 0,
						'redeemed_points' => $row['used_total_points'] ?? 0,
						'total_points'    => $row['earn_total_point'] ?? 0,
					),
					array( '%d', '%d', '%d', '%d' ),
				);
			}

			// Increment the offset for the next batch
			$offset += $batch_size;

			// Output progress
			WP_CLI::success( "Processed batch with offset: $offset" );

		} while ( count( $rows ) > 0 );

		WP_CLI::success( 'Migration of points completed.' );
	}

	/**
	 * Migrate points transactions from the WPLoyalty plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rogershood points migrate_transactions
	 *
	 * @subcommand migrate_transactions
	 */
	public function migrate_transactions() {
		global $wpdb;

		$wployalty_table    = $wpdb->prefix . 'wlr_earn_campaign_transaction';
		$user_table         = $wpdb->prefix . 'users';
		$batch_size         = 2000;
		$offset             = 0;

		// truncate transactions table
		$wpdb->query( "TRUNCATE TABLE " . USER_POINTS_TRANSACTIONS_TABLE );

		do {
			// Fetch 2000 records at a time with the current offset
			$query = $wpdb->prepare(
				"SELECT u.ID, wc.action_type, wc.transaction_type, wc.order_id, wc.points, wc.created_at 
                FROM $wployalty_table wc 
                JOIN $user_table u ON wc.user_email = u.user_email 
                LIMIT %d OFFSET %d",
				$batch_size, $offset
			);
			$rows  = $wpdb->get_results( $query, ARRAY_A );

			// Process the rows
			foreach ( $rows as $row ) {

				// Insert row into rogershood_user_points
				$wpdb->insert(
					USER_POINTS_TRANSACTIONS_TABLE,
					array(
						'user_id'          => $row['ID'],
						'action_type'      => $row['action_type'],
						'transaction_type' => $row['transaction_type'],
						'order_id'         => $row['order_id'],
						'points'           => $row['points'],
						'transaction_date' => wp_date( 'Y-m-d H:i:s', $row['created_at'] ),
					),
					array( '%d', '%s', '%s', '%d', '%d', '%s' ),
				);
			}

			// Increment the offset for the next batch
			$offset += $batch_size;

			// Output progress
			WP_CLI::log( "Processed batch with offset: $offset" );

		} while ( count( $rows ) > 0 );

		// restore unused coupons and convert back to points
		$rewards_table = $wpdb->prefix . 'wlr_user_rewards';

		$sql = "SELECT email, require_point FROM $rewards_table WHERE status = 'active'";

		$rewards      = $wpdb->get_results( $sql, ARRAY_A );
		$coupon_count = count( $rewards );

		WP_CLI::log( "Found $coupon_count unused coupons. Restoring redeemed points." );

		foreach ( $rewards as $reward ) {
			$user = get_user_by( 'email', $reward['email'] );

			if ( ! $user ) {
				continue;
			}

			$data = array(
				'user_id'          => $user->ID,
				'action_type'      => 'restore_unused_coupon',
				'transaction_type' => 'credit',
				'order_id'         => 0,
				'points'           => $reward['require_point'],
				'transaction_date' => current_time( 'mysql', false ),
			);

			$wpdb->insert(
				USER_POINTS_TRANSACTIONS_TABLE,
				$data,
				array( '%d', '%s', '%s', '%d', '%d', '%s' ),
			);
		}

		WP_CLI::log( 'Migration of points transactions completed.' );
	}
}

// Register the WP CLI command.
WP_CLI::add_command( 'rogershood points', 'Rogershood_Points_Migrate_Command' );
