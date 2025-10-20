<?php
/**
 * AJAX Handler for Flagged Emails functionality
 *
 * @package Kickbox_Integration
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Flagged Emails AJAX Handler Class
 *
 * Handles all AJAX requests related to flagged emails functionality
 *
 * @since 1.0.0
 */
class Kickbox_Integration_Flagged_Emails_Ajax_Handler {

    /**
     * Table instance
     *
     * @var Kickbox_Integration_Flagged_Emails_Table
     */
    private $table_name;

    /**
     * Constructor
     *
     * @param Kickbox_Integration_Flagged_Emails_Table $table Table instance
     */
    public function __construct() {
	    global $wpdb;
	    $this->table_name = $wpdb->prefix . 'kickbox_integration_flagged_emails';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_modal_scripts' ) );
        add_action( 'wp_ajax_kickbox_get_flagged_email_details', array( $this, 'get_flagged_email_details' ) );
        add_action( 'wp_ajax_kickbox_save_flagged_email_decision', array( $this, 'save_flagged_email_decision' ) );
        add_action( 'admin_notices', array( $this, 'display_ajax_notices' ) );
    }

    /**
     * Enqueue modal scripts and localize AJAX data
     */
    public function enqueue_modal_scripts( $hook ) {
        // Only enqueue on our admin page
        if ( $hook !== 'woocommerce_page_kickbox-flagged-emails' ) {
            return;
        }

        // Enqueue modal CSS
        wp_enqueue_style(
            'kickbox-flagged-emails-modal-styles',
            KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/css/flagged-emails-modal-styles.css',
            array(),
            KICKBOX_INTEGRATION_VERSION
        );

        // Enqueue React modal script
        wp_enqueue_script(
            'kickbox-flagged-emails-modal',
            KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/js/flagged-emails-modal.js',
            array( 'wp-element', 'wp-components', 'react', 'react-dom' ),
            KICKBOX_INTEGRATION_VERSION,
            true
        );

        // Enqueue click handler script
        wp_enqueue_script(
            'kickbox-flagged-emails-handler',
            KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/js/flagged-emails-handler.js',
            array( 'kickbox-flagged-emails-modal' ),
            KICKBOX_INTEGRATION_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script( 'kickbox-flagged-emails-handler', 'kickboxAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'kickbox_get_details' ),
            'modalNonce' => wp_create_nonce( 'kickbox_modal_decision' ),
            'page' => 'kickbox-flagged-emails'
        ) );
    }

    /**
     * Get flagged email details for modal
     */
    public function get_flagged_email_details() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'kickbox_get_details' ) ) {
            $error_message = __( 'Security check failed. Please refresh the page and try again.', 'kickbox-integration' );
            set_transient( 'kickbox_ajax_notice', array(
                'type' => 'error',
                'message' => $error_message
            ), 30 );
            wp_send_json_error( array( 'message' => $error_message ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            $error_message = __( 'You do not have permission to perform this action.', 'kickbox-integration' );
            set_transient( 'kickbox_ajax_notice', array(
                'type' => 'error',
                'message' => $error_message
            ), 30 );
            wp_send_json_error( array( 'message' => $error_message ) );
        }

        $item_id = intval( $_POST['item_id'] );
        if ( ! $item_id ) {
            $error_message = __( 'Invalid item ID provided.', 'kickbox-integration' );
            set_transient( 'kickbox_ajax_notice', array(
                'type' => 'error',
                'message' => $error_message
            ), 30 );
            wp_send_json_error( array( 'message' => $error_message ) );
        }

        // Get the flagged email data
        global $wpdb;
        $item = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $item_id
        ), ARRAY_A );

        if ( ! $item ) {
            $error_message = __( 'Flagged email not found. It may have been deleted.', 'kickbox-integration' );
            set_transient( 'kickbox_ajax_notice', array(
                'type' => 'error',
                'message' => $error_message
            ), 30 );
            wp_send_json_error( array( 'message' => $error_message ) );
        }

        // Parse kickbox_result if it's JSON
        if ( is_string( $item['kickbox_result'] ) ) {
            $item['kickbox_result'] = json_decode( $item['kickbox_result'], true );
        }

        wp_send_json_success( $item );
    }

    /**
     * Save flagged email decision from modal
     */
    public function save_flagged_email_decision() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['kickbox_modal_nonce'], 'kickbox_modal_decision' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $item_id = intval( $_POST['kickbox_item_id'] );
        $decision = sanitize_text_field( $_POST['kickbox_decision'] );
        $admin_notes = sanitize_textarea_field( $_POST['kickbox_admin_notes'] ?? '' );

        if ( ! $item_id || ! in_array( $decision, array( 'allow', 'block' ) ) ) {
            wp_send_json_error( array( 'message' => 'Invalid data provided' ) );
        }

        // Update the decision
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array(
                'admin_decision' => $decision,
                'admin_notes' => $admin_notes,
                'reviewed_date' => current_time( 'mysql' ),
                'reviewed_by' => get_current_user_id()
            ),
            array( 'id' => $item_id ),
            array( '%s', '%s', '%s', '%d' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            // Get the email address for the success message
            $email_address = $wpdb->get_var( $wpdb->prepare(
                "SELECT email FROM {$this->table_name} WHERE id = %d",
                $item_id
            ) );
            
            // Set success notice
            $notice_message = sprintf( 
                __( 'Email decision for %s updated successfully to %s.', 'kickbox-integration' ), 
                $email_address,
                ucfirst( $decision ) 
            );
            if ( ! empty( $admin_notes ) ) {
                $notice_message .= ' ' . __( 'Admin notes have been saved.', 'kickbox-integration' );
            }
            
            set_transient( 'kickbox_ajax_notice', array(
                'type' => 'success',
                'message' => $notice_message
            ), 30 );
            
            wp_send_json_success( array( 
                'message' => $notice_message,
                'decision' => $decision,
                'notes' => $admin_notes,
                'email' => $email_address
            ) );
        } else {
            // Set error notice
            $error_message = __( 'Failed to update email decision. Please try again.', 'kickbox-integration' );
            set_transient( 'kickbox_ajax_notice', array(
                'type' => 'error',
                'message' => $error_message
            ), 30 );
            
            wp_send_json_error( array( 'message' => $error_message ) );
        }
    }

    /**
     * Display AJAX admin notices
     */
    public function display_ajax_notices() {
        // Only show on the flagged emails page
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'woocommerce_page_kickbox-flagged-emails' ) {
            return;
        }

        $notice = get_transient( 'kickbox_ajax_notice' );
        if ( $notice ) {
            $class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
            
            // Clear the transient after displaying
            delete_transient( 'kickbox_ajax_notice' );
        }
    }
}
