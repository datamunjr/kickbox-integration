<?php
/**
 * Plugin Name: Kickbox Integration
 * Plugin URI: https://munjr.com/kickbox-integration
 * Description: Integrates Kickbox email verification service with WooCommerce for real-time email validation during checkout and user registration.
 * Version: 1.0.0
 * Author: munjr llc
 * Author URI: https://munjr.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kickbox-integration
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 10.2
 * WC tested up to: 8.5
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
define( 'KICKBOX_INTEGRATION_REQUIRED_WP_VERSION', '6.2' );
define( 'KICKBOX_INTEGRATION_REQUIRED_WC_VERSION', '10.2' );

/**
 * Main Kickbox Integration Plugin Class
 *
 * @class Kickbox_Integration
 * @version 1.0.0
 */
class Kickbox_Integration {

    /**
     * The single instance of the class
     *
     * @var Kickbox_Integration
     * @since 1.0.0
     */
    protected static $instance = null;

    /**
     * Verification class instance
     *
     * @var Kickbox_Integration_Verification
     * @since 1.0.0
     */
    public $verification;

    /**
     * Admin class instance
     *
     * @var Kickbox_Integration_Admin
     * @since 1.0.0
     */
    public $admin;

    /**
     * Checkout class instance
     *
     * @var Kickbox_Integration_Checkout
     * @since 1.0.0
     */
    public $checkout;

    /**
     * Registration class instance
     *
     * @var Kickbox_Integration_Registration
     * @since 1.0.0
     */
    public $registration;

    /**
     * Dashboard widget class instance
     *
     * @var Kickbox_Integration_Dashboard_Widget
     * @since 1.0.0
     */
    public $dashboard_widget;

    /**
     * Flagged emails class instance
     *
     * @var Kickbox_Integration_Flagged_Emails
     * @since 1.0.0
     */
    public $flagged_emails;

    /**
     * Main Kickbox_Integration Instance
     *
     * Ensures only one instance of Kickbox_Integration is loaded or can be loaded.
     *
     * @return Kickbox_Integration - Main instance
     * @since 1.0.0
     * @static
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Kickbox_Integration Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define additional constants if needed
     *
     * @since 1.0.0
     */
    private function define_constants() {
        // Additional constants can be defined here if needed
    }

	/**
	 * Include required core files
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		// Include installer and activator classes
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-installer.php';
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-activator.php';

		// Include core classes
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-verification.php';
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-admin.php';
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-checkout.php';
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-registration.php';
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-dashboard-widget.php';
		require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-flagged-emails.php';
	}

    /**
     * Hook into actions and filters
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Initialize components
        add_action( 'plugins_loaded', array( $this, 'init_components' ) );

        // Plugin row meta
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
        add_action( 'after_plugin_row_' . KICKBOX_INTEGRATION_PLUGIN_BASENAME, array(
                $this,
                'plugin_row_dependency_notice'
        ) );
    }

    /**
     * Initialize plugin components
     *
     * @since 1.0.0
     */
    public function init_components() {
        // Instantiate components
        $this->verification     = new Kickbox_Integration_Verification();
        $this->admin            = new Kickbox_Integration_Admin();
        $this->checkout         = new Kickbox_Integration_Checkout();
        $this->registration     = new Kickbox_Integration_Registration();
        $this->dashboard_widget = new Kickbox_Integration_Dashboard_Widget();
        $this->flagged_emails   = new Kickbox_Integration_Flagged_Emails();
    }

