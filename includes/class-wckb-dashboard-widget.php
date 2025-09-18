<?php
/**
 * WCKB_Dashboard_Widget Class
 *
 * Handles the WordPress dashboard widget for email verification statistics
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCKB_Dashboard_Widget {

    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
        add_action( 'wp_ajax_wckb_dashboard_stats', array( $this, 'ajax_get_dashboard_stats' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_scripts' ) );
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            wp_add_dashboard_widget(
                    'wckb_dashboard_widget',
                    __( 'Kickbox Email Verification Statistics', 'wckb' ),
                    array( $this, 'dashboard_widget_content' )
            );
        }
    }

    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        ?>
        <div id="wckb-dashboard-widget">
            <div class="wckb-dashboard-loading">
                <span class="spinner is-active"></span>
                <?php _e( 'Loading statistics...', 'wckb' ); ?>
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

        wp_enqueue_script( 'wckb-dashboard', WCKB_PLUGIN_URL . 'assets/js/dashboard.js', array( 'jquery' ), WCKB_VERSION, true );
        wp_enqueue_style( 'wckb-dashboard', WCKB_PLUGIN_URL . 'assets/css/dashboard.css', array(), WCKB_VERSION );

        wp_localize_script( 'wckb-dashboard', 'wckb_dashboard', array(
                'ajax_url'  => admin_url( 'admin-ajax.php' ),
                'admin_url' => admin_url( 'admin.php?page=wckb-settings&tab=stats' ),
                'nonce'     => wp_create_nonce( 'wckb_dashboard' ),
                'strings'   => array(
                        'loading'       => __( 'Loading statistics...', 'wckb' ),
                        'error'         => __( 'Unable to load statistics.', 'wckb' ),
                        'retry'         => __( 'Retry', 'wckb' ),
                        'no_data'       => __( 'No verification data available.', 'wckb' ),
                        'total'         => __( 'Total Verifications', 'wckb' ),
                        'deliverable'   => __( 'Deliverable', 'wckb' ),
                        'undeliverable' => __( 'Undeliverable', 'wckb' ),
                        'risky'         => __( 'Risky', 'wckb' ),
                        'unknown'       => __( 'Unknown', 'wckb' ),
                        'view_details'  => __( 'View Details', 'wckb' )
                )
        ) );
    }

    /**
     * AJAX handler for dashboard statistics
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer( 'wckb_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wckb' ) ) );
        }

        $verification = new WCKB_Verification();
        $stats        = $verification->get_verification_stats();

        wp_send_json_success( $stats );
    }
}
