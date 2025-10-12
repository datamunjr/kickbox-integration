<?php
/**
 * Kickbox Integration Activator
 *
 * Handles plugin activation checks and setup.
 *
 * @package Kickbox_Integration
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kickbox Integration Activator class
 */
class Kickbox_Integration_Activator {

	/**
	 * Activate the plugin
	 *
	 * @since 1.0.0
	 * @return bool|array True if successful, array with error info if failed
	 */
	public static function activate() {
		$activator = new self();
		return $activator->run_activation_checks();
	}

	/**
	 * Run all activation checks
	 *
	 * @since 1.0.0
	 * @return bool|array True if successful, array with error info if failed
	 */
	public function run_activation_checks() {
		// Check WordPress version
		$wp_check = $this->check_wordpress_version();
		if ( true !== $wp_check ) {
			$this->deactivate_plugin();
			return $wp_check;
		}

		// Check WooCommerce availability
		$wc_check = $this->check_woocommerce_exists();
		if ( true !== $wc_check ) {
			$this->deactivate_plugin();
			return $wc_check;
		}

		// Check WooCommerce version
		$wc_version_check = $this->check_woocommerce_version();
		if ( true !== $wc_version_check ) {
			$this->deactivate_plugin();
			return $wc_version_check;
		}

		// Run installer
		$this->run_installer();

		return true;
	}

	/**
	 * Check WordPress version compatibility
	 *
	 * @since 1.0.0
	 * @return bool|array True if compatible, error array if not
	 */
	protected function check_wordpress_version() {
		$current_version = $this->get_wordpress_version();
		$required_version = KICKBOX_INTEGRATION_REQUIRED_WP_VERSION;

		if ( version_compare( $current_version, $required_version, '<' ) ) {
			/* translators: 1: Required WordPress version, 2: Current WordPress version */
			$message = __( 'Kickbox Integration requires WordPress version %1$s or higher. You are running version %2$s.', 'kickbox-integration' );
			return array(
				'error' => 'wordpress_version',
				'message' => sprintf(
					$message,
					$required_version,
					$current_version
				),
			);
		}

		return true;
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @since 1.0.0
	 * @return bool|array True if active, error array if not
	 */
	protected function check_woocommerce_exists() {
		if ( ! $this->is_woocommerce_active() ) {
			return array(
				'error' => 'woocommerce_missing',
				'message' => __( 'Kickbox Integration requires WooCommerce to be installed and active.', 'kickbox-integration' ),
			);
		}

		return true;
	}

	/**
	 * Check WooCommerce version compatibility
	 *
	 * @since 1.0.0
	 * @return bool|array True if compatible, error array if not
	 */
	protected function check_woocommerce_version() {
		$current_version = $this->get_woocommerce_version();
		$required_version = KICKBOX_INTEGRATION_REQUIRED_WC_VERSION;

		if ( $current_version && version_compare( $current_version, $required_version, '<' ) ) {
			/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
			$message = __( 'Kickbox Integration requires WooCommerce version %1$s or higher. You are running version %2$s.', 'kickbox-integration' );
			return array(
				'error' => 'woocommerce_version',
				'message' => sprintf(
					$message,
					$required_version,
					$current_version
				),
			);
		}

		return true;
	}

	/**
	 * Get WordPress version
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_wordpress_version() {
		return get_bloginfo( 'version' );
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	protected function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get WooCommerce version
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	protected function get_woocommerce_version() {
		return defined( 'WC_VERSION' ) ? WC_VERSION : null;
	}

	/**
	 * Deactivate the plugin
	 *
	 * @since 1.0.0
	 */
	protected function deactivate_plugin() {
		deactivate_plugins( KICKBOX_INTEGRATION_PLUGIN_BASENAME );
	}

	/**
	 * Run the installer
	 *
	 * @since 1.0.0
	 */
	protected function run_installer() {
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-installer.php';
		Kickbox_Integration_Installer::install();
	}
}

