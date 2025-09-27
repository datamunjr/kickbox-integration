<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Kickbox_Integration
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Load PHPUnit Polyfills from Composer
if ( file_exists( dirname( dirname( __FILE__ ) ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' ) ) {
	require_once dirname( dirname( __FILE__ ) ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_kickbox_integration() {
	// Load our plugin directly
	require dirname( __FILE__, 2 ) . '/kickbox-integration.php';
	
	// Manually create tables since we're not going through activation
	if ( function_exists( 'kickbox_integration_create_tables' ) ) {
		kickbox_integration_create_tables();
	}
}

// Load WooCommerce first
function _manually_load_woocommerce() {
	// Note: the TMPDIR directory is used by the isntall-wp-tests.sh shell script to install a temporary
	// Wordpress instance as well as install woocommerce (and activate it). See the install-wp-tests.sh shell script
	// for more details.
	$woocommerce_path = getenv( 'TMPDIR' ) . 'wordpress/wp-content/plugins/woocommerce/woocommerce.php';
	if ( file_exists( $woocommerce_path ) ) {
		// Set up WooCommerce constants
		if ( ! defined( 'WC_ABSPATH' ) ) {
			define( 'WC_ABSPATH', dirname( $woocommerce_path ) . '/' );
		}
		require_once $woocommerce_path;

		// Initialize WooCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			WC();
		}
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_woocommerce' );
tests_add_filter( 'muplugins_loaded', '_manually_load_kickbox_integration' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
