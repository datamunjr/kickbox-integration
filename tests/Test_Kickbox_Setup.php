<?php
/**
 * Basic test to verify the Kickbox Plugin setup is working
 *
 * @package Kickbox_Integration
 */

class Test_Kickbox_Setup extends WP_UnitTestCase {

	/**
	 * Test that WordPress is loaded
	 */
	public function test_wordpress_loaded() {
		$this->assertTrue( function_exists( 'wp_loaded' ) || function_exists( 'wp_die' ) );
	}

	/**
	 * Test that our plugin is loaded
	 */
	public function test_plugin_loaded() {
		// Check if plugin constants are defined
		$constants_defined = defined( 'KICKBOX_INTEGRATION_VERSION' ) &&
		                     defined( 'KICKBOX_INTEGRATION_PLUGIN_DIR' ) &&
		                     defined( 'KICKBOX_INTEGRATION_PLUGIN_URL' );

		$this->assertTrue( $constants_defined );
	}

	/**
	 * Test that our classes exist
	 */
	public function test_classes_exist() {
		// Check if classes exist, skip if plugin not loaded
		$classes_exist = class_exists( 'Kickbox_Integration_Verification' ) &&
		                 class_exists( 'Kickbox_Integration_Admin' ) &&
		                 class_exists( 'Kickbox_Integration_Checkout' ) &&
		                 class_exists( 'Kickbox_Integration_Registration' ) &&
		                 class_exists( 'Kickbox_Integration_Dashboard_Widget' ) &&
		                 class_exists( 'Kickbox_Integration_Flagged_Emails' );

		$this->assertTrue( $classes_exist );
	}

	/**
	 * Test that database tables are created
	 */
	public function test_database_tables() {
		global $wpdb;

		$verification_table = $wpdb->prefix . 'kickbox_integration_verification_log';
		$flagged_table      = $wpdb->prefix . 'kickbox_integration_flagged_emails';

		$verification_exists = $wpdb->get_var( "SHOW TABLES LIKE '$verification_table'" ) === $verification_table;
		$flagged_exists      = $wpdb->get_var( "SHOW TABLES LIKE '$flagged_table'" ) === $flagged_table;

		$this->assertTrue( $verification_exists );
		$this->assertTrue( $flagged_exists );
	}
}
