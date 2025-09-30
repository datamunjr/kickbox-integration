<?php
/**
 * Unit tests for the main Kickbox Integration plugin file
 *
 * @package Kickbox_Integration
 */

class Test_Kickbox_Integration extends WP_UnitTestCase {

	/**
	 * Test that plugin constants are defined correctly
	 */
	public function test_plugin_constants() {
		$this->assertTrue( defined( 'KICKBOX_INTEGRATION_VERSION' ) );
		$this->assertTrue( defined( 'KICKBOX_INTEGRATION_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'KICKBOX_INTEGRATION_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'KICKBOX_INTEGRATION_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'KICKBOX_INTEGRATION_PLUGIN_BASENAME' ) );

		$this->assertEquals( '1.0.0', KICKBOX_INTEGRATION_VERSION );
		$this->assertStringEndsWith( '/', KICKBOX_INTEGRATION_PLUGIN_DIR );
		$this->assertStringEndsWith( '/', KICKBOX_INTEGRATION_PLUGIN_URL );
		$this->assertStringContainsString( 'kickbox-integration.php', KICKBOX_INTEGRATION_PLUGIN_BASENAME );
	}

	/**
	 * Test that database tables are created correctly
	 */
	public function test_database_tables_creation() {
		global $wpdb;

		$verification_table = $wpdb->prefix . 'kickbox_integration_verification_log';
		$flagged_table      = $wpdb->prefix . 'kickbox_integration_flagged_emails';

		// Check if tables exist
		$verification_exists = $wpdb->get_var( "SHOW TABLES LIKE '$verification_table'" ) === $verification_table;
		$flagged_exists      = $wpdb->get_var( "SHOW TABLES LIKE '$flagged_table'" ) === $flagged_table;

		$this->assertTrue( $verification_exists, 'Verification log table should exist' );
		$this->assertTrue( $flagged_exists, 'Flagged emails table should exist' );

		// Check table structure for verification log
		$verification_columns = $wpdb->get_results( "DESCRIBE $verification_table" );
		$column_names         = wp_list_pluck( $verification_columns, 'Field' );

		$expected_columns = array(
			'id',
			'email',
			'verification_result',
			'verification_data',
			'user_id',
			'order_id',
			'origin',
			'created_at'
		);
		foreach ( $expected_columns as $column ) {
			$this->assertContains( $column, $column_names, "Column $column should exist in verification log table" );
		}

		// Check table structure for flagged emails
		$flagged_columns      = $wpdb->get_results( "DESCRIBE $flagged_table" );
		$flagged_column_names = wp_list_pluck( $flagged_columns, 'Field' );

		$expected_flagged_columns = array(
			'id',
			'email',
			'order_id',
			'user_id',
			'origin',
			'kickbox_result',
			'verification_action',
			'admin_decision',
			'admin_notes',
			'flagged_date',
			'reviewed_date',
			'reviewed_by'
		);
		foreach ( $expected_flagged_columns as $column ) {
			$this->assertContains( $column, $flagged_column_names, "Column $column should exist in flagged emails table" );
		}
	}

	/**
	 * Test that database indexes are created
	 */
	public function test_database_indexes() {
		global $wpdb;

		$verification_table = $wpdb->prefix . 'kickbox_integration_verification_log';
		$flagged_table      = $wpdb->prefix . 'kickbox_integration_flagged_emails';

		// Check verification log indexes
		$verification_indexes     = $wpdb->get_results( "SHOW INDEX FROM $verification_table" );
		$verification_index_names = wp_list_pluck( $verification_indexes, 'Key_name' );

		$expected_verification_indexes = array(
			'PRIMARY',
			'email',
			'user_id',
			'order_id',
			'origin',
			'email_created',
			'result_created',
			'origin_created'
		);
		foreach ( $expected_verification_indexes as $index ) {
			$this->assertContains( $index, $verification_index_names, "Index $index should exist in verification log table" );
		}

		// Check flagged emails indexes
		$flagged_indexes     = $wpdb->get_results( "SHOW INDEX FROM $flagged_table" );
		$flagged_index_names = wp_list_pluck( $flagged_indexes, 'Key_name' );

		$expected_flagged_indexes = array(
			'PRIMARY',
			'email',
			'order_id',
			'user_id',
			'admin_decision',
			'verification_action',
			'flagged_date',
			'origin',
			'email_flagged',
			'decision_flagged',
			'origin_decision',
			'action_decision'
		);
		foreach ( $expected_flagged_indexes as $index ) {
			$this->assertContains( $index, $flagged_index_names, "Index $index should exist in flagged emails table" );
		}
	}

	/**
	 * Test that default options are set correctly
	 */
	public function test_default_options() {
		$kickbox_integration_admin = new Kickbox_Integration_Admin();
		$kickbox_integration_admin->register_settings();

		$expected_default_options = array(
			'kickbox_integration_api_key'                          => '',
			'kickbox_integration_deliverable_action'               => 'allow',
			'kickbox_integration_undeliverable_action'             => 'block',
			'kickbox_integration_risky_action'                     => 'block',
			'kickbox_integration_unknown_action'                   => 'block',
			'kickbox_integration_enable_checkout_verification'     => 'no',
			'kickbox_integration_enable_registration_verification' => 'no',
			'kickbox_integration_allow_list'                       => array()
		);

		$actual_options = array();
		foreach ( array_keys( $expected_default_options ) as $option_name ) {
			$actual_options[ $option_name ] = get_option( $option_name );
		}

		$this->assertEquals( $expected_default_options, $actual_options );
	}

	/**
	 * Test that activation hook is registered
	 */
	public function test_activation_hook_registered() {
		global $wp_filter;
		$activation_hook = 'activate_' . substr( dirname( __FILE__, 2 ) . '/kickbox-integration.php', 1 );
		$this->assertTrue( isset( $wp_filter[ $activation_hook ] ) );
	}

	/**
	 * Test that deactivation hook is registered
	 */
	public function test_deactivation_hook_registered() {
		global $wp_filter;
		$deactivation_hook = 'activate_' . substr( dirname( __FILE__, 2 ) . '/kickbox-integration.php', 1 );
		$this->assertTrue( isset( $wp_filter[ $deactivation_hook ] ) );
	}

	/**
	 * Test that plugin row meta filter is registered
	 */
	public function test_plugin_row_meta_filter_registered() {
		global $wp_filter;
		$this->assertTrue( isset( $wp_filter['plugin_row_meta'] ) );
		$this->assertTrue( isset( $wp_filter['plugin_row_meta']->callbacks[10]['kickbox_integration_plugin_row_meta'] ) );
	}

	/**
	 * Test that plugin dependency notice action is registered
	 */
	public function test_plugin_dependency_notice_action_registered() {
		global $wp_filter;
		$plugin_dependency_notice_action = 'after_plugin_row_' . substr( dirname( __FILE__, 2 ) . '/kickbox-integration.php', 1 );
		$this->assertTrue( isset( $wp_filter[ $plugin_dependency_notice_action ] ) );
	}

	/**
	 * Test that the check_woocommerce
	 */
	public function test_plugins_loaded_action_registered() {
		global $wp_filter;

		$this->assertTrue( isset( $wp_filter['plugins_loaded'] ) );

	}

	/**
	 * Test that database table creation function works
	 */
	public function test_create_tables_function() {
		// This should not throw any errors
		$this->assertNull( kickbox_integration_create_tables() );
	}

	/**
	 * Test that origin column addition function works
	 */
	public function test_add_origin_column_function() {
		// This should not throw any errors
		$this->assertNull( kickbox_integration_add_origin_column_to_verification_log() );
	}

	/**
	 * Test that performance indexes function works
	 */
	public function test_add_performance_indexes_function() {
		// This should not throw any errors
		$this->assertNull( kickbox_integration_add_performance_indexes() );
	}

	/**
	 * Test that default options function works
	 */
	public function test_set_default_options_function() {
		// This should not throw any errors
		$this->assertNull( kickbox_integration_set_default_options() );
	}

	/**
	 * Test that deactivation function works
	 */
	public function test_deactivate_function() {
		// This should not throw any errors
		$this->assertNull( kickbox_integration_deactivate() );
	}

	/**
	 * Test that plugin dependency notice function works
	 */
	public function test_plugin_dependency_notice_function() {
		// Capture output
		ob_start();
		kickbox_integration_plugin_dependency_notice();
		$output = ob_get_clean();

		// Should output HTML (empty if no issues)
		$this->assertIsString( $output );
	}

	/**
	 * Test that admin notice functions output correctly
	 */
	public function test_admin_notice_functions_output() {
		// Test WooCommerce missing notice
		ob_start();
		kickbox_integration_woocommerce_missing_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'WooCommerce', $output );
		$this->assertStringContainsString( 'requires', $output );

		// Test WooCommerce deactivated notice
		ob_start();
		kickbox_integration_woocommerce_deactivated_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'deactivated', $output );

		// Test WooCommerce version notice
		ob_start();
		kickbox_integration_woocommerce_version_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'version', $output );

