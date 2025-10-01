<?php
/**
 * Unit tests for Kickbox Integration plugin activation
 *
 * @package Kickbox_Integration
 */

class Test_Kickbox_Activation extends WP_UnitTestCase {

	/**
	 * Test that activation hook is registered
	 */
	public function test_activation_hook_registered() {
		$activation_hook = 'activate_' . KICKBOX_INTEGRATION_PLUGIN_BASENAME;

		// Use has_action to check if activation function is registered
		$priority = has_action( $activation_hook, 'kickbox_integration_activate' );

		$this->assertNotFalse( $priority, 'kickbox_integration_activate should be registered as activation hook' );
	}

	/**
	 * Test that deactivation hook is registered
	 */
	public function test_deactivation_hook_registered() {
		$deactivation_hook = 'deactivate_' . KICKBOX_INTEGRATION_PLUGIN_BASENAME;

		// Use has_action to check if deactivation function is registered
		$priority = has_action( $deactivation_hook, 'kickbox_integration_deactivate' );

		$this->assertNotFalse( $priority, 'kickbox_integration_deactivate should be registered as deactivation hook' );
	}

	/**
	 * Test that activation hook works with different WordPress and WooCommerce versions
	 *
	 * @dataProvider provideActivationScenarios
	 */
	public function test_activation_hook_execution(
		$wp_version,
		$is_wc_active,
		$wc_version,
		$should_succeed,
		$expected_error,
		$expected_message_fragment
	) {
		// Create a mock of Kickbox_Integration_Activator
		$activator_mock = $this->getMockBuilder( Kickbox_Integration_Activator::class )
		                       ->onlyMethods( array(
			                       'get_wordpress_version',
			                       'is_woocommerce_active',
			                       'get_woocommerce_version',
			                       'deactivate_plugin',
			                       'run_installer'
		                       ) )
		                       ->getMock();

		// Mock WordPress version
		$activator_mock->method( 'get_wordpress_version' )
		               ->willReturn( $wp_version );

		// Mock WooCommerce active status
		$activator_mock->method( 'is_woocommerce_active' )
		               ->willReturn( $is_wc_active );

		// Mock WooCommerce version
		$activator_mock->method( 'get_woocommerce_version' )
		               ->willReturn( $wc_version );

		// Set up spy for deactivate_plugin - expect it to be called if activation should fail
		if ( ! $should_succeed ) {
			$activator_mock->expects( $this->once() )
			               ->method( 'deactivate_plugin' );
		} else {
			$activator_mock->expects( $this->never() )
			               ->method( 'deactivate_plugin' );
		}

		// Set up spy for run_installer - expect it to be called only if activation succeeds
		if ( $should_succeed ) {
			$activator_mock->expects( $this->once() )
			               ->method( 'run_installer' );
		} else {
			$activator_mock->expects( $this->never() )
			               ->method( 'run_installer' );
		}

		// Run activation checks
		$result = $activator_mock->run_activation_checks();

		// Verify result
		if ( $should_succeed ) {
			$this->assertTrue( $result, 'Activation should succeed when all requirements are met' );
		} else {
			$this->assertIsArray( $result, 'Activation should return error array when requirements are not met' );
			$this->assertArrayHasKey( 'error', $result );
			$this->assertArrayHasKey( 'message', $result );
			$this->assertEquals( $expected_error, $result['error'], 'Error type should match expected' );

			// Verify the error message contains expected fragment
			$this->assertStringContainsString(
				$expected_message_fragment,
				$result['message'],
				'Error message should contain expected text'
			);
		}
	}

	/**
	 * Data provider for activation scenarios
	 *
	 * @return array Test scenarios [
	 *      wp_version, // the wordpress version
	 *      wc_active, // Whether WooCommerce is active
	 *      wc_version, // The WooCommerce version
	 *      should_succeed, // Whether the activation should succeed
	 *      expected_error, // The expected error
	 *      expected_message_fragment // The expected error message
	 *   ]
	 */
	public function provideActivationScenarios() {
		return array(
			'all_requirements_met'                  => array(
				'6.2.0',
				true,
				'10.2.0',
				true,
				null,
				''
			),
			'newer_versions'                        => array(
				'6.4.0',
				true,
				'10.3.0',
				true,
				null,
				''
			),
			'wordpress_version_too_old'             => array(
				'6.1.0',
				true,
				'10.2.0',
				false,
				'wordpress_version',
				'WordPress version 6.2 or higher'
			),
			'wordpress_version_much_older'          => array(
				'5.9.0',
				true,
				'10.2.0',
				false,
				'wordpress_version',
				'You are running version 5.9.0'
			),
			'woocommerce_not_active'                => array(
				'6.2.0',
				false,
				null,
				false,
				'woocommerce_missing',
				'WooCommerce to be installed and active'
			),
			'woocommerce_version_too_old'           => array(
				'6.2.0',
				true,
				'10.0.0',
				false,
				'woocommerce_version',
				'WooCommerce version 10.2 or higher'
			),
			'woocommerce_version_much_older'        => array(
				'6.2.0',
				true,
				'9.5.0',
				false,
				'woocommerce_version',
				'You are running version 9.5.0'
			),
			'wordpress_old_and_woocommerce_missing' => array(
				'6.1.0',
				false,
				null,
				false,
				'wordpress_version',
				'WordPress version 6.2 or higher'
			),
		);
	}

	/**
	 * Test WordPress version check method
	 */
	public function test_wordpress_version_check() {
		$activator = new Kickbox_Integration_Activator();

		// Use reflection to call protected method
		$method = new ReflectionMethod( Kickbox_Integration_Activator::class, 'check_wordpress_version' );
		$method->setAccessible( true );

		$result = $method->invoke( $activator );

		// Should return true in test environment
		$this->assertTrue( $result, 'WordPress version check should pass in test environment' );
	}

	/**
	 * Test WooCommerce exists check method
	 */
	public function test_woocommerce_exists_check() {
		$activator = new Kickbox_Integration_Activator();

		// Use reflection to call protected method
		$method = new ReflectionMethod( Kickbox_Integration_Activator::class, 'check_woocommerce_exists' );
		$method->setAccessible( true );

		$result = $method->invoke( $activator );

		// Should return true in test environment
		$this->assertTrue( $result, 'WooCommerce exists check should pass in test environment' );
	}

	/**
	 * Test WooCommerce version check method
	 */
	public function test_woocommerce_version_check() {
		$activator = new Kickbox_Integration_Activator();

		// Use reflection to call protected method
		$method = new ReflectionMethod( Kickbox_Integration_Activator::class, 'check_woocommerce_version' );
		$method->setAccessible( true );

		$result = $method->invoke( $activator );

		// Should return true in test environment
		$this->assertTrue( $result, 'WooCommerce version check should pass in test environment' );
	}

	/**
	 * Test deactivation function behavior
	 */
	public function test_deactivation_function() {
		// Should complete without errors (returns null/void)
		$this->assertNull( kickbox_integration_deactivate(), 'Deactivation function should complete without errors' );
	}
}