    /**
     * Check if dependencies are met
     *
     * @return bool True if all dependencies are met
     * @since 1.0.0
     */
    public static function check_dependencies() {
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), KICKBOX_INTEGRATION_REQUIRED_WP_VERSION, '<' ) ) {
            return false;
        }

        // Check WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return false;
        }

        // Check WooCommerce version
        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, KICKBOX_INTEGRATION_REQUIRED_WC_VERSION, '<' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     * @since 1.0.0
     */
    public static function is_woocommerce_active() {
        return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' );
    }

    /**
     * Add dependency information to plugin row meta
     *
     * @param array $plugin_meta Plugin meta data
     * @param string $plugin_file Plugin file path
     *
     * @return array Modified plugin meta
     * @since 1.0.0
     */
    public function plugin_row_meta( $plugin_meta, $plugin_file ) {
        if ( KICKBOX_INTEGRATION_PLUGIN_BASENAME === $plugin_file ) {
            // Add WooCommerce dependency information
            $woocommerce_status = class_exists( 'WooCommerce' )
                    ? '<span style="color: #46b450;">✓ WooCommerce Active</span>'
                    : '<span style="color: #dc3232;">✗ WooCommerce Required</span>';

            $plugin_meta[] = $woocommerce_status;

            // Add version requirement if WooCommerce is active
            if ( class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) ) {
                $current_wc_version = WC_VERSION;
                $version_status     = version_compare( $current_wc_version, KICKBOX_INTEGRATION_REQUIRED_WC_VERSION, '>=' )
                        ? '<span style="color: #46b450;">✓ WC ' . $current_wc_version . '</span>'
                        : '<span style="color: #dc3232;">✗ WC ' . $current_wc_version . ' (Requires ' . KICKBOX_INTEGRATION_REQUIRED_WC_VERSION . '+)</span>';

                $plugin_meta[] = $version_status;
            }
        }

        return $plugin_meta;
    }

    /**
     * Get WooCommerce dependency status
     *
     * @return array Array with 'active' and 'version_ok' keys
     * @since 1.0.0
     */
    protected function get_woocommerce_dependency_status() {
        $woocommerce_active     = self::is_woocommerce_active();
        $woocommerce_version_ok = false;

        if ( $woocommerce_active ) {
            $woocommerce_version_ok = version_compare( WC()->version, KICKBOX_INTEGRATION_REQUIRED_WC_VERSION, '>=' );
        }

        return array(
                'active'     => $woocommerce_active,
                'version_ok' => $woocommerce_version_ok
        );
    }

    /**
     * Display dependency notice below plugin row
     *
     * @since 1.0.0
     */
    public function plugin_row_dependency_notice() {
        $status = $this->get_woocommerce_dependency_status();

        // Only show notice if there are dependency issues
        if ( ! $status['active'] || ! $status['version_ok'] ) {
            ?>
            <tr class="plugin-update-tr">
                <td colspan="3" class="plugin-update">
                    <div class="update-message notice inline notice-warning notice-alt">
                        <p>
                            <strong><?php esc_html_e( 'Dependencies:', 'kickbox-integration' ); ?></strong>
                            <?php if ( ! $status['active'] ) : ?>
                                <?php esc_html_e( 'WooCommerce is required but not active.', 'kickbox-integration' ); ?>
                                <a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
                                    <?php esc_html_e( 'Install WooCommerce', 'kickbox-integration' ); ?>
                                </a>
                            <?php elseif ( ! $status['version_ok'] ) : ?>
                                <?php
                                printf(
                                        esc_html__( 'WooCommerce version %s is required. You have version %s.', 'kickbox-integration' ),
                                        KICKBOX_INTEGRATION_REQUIRED_WC_VERSION . '+',
                                        defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown'
                                );
                                ?>
                                <a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>">
                                    <?php esc_html_e( 'Update WooCommerce', 'kickbox-integration' ); ?>
                                </a>
                            <?php endif; ?>
                        </p>
                    </div>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Display WordPress version notice
     *
     * @since 1.0.0
     */
    public static function display_wordpress_version_notice() {
        $current_wp_version = get_bloginfo( 'version' );
        ?>
        <div class="error">
            <p>
                <strong><?php esc_html_e( 'Kickbox Integration', 'kickbox-integration' ); ?></strong>
                <?php
                printf(
                        esc_html__( 'requires WordPress version %s or higher. You are running version %s.', 'kickbox-integration' ),
                        KICKBOX_INTEGRATION_REQUIRED_WP_VERSION,
                        $current_wp_version
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display WordPress version deactivated notice
     *
     * @since 1.0.0
     */
    public static function display_wordpress_version_deactivated_notice() {
        ?>
        <div class="error">
            <p>
                <strong><?php esc_html_e( 'Kickbox Integration', 'kickbox-integration' ); ?></strong>
                <?php
                printf(
                        esc_html__( 'has been deactivated because the WordPress version is too old. Please upgrade WordPress to at least version %s.', 'kickbox-integration' ),
                        KICKBOX_INTEGRATION_REQUIRED_WP_VERSION
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display WooCommerce missing notice
     *
     * @since 1.0.0
     */
    public static function display_woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p>
                <strong><?php esc_html_e( 'Kickbox Integration', 'kickbox-integration' ); ?></strong>
                <?php esc_html_e( 'requires WooCommerce to be installed and active.', 'kickbox-integration' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display WooCommerce deactivated notice
     *
     * @since 1.0.0
     */
    public static function display_woocommerce_deactivated_notice() {
        ?>
        <div class="error">
            <p>
                <strong><?php esc_html_e( 'Kickbox Integration', 'kickbox-integration' ); ?></strong>
                <?php esc_html_e( 'has been deactivated because WooCommerce is no longer active.', 'kickbox-integration' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display WooCommerce version notice
     *
     * @since 1.0.0
     */
    public static function display_woocommerce_version_notice() {
        $current_version = defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown';
        ?>
        <div class="error">
            <p>
                <strong><?php esc_html_e( 'Kickbox Integration', 'kickbox-integration' ); ?></strong>
                <?php
                printf(
                        esc_html__( 'requires WooCommerce version %s or higher. You are running version %s.', 'kickbox-integration' ),
                        KICKBOX_INTEGRATION_REQUIRED_WC_VERSION,
                        $current_version
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display WooCommerce version deactivated notice
     *
     * @since 1.0.0
     */
    public static function display_woocommerce_version_deactivated_notice() {
        ?>
        <div class="error">
            <p>
                <strong><?php esc_html_e( 'Kickbox Integration', 'kickbox-integration' ); ?></strong>
                <?php esc_html_e( 'has been deactivated because WooCommerce version is too old.', 'kickbox-integration' ); ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Returns the main instance of Kickbox_Integration
 *
 * @return Kickbox_Integration
 * @since 1.0.0
 */
function KICKBOX() {
    return Kickbox_Integration::instance();
}

/**
 * Validate dependencies and initialize plugin
 *
 * @since 1.0.0
 */
function kickbox_integration_validate_and_init() {
    // Check WordPress version compatibility
    if ( version_compare( get_bloginfo( 'version' ), KICKBOX_INTEGRATION_REQUIRED_WP_VERSION, '<' ) ) {
        add_action( 'admin_notices', array( 'Kickbox_Integration', 'display_wordpress_version_notice' ) );

        // If WordPress version is too old, deactivate the plugin
        if ( is_plugin_active( KICKBOX_INTEGRATION_PLUGIN_BASENAME ) ) {
            deactivate_plugins( KICKBOX_INTEGRATION_PLUGIN_BASENAME );
            add_action( 'admin_notices', array(
                    'Kickbox_Integration',
                    'display_wordpress_version_deactivated_notice'
            ) );
        }

        return;
    }

    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', array( 'Kickbox_Integration', 'display_woocommerce_missing_notice' ) );

        // If WooCommerce is not available, deactivate the plugin
        if ( is_plugin_active( KICKBOX_INTEGRATION_PLUGIN_BASENAME ) ) {
            deactivate_plugins( KICKBOX_INTEGRATION_PLUGIN_BASENAME );
            add_action( 'admin_notices', array( 'Kickbox_Integration', 'display_woocommerce_deactivated_notice' ) );
        }

        return;
    }

    // Check WooCommerce version compatibility
    if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, KICKBOX_INTEGRATION_REQUIRED_WC_VERSION, '<' ) ) {
        add_action( 'admin_notices', array( 'Kickbox_Integration', 'display_woocommerce_version_notice' ) );

        // If WooCommerce version is too old, deactivate the plugin
        if ( is_plugin_active( KICKBOX_INTEGRATION_PLUGIN_BASENAME ) ) {
            deactivate_plugins( KICKBOX_INTEGRATION_PLUGIN_BASENAME );
            add_action( 'admin_notices', array(
                    'Kickbox_Integration',
                    'display_woocommerce_version_deactivated_notice'
            ) );
        }

        return;
    }

    // Initialize the plugin
    KICKBOX();
}

/**
 * Plugin activation hook
 *
 * @since 1.0.0
 */
function kickbox_integration_activate() {
	require_once KICKBOX_INTEGRATION_PLUGIN_DIR . 'includes/class-kickbox-integration-activator.php';
	
	$result = Kickbox_Integration_Activator::activate();
	
	// If activation failed, display error and die
	if ( true !== $result && is_array( $result ) ) {
		wp_die(
			'<h1>' . esc_html__( 'Plugin Activation Error', 'kickbox-integration' ) . '</h1>' .
			'<p>' . esc_html( $result['message'] ) . '</p>' .
			'<p>' . esc_html__( 'Please resolve the issue and try activating the plugin again.', 'kickbox-integration' ) . '</p>' .
			'<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Return to Plugins page', 'kickbox-integration' ) . '</a></p>',
			esc_html__( 'Plugin Activation Error', 'kickbox-integration' ),
			array( 'back_link' => true )
		);
	}
}

/**
 * Plugin deactivation hook
 *
 * @since 1.0.0
 */
function kickbox_integration_deactivate() {
	// Clear any caches when plugin is deactivated
	wp_cache_flush();
}

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'kickbox_integration_activate' );
register_deactivation_hook( __FILE__, 'kickbox_integration_deactivate' );

// Initialize the plugin
add_action( 'plugins_loaded', 'kickbox_integration_validate_and_init' );
