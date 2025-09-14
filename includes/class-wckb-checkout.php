<?php
/**
 * WCKB_Checkout Class
 *
 * Handles checkout email verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCKB_Checkout {
    
    private $verification;
    
    public function __construct() {
        $this->verification = new WCKB_Verification();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_email'));
        add_action('woocommerce_after_checkout_validation', array($this, 'after_checkout_validation'));
    }
    
    /**
     * Enqueue checkout scripts
     */
    public function enqueue_checkout_scripts() {
        if (!is_checkout() || !$this->verification->is_verification_enabled()) {
            return;
        }
        
        wp_enqueue_script('wckb-checkout', WCKB_PLUGIN_URL . 'assets/js/checkout.js', array('jquery', 'wc-checkout'), WCKB_VERSION, true);
        wp_enqueue_style('wckb-checkout', WCKB_PLUGIN_URL . 'assets/css/checkout.css', array(), WCKB_VERSION);
        
        wp_localize_script('wckb-checkout', 'wckb_checkout', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wckb_verify_email'),
            'strings' => array(
                'verifying' => __('Verifying email...', 'wckb'),
                'verification_failed' => __('Email verification failed. Please check your email address.', 'wckb'),
                'verification_error' => __('Unable to verify email at this time. Please try again.', 'wckb'),
                'deliverable' => __('Email verified successfully.', 'wckb'),
                'undeliverable' => __('This email address appears to be invalid.', 'wckb'),
                'risky' => __('This email address may be risky.', 'wckb'),
                'unknown' => __('Unable to verify this email address.', 'wckb')
            )
        ));
    }
    
    /**
     * Validate checkout email
     */
    public function validate_checkout_email() {
        if (!$this->verification->is_verification_enabled()) {
            return;
        }
        
        $email = $_POST['billing_email'] ?? '';
        
        if (empty($email)) {
            return;
        }
        
        $result = $this->verification->verify_email($email);
        
        if (is_wp_error($result)) {
            // Log error but don't block checkout
            error_log('WCKB Verification Error: ' . $result->get_error_message());
            return;
        }
        
        $verification_result = $result['result'] ?? 'unknown';
        $action = $this->verification->get_action_for_result($verification_result);
        
        switch ($action) {
            case 'block':
                $this->block_checkout($verification_result, $result);
                break;
            case 'review':
                $this->flag_for_review($email, $verification_result, $result);
                break;
            case 'allow':
            default:
                // Allow checkout to proceed
                break;
        }
    }
    
    /**
     * After checkout validation
     */
    public function after_checkout_validation($data, $errors) {
        if (!$this->verification->is_verification_enabled()) {
            return;
        }
        
        $email = $data['billing_email'] ?? '';
        
        if (empty($email)) {
            return;
        }
        
        // Log verification for completed orders
        $result = $this->verification->verify_email($email);
        
        if (!is_wp_error($result)) {
            // Store verification result in order meta
            add_action('woocommerce_checkout_order_processed', function($order_id) use ($result) {
                update_post_meta($order_id, '_wckb_verification_result', $result['result'] ?? 'unknown');
                update_post_meta($order_id, '_wckb_verification_data', json_encode($result));
            });
        }
    }
    
    /**
     * Block checkout due to verification failure
     */
    private function block_checkout($result, $verification_data) {
        $messages = array(
            'undeliverable' => __('This email address appears to be invalid and cannot receive emails. Please use a different email address.', 'wckb'),
            'risky' => __('This email address has been flagged as potentially risky. Please use a different email address.', 'wckb'),
            'unknown' => __('We were unable to verify this email address. Please use a different email address.', 'wckb')
        );
        
        $message = $messages[$result] ?? $messages['unknown'];
        
        wc_add_notice($message, 'error');
    }
    
    /**
     * Flag email for review
     */
    private function flag_for_review($email, $result, $verification_data) {
        // Store in order meta for admin review
        add_action('woocommerce_checkout_order_processed', function($order_id) use ($email, $result, $verification_data) {
            update_post_meta($order_id, '_wckb_needs_review', 'yes');
            update_post_meta($order_id, '_wckb_verification_result', $result);
            update_post_meta($order_id, '_wckb_verification_data', json_encode($verification_data));
            
            // Add admin note
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note(
                    sprintf(
                        __('Email verification flagged for review: %s (Result: %s)', 'wckb'),
                        $email,
                        $result
                    )
                );
            }
        });
    }
    
    /**
     * Get verification status for order
     */
    public static function get_order_verification_status($order_id) {
        $result = get_post_meta($order_id, '_wckb_verification_result', true);
        $needs_review = get_post_meta($order_id, '_wckb_needs_review', true);
        
        return array(
            'result' => $result,
            'needs_review' => $needs_review === 'yes',
            'data' => get_post_meta($order_id, '_wckb_verification_data', true)
        );
    }
}
