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


// Load WooCommerce first
function _manually_load_woocommerce() {
	// Try multiple possible paths for WooCommerce
	$possible_paths = array(
		getenv( 'TMPDIR' ) . 'wordpress/wp-content/plugins/woocommerce/woocommerce.php',
		'/tmp/wordpress/wp-content/plugins/woocommerce/woocommerce.php',
		'/var/www/html/wp-content/plugins/woocommerce/woocommerce.php'
	);

	$woocommerce_path = null;
	foreach ( $possible_paths as $path ) {
		if ( file_exists( $path ) ) {
			$woocommerce_path = $path;
			break;
		}
	}

	if ( $woocommerce_path ) {
		// Set up WooCommerce constants
		if ( ! defined( 'WC_ABSPATH' ) ) {
			define( 'WC_ABSPATH', dirname( $woocommerce_path ) . '/' );
		}
		require_once $woocommerce_path;

		// Initialize WooCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			WC();

			// Ensure WooCommerce database tables are created
			if ( class_exists( 'WC_Install' ) ) {
				WC_Install::create_tables();
			}
		}
	}
}

/**
 * Manually load the Kickbox plugin.
 */
function _manually_load_kickbox_integration() {
	// Load our plugin directly
	require dirname( __FILE__, 2 ) . '/kickbox-integration.php';

	// Initialize the singleton to load all includes
	if ( function_exists( 'KICKBOX' ) ) {
		KICKBOX();
	}

	// Manually create tables since we're not going through activation
	if ( class_exists( 'Kickbox_Integration_Installer' ) ) {
		Kickbox_Integration_Installer::create_tables();
	} else {
		echo "Warning: Kickbox_Integration_Installer class not found!\n";
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_woocommerce' );
tests_add_filter( 'muplugins_loaded', '_manually_load_kickbox_integration' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
