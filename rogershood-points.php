<?php
/**
 * Plugin Name: Rogershood Points
 * Description: A plugin for a points system.
 * Version: 1.0
 * Author: Jenny Chan
 */

defined( 'ABSPATH' ) || exit;

// define plugin dir
define( 'RH_POINTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RH_POINTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

global $wpdb;
define( 'USER_POINTS_TABLE', $wpdb->prefix . 'rogershood_user_points' );
define( 'USER_POINTS_TRANSACTIONS_TABLE', $wpdb->prefix . 'rogershood_user_points_transactions' );

require RH_POINTS_PLUGIN_DIR . 'includes/class-rogershood-points.php';
require RH_POINTS_PLUGIN_DIR . 'includes/class-rogershood-points-transaction.php';
require RH_POINTS_PLUGIN_DIR . 'includes/helper-functions.php';

require RH_POINTS_PLUGIN_DIR . 'admin/class-rogershood-points-admin.php';
require RH_POINTS_PLUGIN_DIR . 'admin/class-rogershood-points-migrate.php';

require RH_POINTS_PLUGIN_DIR . 'public/class-rogershood-points-checkout.php';
require RH_POINTS_PLUGIN_DIR . 'public/class-rogershood-points-my-account.php';
require RH_POINTS_PLUGIN_DIR . 'public/class-rogershood-points-order.php';
require RH_POINTS_PLUGIN_DIR . 'public/class-rogershood-points-shop.php';
require RH_POINTS_PLUGIN_DIR . 'public/class-rogershood-points-registration.php';

function rogershood_points_init() {

	// Initialize the admin interface
	if (is_admin()) {
		$admin = new Rogershood_Points_Admin();
		$admin->init();
	}

	$reg = new Rogershood_Points_Registration();
	$reg->init();

	$checkout = new Rogershood_Points_Checkout();
	$checkout->init();

	$order = new Rogershood_Points_Order();
	$order->init();

	$shop = new Rogershood_Points_Shop();
	$shop->init();

	$my_account = new Rogershood_Points_My_Account();
	$my_account->init();

}

add_action('plugins_loaded', 'rogershood_points_init');
function rh_points_install() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$sql1 = "CREATE TABLE IF NOT EXISTS " . USER_POINTS_TABLE. " (
        user_id BIGINT(20) UNSIGNED NOT NULL,
        current_points INT(11) NOT NULL,
        total_points INT(11) NOT NULL,
        redeemed_points INT(11) NOT NULL,
        PRIMARY KEY (user_id)
    ) $charset_collate;";

	$sql2 = "CREATE TABLE IF NOT EXISTS " . USER_POINTS_TRANSACTIONS_TABLE. " (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        points INT(11) NOT NULL,
        transaction_type VARCHAR(20) NOT NULL, /* 'credit' or 'debit' */
        action_type VARCHAR(255) NOT NULL,
        order_id BIGINT(20) UNSIGNED,
        transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

	require( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql1 );
	dbDelta( $sql2 );
}

register_activation_hook( __FILE__, 'rh_points_install' );
