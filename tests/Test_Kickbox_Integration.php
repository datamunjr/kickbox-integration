<?php
/**
 * Unit tests for the main Kickbox Integration plugin file
 *
 * @package Kickbox_Integration
 */

class Test_Kickbox_Integration extends WP_UnitTestCase {

	private function getPluginBasename() {
		return substr( dirname( __FILE__, 2 ) . '/kickbox-integration.php', 1 );
	}


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
		$activation_hook = 'activate_' . $this->getPluginBasename();
		$this->assertTrue( isset( $wp_filter[ $activation_hook ] ) );
	}

	/**
	 * Test that plugin row meta filter is registered
	 */
	public function test_plugin_row_meta_filter_registered() {
		$kickbox = KICKBOX();

		// Use has_filter to check if our specific callback is registered
		$priority = has_filter( 'plugin_row_meta', array( $kickbox, 'plugin_row_meta' ) );

		// has_filter returns the priority if found, false if not found
		$this->assertNotFalse( $priority, 'Kickbox_Integration::plugin_row_meta should be registered as a callback on plugin_row_meta' );
		$this->assertEquals( 10, $priority, 'Kickbox_Integration::plugin_row_meta should be registered at priority 10' );
	}

	/**
	 * Test that plugin dependency notice action is registered
	 */
	public function test_plugin_dependency_notice_action_registered() {
		$kickbox                         = KICKBOX();
		$plugin_dependency_notice_action = 'after_plugin_row_' . $this->getPluginBasename();

		// Use has_action to check if our specific callback is registered
		$priority = has_action( $plugin_dependency_notice_action, array( $kickbox, 'plugin_row_dependency_notice' ) );

		// has_action returns the priority if found, false if not found
		$this->assertNotFalse( $priority, 'Kickbox_Integration::plugin_row_dependency_notice should be registered as a callback on after_plugin_row' );
		$this->assertEquals( 10, $priority, 'Kickbox_Integration::plugin_row_dependency_notice should be registered at priority 10' );
	}

	/**
	 * Test that plugins_loaded action is registered
	 */
	public function test_plugins_loaded_action_registered() {
		// Use has_action to check if our specific callback is registered
		$priority = has_action( 'plugins_loaded', 'kickbox_integration_validate_and_init' );

		// has_action returns the priority if found, false if not found
		$this->assertNotFalse( $priority, 'kickbox_integration_validate_and_init should be registered as a callback on plugins_loaded' );
		$this->assertEquals( 10, $priority, 'kickbox_integration_validate_and_init should be registered at priority 10' );
	}

	/**
	 * Test that database table creation function works
	 */
	public function test_create_tables_function() {
		// Test the installer class method
		try {
			$this->assertNull( Kickbox_Integration_Installer::create_tables() );
		} catch ( Exception $e ) {
			$this->fail( "Kickbox_Integration_Installer::create_tables method failed with the following exception:\n" . $e->getMessage() );
		}
	}

	/**
	 * Test that performance indexes function works
	 */
	public function test_add_performance_indexes_function() {
		// Test the installer class method
		try {
			$errors = Kickbox_Integration_Installer::add_performance_indexes();

			$this->assertEmpty(
				$errors,
				'No errors should occur when adding performance indexes. Errors: ' . print_r( $errors, true )
			);
		} catch ( Exception $e ) {
			$this->fail( "Kickbox_Integration_Installer::add_performance_indexes method failed with the following exception:\n" . $e->getMessage() );
		}
	}

	/**
	 * Test that default options function works
	 */
	public function test_set_default_options_function() {
		// Test the installer class method
		try {
			$errors = Kickbox_Integration_Installer::set_default_options();

			$this->assertEmpty(
				$errors,
				'No errors should occur when setting default options. Errors: ' . print_r( $errors, true )
			);
		} catch ( Exception $e ) {
			$this->fail( "Kickbox_Integration_Installer::set_default_options method failed with the following exception:\n" . $e->getMessage() );
		}
	}

	/**
	 * Test that plugin dependency notice function works with different WooCommerce states
	 *
	 * @dataProvider provideDependencyNoticeScenarios
	 */
	public function test_plugin_dependency_notice_function( $wc_active, $wc_version_ok, $should_show_notice, $expected_text ) {
		// Create a mock of Kickbox_Integration that only mocks get_woocommerce_dependency_status
		$kickbox_mock = $this->getMockBuilder( Kickbox_Integration::class )
		                     ->disableOriginalConstructor()
		                     ->onlyMethods( array( 'get_woocommerce_dependency_status' ) )
		                     ->getMock();

		// Mock get_woocommerce_dependency_status() to return our test values
		$kickbox_mock->method( 'get_woocommerce_dependency_status' )
		             ->willReturn( array(
			             'active'     => $wc_active,
			             'version_ok' => $wc_version_ok,
		             ) );

		// Capture output
		ob_start();
		$kickbox_mock->plugin_row_dependency_notice();
		$output = ob_get_clean();

		// Verify output
		$this->assertIsString( $output );

		if ( $should_show_notice ) {
			$this->assertNotEmpty( $output, 'Should show dependency notice when dependencies are not met' );
			if ( ! empty( $expected_text ) ) {
				$this->assertStringContainsString( $expected_text, $output, 'Output should contain expected text' );
			}
		} else {
			$this->assertEmpty( $output, 'Should not show notice when dependencies are met' );
		}
	}

	/**
	 * Data provider for dependency notice scenarios
	 *
	 * @return array Test scenarios [wc_active, wc_version_ok, should_show_notice, expected_text]
	 */
	public function provideDependencyNoticeScenarios() {
		return array(
			'woocommerce_active_and_compatible'   => array( true, true, false, '' ),
			'woocommerce_active_but_incompatible' => array(
				true,
				false,
				true,
				'WooCommerce version 10.2+ is required'
			),
			'woocommerce_not_active'              => array(
				false,
				false,
				true,
				'WooCommerce is required but not active'
			),
			'woocommerce_not_active_version_moot' => array(
				false,
				true,
				true,
				'WooCommerce is required but not active'
			),
		);
	}

	/**
	 * Test that admin notice functions output correctly
	 */
	public function test_admin_notice_functions_output() {
		// Test WooCommerce missing notice
		ob_start();
		Kickbox_Integration::display_woocommerce_missing_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'WooCommerce', $output );
		$this->assertStringContainsString( 'requires', $output );

		// Test WooCommerce deactivated notice
		ob_start();
		Kickbox_Integration::display_woocommerce_deactivated_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'deactivated', $output );

		// Test WooCommerce version notice
		ob_start();
		Kickbox_Integration::display_woocommerce_version_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'version', $output );

		// Test WordPress version notice
		ob_start();
		Kickbox_Integration::display_wordpress_version_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'WordPress', $output );
		$this->assertStringContainsString( 'version', $output );
	}

	/**
	 * Test that KICKBOX() function returns singleton instance
	 */
	public function test_kickbox_singleton_function() {
		$instance1 = KICKBOX();
		$instance2 = KICKBOX();

		// Should return the same instance
		$this->assertSame( $instance1, $instance2, 'KICKBOX() should return the same singleton instance' );
		$this->assertInstanceOf( 'Kickbox_Integration', $instance1 );
	}

	/**
	 * Test that plugin components are initialized
	 */
	public function test_plugin_components_initialized() {
		$kickbox = KICKBOX();

		// Trigger component initialization
		$kickbox->init_components();

		// Check that all components are initialized
		$this->assertInstanceOf( 'Kickbox_Integration_Verification', $kickbox->verification );
		$this->assertInstanceOf( 'Kickbox_Integration_Admin', $kickbox->admin );
		$this->assertInstanceOf( 'Kickbox_Integration_Checkout', $kickbox->checkout );
		$this->assertInstanceOf( 'Kickbox_Integration_Registration', $kickbox->registration );
		$this->assertInstanceOf( 'Kickbox_Integration_Dashboard_Widget', $kickbox->dashboard_widget );
		$this->assertInstanceOf( 'Kickbox_Integration_Flagged_Emails', $kickbox->flagged_emails );
	}

	/**
	 * Test dependency checking methods
	 */
	public function test_dependency_checking() {
		// Should return true since dependencies are met in test environment
		$this->assertTrue( Kickbox_Integration::check_dependencies(), 'Dependencies should be met in test environment' );
		$this->assertTrue( Kickbox_Integration::is_woocommerce_active(), 'WooCommerce should be active in test environment' );
	}

	/**
	 * Test that plugins_loaded action triggers validation and initialization
	 */
	public function test_plugins_loaded_action_triggers_validation() {
		// Clear any existing actions
		remove_all_actions( 'plugins_loaded' );

		// Re-add the action
		add_action( 'plugins_loaded', 'kickbox_integration_validate_and_init' );

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
