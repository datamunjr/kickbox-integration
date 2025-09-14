<?php
/**
 * WCKB_Verification Class
 *
 * Handles email verification using Kickbox API
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCKB_Verification {
    
    private $api_key;
    private $sandbox_mode;
    private $api_url;
    
    public function __construct() {
        $this->api_key = get_option('wckb_api_key', '');
        $this->sandbox_mode = get_option('wckb_sandbox_mode', 'yes');
        $this->api_url = $this->sandbox_mode === 'yes' ? 'https://api.kickbox.com/v2/verify' : 'https://api.kickbox.com/v2/verify';
        
        add_action('wp_ajax_wckb_verify_email', array($this, 'ajax_verify_email'));
        add_action('wp_ajax_nopriv_wckb_verify_email', array($this, 'ajax_verify_email'));
    }
    
    /**
     * Verify a single email address
     *
     * @param string $email Email address to verify
     * @param array $options Additional verification options
     * @return array|WP_Error Verification result or error
     */
    public function verify_email($email, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Kickbox API key is not configured.', 'wckb'));
        }
        
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address format.', 'wckb'));
        }
        
        $default_options = array(
            'timeout' => 30,
            'timeout' => 30,
            'timeout' => 30
        );
        
        $options = wp_parse_args($options, $default_options);
        
        $url = add_query_arg(array(
            'email' => $email,
            'apikey' => $this->api_key,
            'timeout' => $options['timeout']
        ), $this->api_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => $options['timeout'],
            'headers' => array(
                'User-Agent' => 'WooCommerce-Kickbox-Integration/' . WCKB_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', __('Invalid response from Kickbox API.', 'wckb'));
        }
        
        // Log the verification
        $this->log_verification($email, $data);
        
        return $data;
    }
    
    /**
     * Verify multiple email addresses using batch API
     *
     * @param array $emails Array of email addresses
     * @return array|WP_Error Batch verification result or error
     */
    public function verify_batch($emails) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Kickbox API key is not configured.', 'wckb'));
        }
        
        if (!is_array($emails) || empty($emails)) {
            return new WP_Error('invalid_emails', __('No valid email addresses provided.', 'wckb'));
        }
        
        // Validate email addresses
        $valid_emails = array();
        foreach ($emails as $email) {
            if (is_email($email)) {
                $valid_emails[] = $email;
            }
        }
        
        if (empty($valid_emails)) {
            return new WP_Error('no_valid_emails', __('No valid email addresses found.', 'wckb'));
        }
        
        $batch_url = 'https://api.kickbox.com/v2/verify-batch';
        
        $response = wp_remote_post($batch_url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WooCommerce-Kickbox-Integration/' . WCKB_VERSION
            ),
            'body' => json_encode(array(
                'emails' => $valid_emails,
                'apikey' => $this->api_key
            ))
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', __('Invalid response from Kickbox batch API.', 'wckb'));
        }
        
        // Log batch verifications
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $result) {
                if (isset($result['email'])) {
                    $this->log_verification($result['email'], $result);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * AJAX handler for email verification
     */
    public function ajax_verify_email() {
        check_ajax_referer('wckb_verify_email', 'nonce');
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error(array('message' => __('Email address is required.', 'wckb')));
        }
        
        $result = $this->verify_email($email);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Log verification result to database
     *
     * @param string $email Email address
     * @param array $result Verification result
     * @param int $user_id User ID (optional)
     * @param int $order_id Order ID (optional)
     */
    private function log_verification($email, $result, $user_id = null, $order_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wckb_verification_log';
        
        $verification_result = $result['result'] ?? 'unknown';
        $verification_data = json_encode($result);
        
        $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'verification_result' => $verification_result,
                'verification_data' => $verification_data,
                'user_id' => $user_id,
                'order_id' => $order_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s')
        );
    }
    
    /**
     * Get verification history for an email
     *
     * @param string $email Email address
     * @return array Verification history
     */
    public function get_verification_history($email) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wckb_verification_log';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s ORDER BY created_at DESC",
            $email
        ));
        
        return $results;
    }
    
    /**
     * Get verification statistics
     *
     * @return array Statistics
     */
    public function get_verification_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wckb_verification_log';
        
        $stats = $wpdb->get_results(
            "SELECT verification_result, COUNT(*) as count FROM $table_name GROUP BY verification_result"
        );
        
        return $stats;
    }
    
    /**
     * Check if email verification is enabled
     *
     * @return bool
     */
    public function is_verification_enabled() {
        return get_option('wckb_enable_checkout_verification', 'no') === 'yes';
    }
    
    /**
     * Get action for verification result
     *
     * @param string $result Verification result (deliverable, undeliverable, risky, unknown)
     * @return string Action (allow, block, review)
     */
    public function get_action_for_result($result) {
        $option_map = array(
            'deliverable' => 'wckb_deliverable_action',
            'undeliverable' => 'wckb_undeliverable_action',
            'risky' => 'wckb_risky_action',
            'unknown' => 'wckb_unknown_action'
        );
        
        $option = $option_map[$result] ?? 'wckb_unknown_action';
        
        return get_option($option, 'allow');
    }
}