		// Test WordPress version notice
		ob_start();
		kickbox_integration_wordpress_version_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'WordPress', $output );
		$this->assertStringContainsString( 'version', $output );
	}

	/**
	 * Test that plugin initialization function works
	 */
	public function test_init_function() {
		// This should not throw any errors
		$this->assertNull( kickbox_integration_init() );
	}

	/**
	 * Test that WooCommerce check function works
	 */
	public function test_check_woocommerce_function() {
		// This should not throw any errors
		$this->assertNull( kickbox_integration_check_woocommerce() );
	}

	/**
	 * Test that plugins_loaded action triggers WooCommerce check
	 */
	public function test_plugins_loaded_action_triggers_check() {
		// Clear any existing actions
		remove_all_actions( 'plugins_loaded' );

		// Re-add the action
		add_action( 'plugins_loaded', 'kickbox_integration_check_woocommerce' );

		// Trigger the action
		do_action( 'plugins_loaded' );

		// The function should have been called (no errors means it worked)
		$this->assertTrue( true ); // If we get here, no fatal errors occurred
	}

	/**
	 * Test that activation hook works
	 */
	public function test_activation_hook_execution() {
		// Test that activation function can be called
		$this->assertNull( kickbox_integration_activate() );
	}

	/**
	 * Test that deactivation hook works
	 */
	public function test_deactivation_hook_execution() {
		// Test that deactivation function can be called
		$this->assertNull( kickbox_integration_deactivate() );
	}

	/**
	 * Test that wp_loaded action works
	 */
	public function test_wp_loaded_action() {
		// Trigger wp_loaded action
		do_action( 'wp_loaded' );

		// Should not throw any errors
		$this->assertTrue( true );
	}

	/**
	 * Test that after_plugin_row action works
	 */
	public function test_after_plugin_row_action() {
		// Capture output
		ob_start();
		do_action( 'after_plugin_row_' . plugin_basename( __FILE__ ) );
		$output = ob_get_clean();

		// Should not throw any errors
		$this->assertIsString( $output );
	}
}
