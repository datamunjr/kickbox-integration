<?php
/**
 * WCKB_Admin Class
 *
 * Handles admin functionality and settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCKB_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wckb_test_api', array($this, 'ajax_test_api'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Kickbox Integration', 'wckb'),
            __('Kickbox Integration', 'wckb'),
            'manage_woocommerce',
            'wckb-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wckb_settings', 'wckb_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('wckb_settings', 'wckb_sandbox_mode', array(
            'type' => 'string',
            'default' => 'yes'
        ));
        
        register_setting('wckb_settings', 'wckb_deliverable_action', array(
            'type' => 'string',
            'default' => 'allow'
        ));
        
        register_setting('wckb_settings', 'wckb_undeliverable_action', array(
            'type' => 'string',
            'default' => 'allow'
        ));
        
        register_setting('wckb_settings', 'wckb_risky_action', array(
            'type' => 'string',
            'default' => 'allow'
        ));
        
        register_setting('wckb_settings', 'wckb_unknown_action', array(
            'type' => 'string',
            'default' => 'allow'
        ));
        
        register_setting('wckb_settings', 'wckb_enable_checkout_verification', array(
            'type' => 'string',
            'default' => 'no'
        ));
        
        register_setting('wckb_settings', 'wckb_enable_customer_verification', array(
            'type' => 'string',
            'default' => 'no'
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_wckb-settings') {
            return;
        }
        
        wp_enqueue_script('wckb-admin', WCKB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WCKB_VERSION, true);
        wp_enqueue_style('wckb-admin', WCKB_PLUGIN_URL . 'assets/css/admin.css', array(), WCKB_VERSION);
        
        wp_localize_script('wckb-admin', 'wckb_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wckb_admin'),
            'strings' => array(
                'testing_api' => __('Testing API connection...', 'wckb'),
                'api_success' => __('API connection successful!', 'wckb'),
                'api_error' => __('API connection failed. Please check your API key.', 'wckb'),
                'confirm_test' => __('Are you sure you want to test the API connection? This will use 1 verification credit.', 'wckb')
            )
        ));
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Kickbox Integration Settings', 'wckb'); ?></h1>
            
            <div id="wckb-admin-app"></div>
            
            <form method="post" action="options.php" id="wckb-settings-form" style="display: none;">
                <?php
                settings_fields('wckb_settings');
                do_settings_sections('wckb_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wckb_api_key"><?php echo esc_html__('API Key', 'wckb'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="wckb_api_key" name="wckb_api_key" 
                                   value="<?php echo esc_attr(get_option('wckb_api_key', '')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php echo esc_html__('Enter your Kickbox API key. You can find this in your Kickbox dashboard.', 'wckb'); ?>
                            </p>
                            <button type="button" id="wckb-test-api" class="button button-secondary">
                                <?php echo esc_html__('Test API Connection', 'wckb'); ?>
                            </button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wckb_sandbox_mode"><?php echo esc_html__('Sandbox Mode', 'wckb'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="wckb_sandbox_mode" name="wckb_sandbox_mode" 
                                   value="yes" <?php checked(get_option('wckb_sandbox_mode', 'yes'), 'yes'); ?> />
                            <label for="wckb_sandbox_mode">
                                <?php echo esc_html__('Enable sandbox mode for testing (recommended)', 'wckb'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wckb_enable_checkout_verification"><?php echo esc_html__('Checkout Verification', 'wckb'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="wckb_enable_checkout_verification" name="wckb_enable_checkout_verification" 
                                   value="yes" <?php checked(get_option('wckb_enable_checkout_verification', 'no'), 'yes'); ?> />
                            <label for="wckb_enable_checkout_verification">
                                <?php echo esc_html__('Enable email verification during checkout', 'wckb'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wckb_enable_customer_verification"><?php echo esc_html__('Customer Verification', 'wckb'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="wckb_enable_customer_verification" name="wckb_enable_customer_verification" 
                                   value="yes" <?php checked(get_option('wckb_enable_customer_verification', 'no'), 'yes'); ?> />
                            <label for="wckb_enable_customer_verification">
                                <?php echo esc_html__('Enable batch verification for existing customers', 'wckb'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php echo esc_html__('Verification Actions', 'wckb'); ?></h2>
                <p><?php echo esc_html__('Configure what action to take for each verification result:', 'wckb'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wckb_deliverable_action"><?php echo esc_html__('Deliverable Emails', 'wckb'); ?></label>
                        </th>
                        <td>
                            <select id="wckb_deliverable_action" name="wckb_deliverable_action">
                                <option value="allow" <?php selected(get_option('wckb_deliverable_action', 'allow'), 'allow'); ?>>
                                    <?php echo esc_html__('Allow checkout', 'wckb'); ?>
                                </option>
                                <option value="block" <?php selected(get_option('wckb_deliverable_action', 'allow'), 'block'); ?>>
                                    <?php echo esc_html__('Block checkout', 'wckb'); ?>
                                </option>
                                <option value="review" <?php selected(get_option('wckb_deliverable_action', 'allow'), 'review'); ?>>
                                    <?php echo esc_html__('Allow but flag for review', 'wckb'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wckb_undeliverable_action"><?php echo esc_html__('Undeliverable Emails', 'wckb'); ?></label>
                        </th>
                        <td>
                            <select id="wckb_undeliverable_action" name="wckb_undeliverable_action">
                                <option value="allow" <?php selected(get_option('wckb_undeliverable_action', 'allow'), 'allow'); ?>>
                                    <?php echo esc_html__('Allow checkout', 'wckb'); ?>
                                </option>
                                <option value="block" <?php selected(get_option('wckb_undeliverable_action', 'allow'), 'block'); ?>>
                                    <?php echo esc_html__('Block checkout', 'wckb'); ?>
                                </option>
                                <option value="review" <?php selected(get_option('wckb_undeliverable_action', 'allow'), 'review'); ?>>
                                    <?php echo esc_html__('Allow but flag for review', 'wckb'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wckb_risky_action"><?php echo esc_html__('Risky Emails', 'wckb'); ?></label>
                        </th>
                        <td>
                            <select id="wckb_risky_action" name="wckb_risky_action">
                                <option value="allow" <?php selected(get_option('wckb_risky_action', 'allow'), 'allow'); ?>>
                                    <?php echo esc_html__('Allow checkout', 'wckb'); ?>
                                </option>
                                <option value="block" <?php selected(get_option('wckb_risky_action', 'allow'), 'block'); ?>>
                                    <?php echo esc_html__('Block checkout', 'wckb'); ?>
                                </option>
                                <option value="review" <?php selected(get_option('wckb_risky_action', 'allow'), 'review'); ?>>
                                    <?php echo esc_html__('Allow but flag for review', 'wckb'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wckb_unknown_action"><?php echo esc_html__('Unknown Emails', 'wckb'); ?></label>
                        </th>
                        <td>
                            <select id="wckb_unknown_action" name="wckb_unknown_action">
                                <option value="allow" <?php selected(get_option('wckb_unknown_action', 'allow'), 'allow'); ?>>
                                    <?php echo esc_html__('Allow checkout', 'wckb'); ?>
                                </option>
                                <option value="block" <?php selected(get_option('wckb_unknown_action', 'allow'), 'block'); ?>>
                                    <?php echo esc_html__('Block checkout', 'wckb'); ?>
                                </option>
                                <option value="review" <?php selected(get_option('wckb_unknown_action', 'allow'), 'review'); ?>>
                                    <?php echo esc_html__('Allow but flag for review', 'wckb'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for API testing
     */
    public function ajax_test_api() {
        check_ajax_referer('wckb_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wckb')));
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $sandbox_mode = sanitize_text_field($_POST['sandbox_mode'] ?? 'yes');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required.', 'wckb')));
        }
        
        // Test with a sample email
        $test_email = 'test@example.com';
        $api_url = $sandbox_mode === 'yes' ? 'https://api.kickbox.com/v2/verify' : 'https://api.kickbox.com/v2/verify';
        
        $url = add_query_arg(array(
            'email' => $test_email,
            'apikey' => $api_key
        ), $api_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WooCommerce-Kickbox-Integration/' . WCKB_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid response from Kickbox API.', 'wckb')));
        }
        
        if (isset($data['result'])) {
            wp_send_json_success(array(
                'message' => __('API connection successful!', 'wckb'),
                'result' => $data
            ));
        } else {
            wp_send_json_error(array('message' => __('Unexpected response from Kickbox API.', 'wckb')));
        }
    }
}
