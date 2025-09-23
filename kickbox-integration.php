<?php
/**
 * Plugin Name: Kickbox Integration
 * Plugin URI: https://munjr.com/kickbox-integration
 * Description: Integrates Kickbox email verification service with WooCommerce for real-time email validation during checkout.
 * Version: 1.0.0
 * Author: munjr llc
 * Author URI: https://munjr.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kickbox-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * HPOS: Compatible
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'KICKBOX_INTEGRATION_VERSION', '1.0.0' );
define( 'KICKBOX_INTEGRATION_PLUGIN_FILE', __FILE__ );
define( 'KICKBOX_INTEGRATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KICKBOX_INTEGRATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KICKBOX_INTEGRATION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Declare HPOS compatibility
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Check if WooCommerce is active
add_action( 'plugins_loaded', 'kickbox_integration_check_woocommerce' );

function kickbox_integration_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'kickbox_integration_woocommerce_missing_notice' );

		return;
	}

	// Initialize the plugin
	kickbox_integration_init();
}

function kickbox_integration_woocommerce_missing_notice() {
	echo '<div class="error"><p><strong>' . esc_html__( 'WooCommerce Kickbox Integration', 'kickbox-integration' ) . '</strong> ' .
	     esc_html__( 'requires WooCommerce to be installed and active.', 'kickbox-integration' ) . '</p></div>';
}

function kickbox_integration_init() {
	// Load plugin textdomain
	load_plugin_textdomain( 'kickbox-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

// Include required files
	require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-verification.php';
	require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-admin.php';
	require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-checkout.php';
	require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-registration.php';
	require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-dashboard-widget.php';
	require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-flagged-emails.php';

// Initialize classes
	new Kickbox_Integration_Verification();
	new Kickbox_Integration_Admin();
	new Kickbox_Integration_Checkout();
	new Kickbox_Integration_Registration();
	new Kickbox_Integration_Dashboard_Widget();
	new Kickbox_Integration_Flagged_Emails();
}

// Activation hook
register_activation_hook( __FILE__, 'kickbox_integration_activate' );

function kickbox_integration_activate() {
	// Create database tables if needed
	kickbox_integration_create_tables();

	// Add origin column to existing verification log table if it doesn't exist
	kickbox_integration_add_origin_column_to_verification_log();

	// Add performance indexes to existing tables
	kickbox_integration_add_performance_indexes();

	// Set default options
	kickbox_integration_set_default_options();
}

function kickbox_integration_create_tables() {
	global $wpdb;

	$verification_log_table = $wpdb->prefix . 'kickbox_integration_verification_log';
	$flagged_emails_table   = $wpdb->prefix . 'kickbox_integration_flagged_emails';

	$charset_collate = $wpdb->get_charset_collate();

	// Verification log table
	$sql_verification = "CREATE TABLE $verification_log_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        verification_result varchar(50) NOT NULL,
        verification_data longtext,
        user_id bigint(20),
        order_id bigint(20),
        origin varchar(50) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY email (email),
        KEY user_id (user_id),
        KEY order_id (order_id),
        KEY origin (origin),
        KEY email_created (email, created_at),
        KEY result_created (verification_result, created_at),
        KEY origin_created (origin, created_at)
    ) $charset_collate;";

	// Flagged emails table
	$sql_flagged = "CREATE TABLE $flagged_emails_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        order_id bigint(20) NULL,
        user_id bigint(20) NULL,
        origin varchar(50) NOT NULL DEFAULT 'checkout',
        kickbox_result longtext NOT NULL,
        verification_action varchar(20) NOT NULL DEFAULT 'review',
        admin_decision varchar(20) NOT NULL DEFAULT 'pending',
        admin_notes text NULL,
        flagged_date datetime DEFAULT CURRENT_TIMESTAMP,
        reviewed_date datetime NULL,
        reviewed_by bigint(20) NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY order_id (order_id),
        KEY user_id (user_id),
        KEY admin_decision (admin_decision),
        KEY verification_action (verification_action),
        KEY flagged_date (flagged_date),
        KEY origin (origin),
        KEY email_flagged (email, flagged_date),
        KEY decision_flagged (admin_decision, flagged_date),
        KEY origin_decision (origin, admin_decision),
        KEY action_decision (verification_action, admin_decision)
    ) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql_verification );
	dbDelta( $sql_flagged );
}

function kickbox_integration_add_origin_column_to_verification_log() {
	global $wpdb;

	$verification_log_table = $wpdb->prefix . 'kickbox_integration_verification_log';

	// Check if origin column exists
	$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $verification_log_table, 'origin' ) );

	if ( empty( $column_exists ) ) {
		// Add origin column
		$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN origin varchar(50) DEFAULT NULL AFTER order_id", $verification_log_table ) );

		// Add index for origin column
		$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD INDEX origin (origin)", $verification_log_table ) );

		// Note: We don't update existing records since we don't know their actual origin
	}
}

function kickbox_integration_add_performance_indexes() {
	global $wpdb;

	$verification_log_table = $wpdb->prefix . 'kickbox_integration_verification_log';
	$flagged_emails_table   = $wpdb->prefix . 'kickbox_integration_flagged_emails';

	// Add composite indexes to verification log table
	$verification_indexes = array(
		'email_created'  => 'email, created_at',
		'result_created' => 'verification_result, created_at',
		'origin_created' => 'origin, created_at'
	);

	foreach ( $verification_indexes as $index_name => $columns ) {
		$index_exists = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = %s", $verification_log_table, $index_name ) );
		if ( empty( $index_exists ) ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD INDEX %i (%s)", $verification_log_table, $index_name, $columns ) );
		}
	}

	// Add composite indexes to flagged emails table
	$flagged_indexes = array(
		'email_flagged'    => 'email, flagged_date',
		'decision_flagged' => 'admin_decision, flagged_date',
		'origin_decision'  => 'origin, admin_decision',
		'action_decision'  => 'verification_action, admin_decision'
	);

	foreach ( $flagged_indexes as $index_name => $columns ) {
		$index_exists = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = %s", $flagged_emails_table, $index_name ) );
		if ( empty( $index_exists ) ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD INDEX %i (%s)", $flagged_emails_table, $index_name, $columns ) );
		}
	}
}

function kickbox_integration_set_default_options() {
	$default_options = array(
		'kickbox_integration_api_key'                          => '',
		'kickbox_integration_deliverable_action'               => 'allow',
		'kickbox_integration_undeliverable_action'             => 'allow',
		'kickbox_integration_risky_action'                     => 'allow',
		'kickbox_integration_unknown_action'                   => 'allow',
		'kickbox_integration_enable_checkout_verification'     => 'no',
		'kickbox_integration_enable_registration_verification' => 'no',
		'kickbox_integration_allow_list'                       => array()
	);

	foreach ( $default_options as $option => $value ) {
		if ( get_option( $option ) === false ) {
			add_option( $option, $value );
		}
	}
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'kickbox_integration_deactivate' );

function kickbox_integration_deactivate() {
	// Clean up if needed
}
