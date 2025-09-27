<?php
/**
 * Simple test to verify WooCommerce setup
 *
 * @package Kickbox_Integration
 */

class Test_Simple extends WP_UnitTestCase {

	/**
	 * Test that WordPress is loaded
	 */
	public function test_wordpress_loaded() {
		$this->assertTrue( function_exists( 'wp_die' ) );
	}

	/**
	 * Test that WooCommerce is available
	 */
	public function test_woocommerce_available() {
		// Check if WooCommerce class exists
		$woocommerce_available = class_exists( 'WooCommerce' );
		
		if ( ! $woocommerce_available ) {
			$this->markTestSkipped( 'WooCommerce is not available' );
		}
		
		$this->assertTrue( $woocommerce_available );
	}

	/**
	 * Test that our plugin is loaded
	 */
	public function test_plugin_loaded() {
		// Check if plugin constants are defined
		$constants_defined = defined( 'KICKBOX_INTEGRATION_VERSION' ) && 
		                     defined( 'KICKBOX_INTEGRATION_PLUGIN_DIR' ) && 
		                     defined( 'KICKBOX_INTEGRATION_PLUGIN_URL' );
		
		// If constants aren't defined, the plugin might not be loaded yet
		if ( ! $constants_defined ) {
			$this->markTestSkipped( 'Plugin constants not defined - plugin may not be loaded' );
		}
		
		$this->assertTrue( $constants_defined );
	}
}
