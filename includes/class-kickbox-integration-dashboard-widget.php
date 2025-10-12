<?php
/**
 * Kickbox_Integration_Dashboard_Widget Class
 *
 * Handles the WordPress dashboard widget for email verification statistics
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Kickbox_Integration_Dashboard_Widget {

    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
        add_action( 'wp_ajax_kickbox_integration_dashboard_stats', array( $this, 'ajax_get_dashboard_stats' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_scripts' ) );
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            wp_add_dashboard_widget(
                    'kickbox_integration_dashboard_widget',
                    __( 'Kickbox Email Verification Statistics', 'kickbox-integration' ),
                    array( $this, 'dashboard_widget_content' )
            );
        }
    }

    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        ?>
        <div id="kickbox-integration-dashboard-widget">
            <div class="kickbox-integration-dashboard-loading">
                <span class="spinner is-active"></span>
                <?php esc_html_e( 'Loading statistics...', 'kickbox-integration' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue dashboard scripts
     */
    public function enqueue_dashboard_scripts( $hook ) {
        // Only load on dashboard
        if ( 'index.php' !== $hook ) {
            return;
        }

        // Only load if user can manage WooCommerce
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        wp_enqueue_script( 'kickbox-integration-dashboard', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/js/dashboard.js', array( 'jquery' ), KICKBOX_INTEGRATION_VERSION, true );
        wp_enqueue_style( 'kickbox-integration-dashboard', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/css/dashboard.css', array(), KICKBOX_INTEGRATION_VERSION );

        wp_localize_script( 'kickbox-integration-dashboard', 'kickbox_integration_dashboard', array(
                'ajax_url'  => admin_url( 'admin-ajax.php' ),
                'admin_url' => admin_url( 'admin.php?page=wc-settings&tab=kickbox&section=stats' ),
                'nonce'     => wp_create_nonce( 'kickbox_integration_dashboard' ),
                'strings'   => array(
                        'loading'       => __( 'Loading statistics...', 'kickbox-integration' ),
                        'error'         => __( 'Unable to load statistics.', 'kickbox-integration' ),
                        'retry'         => __( 'Retry', 'kickbox-integration' ),
                        'no_data'       => __( 'No verification data available.', 'kickbox-integration' ),
                        'total'         => __( 'Total Verifications', 'kickbox-integration' ),
                        'deliverable'   => __( 'Deliverable', 'kickbox-integration' ),
                        'undeliverable' => __( 'Undeliverable', 'kickbox-integration' ),
                        'risky'         => __( 'Risky', 'kickbox-integration' ),
                        'unknown'       => __( 'Unknown', 'kickbox-integration' ),
                        'view_details'  => __( 'View Details', 'kickbox-integration' )
                )
        ) );
    }

    /**
     * AJAX handler for dashboard statistics
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer( 'kickbox_integration_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $verification = new Kickbox_Integration_Verification();
        $stats        = $verification->get_verification_stats();

        wp_send_json_success( $stats );
    }
}
