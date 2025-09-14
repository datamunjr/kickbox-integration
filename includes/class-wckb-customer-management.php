<?php
/**
 * WCKB_Customer_Management Class
 *
 * Handles customer management integration with verification status
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCKB_Customer_Management {
    
    private $verification;
    
    public function __construct() {
        $this->verification = new WCKB_Verification();
        
        add_filter('manage_users_columns', array($this, 'add_verification_column'));
        add_filter('manage_users_custom_column', array($this, 'show_verification_column'), 10, 3);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_customer_scripts'));
        add_action('wp_ajax_wckb_verify_customer_batch', array($this, 'ajax_verify_customer_batch'));
        add_action('admin_footer-users.php', array($this, 'add_bulk_actions'));
        add_action('load-users.php', array($this, 'handle_bulk_actions'));
    }
    
    /**
     * Add verification column to users table
     */
    public function add_verification_column($columns) {
        if (!get_option('wckb_enable_customer_verification', 'no') === 'yes') {
            return $columns;
        }
        
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'email') {
                $new_columns['wckb_verification'] = __('Verified', 'wckb');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Show verification status in column
     */
    public function show_verification_column($value, $column_name, $user_id) {
        if ($column_name !== 'wckb_verification') {
            return $value;
        }
        
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return $value;
        }
        
        $verification_history = $this->verification->get_verification_history($user->user_email);
        
        if (empty($verification_history)) {
            return '<span class="wckb-status wckb-unverified" title="' . esc_attr__('Not verified', 'wckb') . '">' . 
                   __('Not verified', 'wckb') . '</span>';
        }
        
        $latest = $verification_history[0];
        $result = $latest->verification_result;
        $date = date_i18n(get_option('date_format'), strtotime($latest->created_at));
        
        $status_classes = array(
            'deliverable' => 'wckb-deliverable',
            'undeliverable' => 'wckb-undeliverable',
            'risky' => 'wckb-risky',
            'unknown' => 'wckb-unknown'
        );
        
        $status_labels = array(
            'deliverable' => __('Deliverable', 'wckb'),
            'undeliverable' => __('Undeliverable', 'wckb'),
            'risky' => __('Risky', 'wckb'),
            'unknown' => __('Unknown', 'wckb')
        );
        
        $class = $status_classes[$result] ?? 'wckb-unknown';
        $label = $status_labels[$result] ?? __('Unknown', 'wckb');
        
        return '<span class="wckb-status ' . esc_attr($class) . '" title="' . 
               esc_attr(sprintf(__('Last verified: %s', 'wckb'), $date)) . '">' . 
               esc_html($label) . '</span>';
    }
    
    /**
     * Enqueue customer management scripts
     */
    public function enqueue_customer_scripts($hook) {
        if ($hook !== 'users.php') {
            return;
        }
        
        wp_enqueue_script('wckb-customer', WCKB_PLUGIN_URL . 'assets/js/customer.js', array('jquery'), WCKB_VERSION, true);
        wp_enqueue_style('wckb-customer', WCKB_PLUGIN_URL . 'assets/css/customer.css', array(), WCKB_VERSION);
        
        wp_localize_script('wckb-customer', 'wckb_customer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wckb_customer'),
            'strings' => array(
                'verifying' => __('Verifying emails...', 'wckb'),
                'verification_complete' => __('Email verification complete!', 'wckb'),
                'verification_error' => __('Error during verification. Please try again.', 'wckb'),
                'confirm_batch' => __('Are you sure you want to verify the selected emails? This will use verification credits.', 'wckb'),
                'no_emails_selected' => __('Please select at least one user to verify.', 'wckb')
            )
        ));
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions() {
        if (!get_option('wckb_enable_customer_verification', 'no') === 'yes') {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('<option>').val('wckb_verify_emails').text('<?php echo esc_js(__('Verify Emails', 'wckb')); ?>').appendTo('select[name="action"]');
            $('<option>').val('wckb_verify_emails').text('<?php echo esc_js(__('Verify Emails', 'wckb')); ?>').appendTo('select[name="action2"]');
        });
        </script>
        <?php
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions() {
        if (!get_option('wckb_enable_customer_verification', 'no') === 'yes') {
            return;
        }
        
        $wp_list_table = _get_list_table('WP_Users_List_Table');
        $action = $wp_list_table->current_action();
        
        if ($action !== 'wckb_verify_emails') {
            return;
        }
        
        check_admin_referer('bulk-users');
        
        $user_ids = $_REQUEST['users'] ?? array();
        
        if (empty($user_ids)) {
            wp_redirect(add_query_arg('wckb_error', 'no_users', wp_get_referer()));
            exit;
        }
        
        $emails = array();
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user && $user->user_email) {
                $emails[] = $user->user_email;
            }
        }
        
        if (empty($emails)) {
            wp_redirect(add_query_arg('wckb_error', 'no_emails', wp_get_referer()));
            exit;
        }
        
        $result = $this->verification->verify_batch($emails);
        
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('wckb_error', 'verification_failed', wp_get_referer()));
            exit;
        }
        
        wp_redirect(add_query_arg('wckb_success', 'verification_complete', wp_get_referer()));
        exit;
    }
    
    /**
     * AJAX handler for batch verification
     */
    public function ajax_verify_customer_batch() {
        check_ajax_referer('wckb_customer', 'nonce');
        
        if (!current_user_can('manage_users')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wckb')));
        }
        
        $user_ids = $_POST['user_ids'] ?? array();
        
        if (empty($user_ids)) {
            wp_send_json_error(array('message' => __('No users selected.', 'wckb')));
        }
        
        $emails = array();
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user && $user->user_email) {
                $emails[] = $user->user_email;
            }
        }
        
        if (empty($emails)) {
            wp_send_json_error(array('message' => __('No valid email addresses found.', 'wckb')));
        }
        
        $result = $this->verification->verify_batch($emails);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully verified %d email addresses.', 'wckb'), count($emails)),
            'result' => $result
        ));
    }
    
    /**
     * Get verification statistics
     */
    public function get_verification_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wckb_verification_log';
        
        $stats = $wpdb->get_results(
            "SELECT verification_result, COUNT(*) as count FROM $table_name GROUP BY verification_result"
        );
        
        $total_users = count_users();
        $verified_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT email) FROM $table_name"
        );
        
        return array(
            'total_users' => $total_users['total_users'],
            'verified_users' => $verified_users,
            'verification_results' => $stats
        );
    }
}
