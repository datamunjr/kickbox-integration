<?php
/**
 * Kickbox_Integration_Admin Class
 *
 * Handles admin functionality and settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Kickbox_Integration_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 25 );
        add_action( 'wp_ajax_kickbox_integration_test_api', array( $this, 'validate_kickbox_api_key' ) );
        add_action( 'wp_ajax_kickbox_integration_validate_api_key', array( $this, 'validate_kickbox_api_key' ) );
        add_action( 'wp_ajax_kickbox_integration_get_settings', array( $this, 'ajax_get_settings' ) );
        add_action( 'wp_ajax_kickbox_integration_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_kickbox_integration_get_stats', array( $this, 'ajax_get_stats' ) );
        add_action( 'wp_ajax_kickbox_integration_get_full_api_key', array( $this, 'ajax_get_full_api_key' ) );
        add_action( 'wp_ajax_kickbox_integration_get_allow_list', array( $this, 'ajax_get_allow_list' ) );
        add_action( 'wp_ajax_kickbox_integration_add_to_allow_list', array( $this, 'ajax_add_to_allow_list' ) );
        add_action( 'wp_ajax_kickbox_integration_remove_from_allow_list', array(
                $this,
                'ajax_remove_from_allow_list'
        ) );

        // Flagged emails AJAX handlers
        add_action( 'wp_ajax_kickbox_integration_get_flagged_emails', array( $this, 'ajax_get_flagged_emails' ) );
        add_action( 'wp_ajax_kickbox_integration_update_flagged_decision', array(
                $this,
                'ajax_update_flagged_decision'
        ) );
        add_action( 'wp_ajax_kickbox_integration_edit_flagged_decision', array( $this, 'ajax_edit_flagged_decision' ) );
        add_action( 'wp_ajax_kickbox_integration_get_flagged_statistics', array(
                $this,
                'ajax_get_flagged_statistics'
        ) );
        add_action( 'wp_ajax_kickbox_integration_get_pending_count', array( $this, 'ajax_get_pending_count' ) );
        add_action( 'admin_notices', array( $this, 'show_balance_notice' ) );
        add_action( 'admin_notices', array( $this, 'show_verification_disabled_notice' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
                'woocommerce',
                __( 'Kickbox Integration', 'kickbox-integration' ),
                __( 'Kickbox Integration', 'kickbox-integration' ),
                'manage_woocommerce',
                'kickbox-integration-settings',
                array( $this, 'admin_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'kickbox_integration_settings', 'kickbox_integration_api_key', array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => ''
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_sandbox_mode', array(
                'type'    => 'string',
                'default' => 'yes'
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_deliverable_action', array(
                'type'              => 'string',
                'default'           => 'allow',
                'sanitize_callback' => array( $this, 'restrict_deliverable_action' )
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_undeliverable_action', array(
                'type'    => 'string',
                'default' => 'block'
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_risky_action', array(
                'type'    => 'string',
                'default' => 'block'
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_unknown_action', array(
                'type'    => 'string',
                'default' => 'block'
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_enable_checkout_verification', array(
                'type'    => 'string',
                'default' => 'no'
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_enable_registration_verification', array(
                'type'    => 'string',
                'default' => 'no'
        ) );

        register_setting( 'kickbox_integration_settings', 'kickbox_integration_allow_list', array(
                'type'              => 'array',
                'default'           => array(),
                'sanitize_callback' => array( $this, 'sanitize_allow_list' )
        ) );

    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'woocommerce_page_kickbox-integration-settings' ) {
            return;
        }

        // Enqueue WooCommerce help tip functionality
        wp_enqueue_style( 'woocommerce_admin_styles' );

        // Enqueue our admin scripts with WooCommerce as dependency
        wp_enqueue_script( 'kickbox-integration-admin', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/js/admin.js', array(
                'jquery',
                'jquery-tiptip'
        ), KICKBOX_INTEGRATION_VERSION, true );
        wp_enqueue_style( 'kickbox-integration-admin', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/css/admin.css', array( 'woocommerce_admin_styles' ), KICKBOX_INTEGRATION_VERSION );

        wp_localize_script( 'kickbox-integration-admin', 'kickbox_integration_admin', array(
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'plugin_url' => KICKBOX_INTEGRATION_PLUGIN_URL,
                'nonce'      => wp_create_nonce( 'kickbox_integration_admin' ),
                'strings'    => array(
                        'testing_api'  => __( 'Testing API connection...', 'kickbox-integration' ),
                        'api_success'  => __( 'API connection successful!', 'kickbox-integration' ),
                        'api_error'    => __( 'API connection failed. Please check your API key.', 'kickbox-integration' ),
                        'confirm_test' => __( 'Are you sure you want to test the API connection? This will use 1 verification credit.', 'kickbox-integration' )
                )
        ) );
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <div class="kickbox-admin-header">
                <img src="<?php echo esc_url( KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/images/kickbox-logo-icon-255x255.svg' ); ?>"
                     alt="Kickbox Logo"/>
                <h1><?php echo esc_html__( 'Kickbox Integration Settings', 'kickbox-integration' ); ?></h1>
            </div>

            <div id="kickbox-integration-admin-app">
                <p>Loading Kickbox Integration Settings...</p>
                <p><em>If this message persists, there may be a JavaScript error. Please check the browser console.</em>
                </p>
            </div>

            <form method="post" action="options.php" id="kickbox-integration-settings-form" style="display: none;">
                <?php
                settings_fields( 'kickbox_integration_settings' );
                do_settings_sections( 'kickbox_integration_settings' );
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_api_key"><?php echo esc_html__( 'API Key', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <input type="password" id="kickbox_integration_api_key" name="kickbox_integration_api_key"
                                   value="<?php echo esc_attr( get_option( 'kickbox_integration_api_key', '' ) ); ?>"
                                   class="regular-text"/>
                            <p class="description">
                                <?php echo esc_html__( 'Enter your Kickbox API key. You can find this in your Kickbox dashboard.', 'kickbox-integration' ); ?>
                            </p>
                            <button type="button" id="kickbox-integration-test-api" class="button button-secondary">
                                <?php echo esc_html__( 'Test API Connection', 'kickbox-integration' ); ?>
                            </button>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_sandbox_mode"><?php echo esc_html__( 'Sandbox Mode', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="kickbox_integration_sandbox_mode"
                                   name="kickbox_integration_sandbox_mode"
                                   value="yes" <?php checked( get_option( 'kickbox_integration_sandbox_mode', 'yes' ), 'yes' ); ?> />
                            <label for="kickbox_integration_sandbox_mode">
                                <?php echo esc_html__( 'Enable sandbox mode for testing (recommended)', 'kickbox-integration' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_enable_checkout_verification"><?php echo esc_html__( 'Checkout Verification', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="kickbox_integration_enable_checkout_verification"
                                   name="kickbox_integration_enable_checkout_verification"
                                   value="yes" <?php checked( get_option( 'kickbox_integration_enable_checkout_verification', 'no' ), 'yes' ); ?> />
                            <label for="kickbox_integration_enable_checkout_verification">
                                <?php echo esc_html__( 'Enable email verification during checkout', 'kickbox-integration' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_enable_registration_verification"><?php echo esc_html__( 'Registration Verification', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="kickbox_integration_enable_registration_verification"
                                   name="kickbox_integration_enable_registration_verification"
                                   value="yes" <?php checked( get_option( 'kickbox_integration_enable_registration_verification', 'no' ), 'yes' ); ?> />
                            <label for="kickbox_integration_enable_registration_verification">
                                <?php echo esc_html__( 'Enable email verification during user registration', 'kickbox-integration' ); ?>
                            </label>
                        </td>
                    </tr>

                </table>

                <h2><?php echo esc_html__( 'Verification Actions', 'kickbox-integration' ); ?></h2>
                <p><?php echo esc_html__( 'Configure what action to take for each verification result:', 'kickbox-integration' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_deliverable_action"><?php echo esc_html__( 'Deliverable Emails', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <select id="kickbox_integration_deliverable_action"
                                    name="kickbox_integration_deliverable_action">
                                <option value="allow" <?php selected( get_option( 'kickbox_integration_deliverable_action', 'allow' ), 'allow' ); ?>>
                                    <?php echo esc_html__( 'Allow checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="block" <?php selected( get_option( 'kickbox_integration_deliverable_action', 'allow' ), 'block' ); ?>>
                                    <?php echo esc_html__( 'Block checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="review" <?php selected( get_option( 'kickbox_integration_deliverable_action', 'allow' ), 'review' ); ?>>
                                    <?php echo esc_html__( 'Allow but flag for review', 'kickbox-integration' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_undeliverable_action"><?php echo esc_html__( 'Undeliverable Emails', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <select id="kickbox_integration_undeliverable_action"
                                    name="kickbox_integration_undeliverable_action">
                                <option value="allow" <?php selected( get_option( 'kickbox_integration_undeliverable_action', 'allow' ), 'allow' ); ?>>
                                    <?php echo esc_html__( 'Allow checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="block" <?php selected( get_option( 'kickbox_integration_undeliverable_action', 'allow' ), 'block' ); ?>>
                                    <?php echo esc_html__( 'Block checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="review" <?php selected( get_option( 'kickbox_integration_undeliverable_action', 'allow' ), 'review' ); ?>>
                                    <?php echo esc_html__( 'Allow but flag for review', 'kickbox-integration' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_risky_action"><?php echo esc_html__( 'Risky Emails', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <select id="kickbox_integration_risky_action" name="kickbox_integration_risky_action">
                                <option value="allow" <?php selected( get_option( 'kickbox_integration_risky_action', 'allow' ), 'allow' ); ?>>
                                    <?php echo esc_html__( 'Allow checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="block" <?php selected( get_option( 'kickbox_integration_risky_action', 'allow' ), 'block' ); ?>>
                                    <?php echo esc_html__( 'Block checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="review" <?php selected( get_option( 'kickbox_integration_risky_action', 'allow' ), 'review' ); ?>>
                                    <?php echo esc_html__( 'Allow but flag for review', 'kickbox-integration' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kickbox_integration_unknown_action"><?php echo esc_html__( 'Unknown Emails', 'kickbox-integration' ); ?></label>
                        </th>
                        <td>
                            <select id="kickbox_integration_unknown_action" name="kickbox_integration_unknown_action">
                                <option value="allow" <?php selected( get_option( 'kickbox_integration_unknown_action', 'allow' ), 'allow' ); ?>>
                                    <?php echo esc_html__( 'Allow checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="block" <?php selected( get_option( 'kickbox_integration_unknown_action', 'allow' ), 'block' ); ?>>
                                    <?php echo esc_html__( 'Block checkout', 'kickbox-integration' ); ?>
                                </option>
                                <option value="review" <?php selected( get_option( 'kickbox_integration_unknown_action', 'allow' ), 'review' ); ?>>
                                    <?php echo esc_html__( 'Allow but flag for review', 'kickbox-integration' ); ?>
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
     * AJAX handler for API key validation
     */
    public function validate_kickbox_api_key() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $api_key      = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        $sandbox_mode = sanitize_text_field( wp_unslash( $_POST['sandbox_mode'] ?? 'yes' ) );

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API key is required.', 'kickbox-integration' ) ) );
        }

        // Determine sandbox mode from API key prefix
        $sandbox_mode = strpos( $api_key, 'test_' ) === 0;

        // Test with a sample email
        $test_email = 'test@example.com';
        $api_url    = 'https://api.kickbox.com/v2/verify';

        $url = add_query_arg( array(
                'email'  => $test_email,
                'apikey' => $api_key
        ), $api_url );

        $response = wp_remote_get( $url, array(
                'timeout' => 30,
                'headers' => array(
                        'User-Agent' => 'WooCommerce-Kickbox-Integration/' . KICKBOX_INTEGRATION_VERSION
                )
        ) );

        if ( is_wp_error( $response ) ) {
            // Log the WP_Error for debugging
            error_log( '[Kickbox_Integration]: WP_Error during API test - ' . $response->get_error_message() );

            wp_send_json_error( array(
                    'message'     => $response->get_error_message(),
                    'details'     => array(
                            'error_code' => $response->get_error_code(),
                            'error_data' => $response->get_error_data()
                    ),
                    'has_details' => true
            ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Log the invalid JSON response for debugging
            error_log( '[Kickbox_Integration]: Invalid JSON response from Kickbox API - ' . $body );

            wp_send_json_error( array(
                    'message'     => __( 'Invalid response from Kickbox API.', 'kickbox-integration' ),
                    'details'     => array(
                            'raw_response' => $body,
                            'json_error'   => json_last_error_msg()
                    ),
                    'has_details' => true
            ) );
        }

        if ( isset( $data['result'] ) ) {
            wp_send_json_success( array(
                    'message' => __( 'API connection successful!', 'kickbox-integration' ),
                    'result'  => $data
            ) );
        } else {
            // Log the unexpected response for debugging
            error_log( '[Kickbox_Integration]: Unexpected Kickbox API response - ' . print_r( $data, true ) );

            wp_send_json_error( array(
                    'message'     => __( 'Unexpected response from Kickbox API.', 'kickbox-integration' ),
                    'details'     => $data,
                    'has_details' => true
            ) );
        }
    }

    /**
     * Internal API key validation method
     */
    private function validate_api_key_internal( $api_key ) {
        if ( empty( $api_key ) ) {
            return array( 'success' => false, 'message' => __( 'API key is required.', 'kickbox-integration' ) );
        }

        // Test with a sample email
        $test_email = 'test@example.com';
        $api_url    = 'https://api.kickbox.com/v2/verify';

        $url = add_query_arg( array(
                'email'  => $test_email,
                'apikey' => $api_key
        ), $api_url );

        $response = wp_remote_get( $url, array(
                'timeout' => 30,
                'headers' => array(
                        'User-Agent' => 'WooCommerce-Kickbox-Integration/' . KICKBOX_INTEGRATION_VERSION
                )
        ) );

        if ( is_wp_error( $response ) ) {
            // Log the WP_Error for debugging
            error_log( '[Kickbox_Integration]: WP_Error during API validation - ' . $response->get_error_message() );

            return array(
                    'success' => false,
                    'message' => $response->get_error_message(),
                    'details' => array(
                            'error_code' => $response->get_error_code(),
                            'error_data' => $response->get_error_data()
                    )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Log the invalid JSON response for debugging
            error_log( '[Kickbox_Integration]: Invalid JSON response during API validation - ' . $body );

            return array(
                    'success' => false,
                    'message' => __( 'Invalid response from Kickbox API.', 'kickbox-integration' ),
                    'details' => array(
                            'raw_response' => $body,
                            'json_error'   => json_last_error_msg()
                    )
            );
        }

        if ( isset( $data['result'] ) ) {
            return array( 'success' => true, 'message' => __( 'API key is valid.', 'kickbox-integration' ) );
        } else {
            // Log the unexpected response for debugging
            error_log( '[Kickbox_Integration]: Unexpected Kickbox API response during validation - ' . print_r( $data, true ) );

            return array(
                    'success' => false,
                    'message' => __( 'Unexpected response from Kickbox API.', 'kickbox-integration' ),
                    'details' => $data
            );
        }
    }

    /**
     * AJAX handler for getting settings
     */
    public function ajax_get_settings() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $api_key        = get_option( 'kickbox_integration_api_key', '' );
        $masked_api_key = $this->mask_api_key( $api_key );

        // Get balance information
        $verification                = new Kickbox_Integration_Verification();
        $balance                     = $verification->get_balance();
        $balance_message             = $verification->get_balance_status_message();
        $is_balance_low              = $verification->is_balance_low();
        $has_balance_been_determined = $verification->has_balance_been_determined();

        $settings = array(
                'apiKey'                         => $masked_api_key,
                'deliverableAction'              => get_option( 'kickbox_integration_deliverable_action', 'allow' ),
                'undeliverableAction'            => get_option( 'kickbox_integration_undeliverable_action', 'allow' ),
                'riskyAction'                    => get_option( 'kickbox_integration_risky_action', 'allow' ),
                'unknownAction'                  => get_option( 'kickbox_integration_unknown_action', 'allow' ),
                'enableCheckoutVerification'     => get_option( 'kickbox_integration_enable_checkout_verification', 'no' ) === 'yes',
                'enableRegistrationVerification' => get_option( 'kickbox_integration_enable_registration_verification', 'no' ) === 'yes',
                'balance'                        => $balance,
                'balanceMessage'                 => $balance_message,
                'isBalanceLow'                   => $is_balance_low,
                'hasBalanceBeenDetermined'       => $has_balance_been_determined,
                'allowList'                      => $this->get_allow_list()
        );

        wp_send_json_success( $settings );
    }

    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $api_key             = sanitize_text_field( wp_unslash( $_POST['apiKey'] ?? '' ) );
        $skip_key_validation = sanitize_text_field( wp_unslash( $_POST['skipValidation'] ?? 'false' ) ) === 'true';

        // If API key is provided and validation is not skipped, validate it before saving
        if ( ! empty( $api_key ) && ! $skip_key_validation ) {
            $validation_result = $this->validate_api_key_internal( $api_key );
            if ( ! $validation_result['success'] ) {
                wp_send_json_error( array(
                        'message'     => __( 'Settings not saved. API key validation failed: ', 'kickbox-integration' ) . $validation_result['message'],
                        'details'     => $validation_result['details'] ?? null,
                        'has_details' => ! empty( $validation_result['details'] )
                ) );
            }
        }

        $settings = array(
                'kickbox_integration_api_key'                          => $api_key,
                'kickbox_integration_deliverable_action'               => $this->restrict_deliverable_action( sanitize_text_field( wp_unslash( $_POST['deliverableAction'] ?? 'allow' ) ) ),
                'kickbox_integration_undeliverable_action'             => sanitize_text_field( wp_unslash( $_POST['undeliverableAction'] ?? 'allow' ) ),
                'kickbox_integration_risky_action'                     => sanitize_text_field( wp_unslash( $_POST['riskyAction'] ?? 'allow' ) ),
                'kickbox_integration_unknown_action'                   => sanitize_text_field( wp_unslash( $_POST['unknownAction'] ?? 'allow' ) ),
                'kickbox_integration_enable_checkout_verification'     => sanitize_text_field( wp_unslash( $_POST['enableCheckoutVerification'] ?? 'no' ) ) === 'true' ? 'yes' : 'no',
                'kickbox_integration_enable_registration_verification' => sanitize_text_field( wp_unslash( $_POST['enableRegistrationVerification'] ?? 'no' ) ) === 'true' ? 'yes' : 'no'
        );

        if ( $skip_key_validation ) {
            unset( $settings['kickbox_integration_api_key'] );
        }

        foreach ( $settings as $option => $value ) {
            update_option( $option, $value );
        }

        // Prepare success message with API key validation info
        $message       = __( 'Settings saved successfully!', 'kickbox-integration' );
        $api_mode_info = null;

        if ( ! empty( $api_key ) ) {
            $sandbox_mode  = strpos( $api_key, 'test_' ) === 0;
            $api_mode_info = array(
                    'validated'    => true,
                    'sandbox_mode' => $sandbox_mode,
                    'mode_text'    => $sandbox_mode ?
                            __( 'Sandbox Mode: Using test API key. No credits will be consumed.', 'kickbox-integration' ) :
                            __( 'Live Mode: Using production API key. Credits will be consumed.', 'kickbox-integration' )
            );
        }

        wp_send_json_success( array(
                'message'       => $message,
                'api_mode_info' => $api_mode_info
        ) );
    }

    /**
     * AJAX handler for getting statistics
     */
    public function ajax_get_stats() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $verification = new Kickbox_Integration_Verification();
        $stats        = $verification->get_verification_stats();

        wp_send_json_success( $stats );
    }

    /**
     * AJAX handler for getting full API key
     */
    public function ajax_get_full_api_key() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $api_key = get_option( 'kickbox_integration_api_key', '' );

        wp_send_json_success( array( 'apiKey' => $api_key ) );
    }

    /**
     * Mask API key for display
     */
    private function mask_api_key( $api_key ) {
        if ( empty( $api_key ) ) {
            return '';
        }

        // Extract prefix (test_ or live_)
        $prefix = '';
        if ( strpos( $api_key, 'test_' ) === 0 ) {
            $prefix = 'test_';
        } elseif ( strpos( $api_key, 'live_' ) === 0 ) {
            $prefix = 'live_';
        }

        if ( $prefix ) {
            $remaining = substr( $api_key, strlen( $prefix ) );
            if ( strlen( $remaining ) <= 4 ) {
                return $prefix . $remaining;
            }
            $last_four = substr( $remaining, - 4 );
            $masked    = str_repeat( '*', strlen( $remaining ) - 4 );

            return $prefix . $masked . $last_four;
        }

        // If no prefix, just show last 4 characters
        if ( strlen( $api_key ) <= 4 ) {
            return $api_key;
        }
        $last_four = substr( $api_key, - 4 );
        $masked    = str_repeat( '*', strlen( $api_key ) - 4 );

        return $masked . $last_four;
    }

    /**
     * Show balance notice if balance is low
     */
    public function show_balance_notice() {
        // Only show to users who can manage WooCommerce
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Only show if API key is configured
        $api_key = get_option( 'kickbox_integration_api_key', '' );
        if ( empty( $api_key ) ) {
            return;
        }

        $verification = new Kickbox_Integration_Verification();

        // Only show if balance has been determined and is low
        if ( ! $verification->has_balance_been_determined() || ! $verification->is_balance_low() ) {
            return;
        }

        $balance         = $verification->get_balance();
        $balance_message = $verification->get_balance_status_message();

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php echo esc_html__( 'Kickbox Email Verification - Low Balance Alert', 'kickbox-integration' ); ?></strong>
            </p>
            <p>
                <?php echo esc_html( $balance_message ); ?>
            </p>
            <p>
                <?php echo esc_html__( 'Your verification balance is running low. Please add more credits to continue email verification.', 'kickbox-integration' ); ?>
            </p>
            <p>
                <a href="https://kickbox.com" target="_blank" class="button button-primary">
                    <?php echo esc_html__( 'Add Credits to Kickbox Account', 'kickbox-integration' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=kickbox-integration-settings' ) ); ?>"
                   class="button button-secondary">
                    <?php echo esc_html__( 'View Settings', 'kickbox-integration' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Sanitize deliverable action to prevent 'review' option
     *
     * @param string $value The value to sanitize
     *
     * @return string Sanitized value
     */
    public function restrict_deliverable_action( $value ) {
        // Only allow 'allow' or 'block' for deliverable emails
        if ( $value === 'review' ) {
            return 'allow'; // Default to 'allow' if 'review' is somehow submitted
        }

        return in_array( $value, array( 'allow', 'block' ), true ) ? $value : 'allow';
    }

    /**
     * Show notice when verification is disabled
     */
    public function show_verification_disabled_notice() {
        // Only show to users who can manage WooCommerce
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Only show if API key is configured
        $api_key = get_option( 'kickbox_integration_api_key', '' );
        if ( empty( $api_key ) ) {
            return;
        }

        // Only show if both checkout and registration verification are disabled
        $checkout_verification_enabled     = get_option( 'kickbox_integration_enable_checkout_verification', 'no' );
        $registration_verification_enabled = get_option( 'kickbox_integration_enable_registration_verification', 'no' );

        if ( $checkout_verification_enabled === 'yes' || $registration_verification_enabled === 'yes' ) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php echo esc_html__( 'Kickbox Email Verification - Verification Disabled', 'kickbox-integration' ); ?></strong>
            </p>
            <p>
                <?php echo esc_html__( 'Your Kickbox integration is enabled but both checkout and registration verification are currently disabled.', 'kickbox-integration' ); ?>
            </p>
            <p>
                <?php echo esc_html__( 'This means customers can use invalid email addresses during checkout and registration, which can result in:', 'kickbox-integration' ); ?>
            </p>
            <ul style="margin-left: 20px;">
                <li><?php echo esc_html__( '- Fake or invalid email addresses', 'kickbox-integration' ); ?></li>
                <li><?php echo esc_html__( '- Misspelled email addresses', 'kickbox-integration' ); ?></li>
                <li><?php echo esc_html__( '- Throw-away or disposable email addresses', 'kickbox-integration' ); ?></li>
                <li><?php echo esc_html__( '- Invalid user accounts and failed order deliveries', 'kickbox-integration' ); ?></li>
                <li><?php echo esc_html__( '- Reduced email deliverability and customer engagement', 'kickbox-integration' ); ?></li>
            </ul>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=kickbox-integration-settings' ) ); ?>"
                   class="button button-primary">
                    <?php echo esc_html__( 'Enable Email Verification', 'kickbox-integration' ); ?>
                </a>
                <a href="https://docs.kickbox.com/docs/terminology" target="_blank" class="button button-secondary">
                    <?php echo esc_html__( 'Learn More About Email Verification', 'kickbox-integration' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Sanitize allow list
     *
     * @param array $value The value to sanitize
     *
     * @return array Sanitized value
     */
    public function sanitize_allow_list( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $valid_emails = array();

        foreach ( $value as $email ) {
            $email = trim( $email );
            if ( is_email( $email ) ) {
                $valid_emails[] = sanitize_email( $email );
            }
        }

        // Remove duplicates and re-index array
        return array_values( array_unique( $valid_emails ) );
    }

    /**
     * Get allow list as array
     *
     * @return array Array of allowed emails
     */
    public function get_allow_list() {
        $allow_list = get_option( 'kickbox_integration_allow_list', array() );

        // Ensure we always return an array
        if ( ! is_array( $allow_list ) ) {
            return array();
        }

        return $allow_list;
    }

    /**
     * Check if email is in allow list
     *
     * @param string $email Email to check
     *
     * @return bool True if email is in allow list
     */
    public function is_email_in_allow_list( $email ) {
        $allow_list = $this->get_allow_list();

        return in_array( strtolower( $email ), array_map( 'strtolower', $allow_list ), true );
    }

    /**
     * AJAX handler to get allow list
     */
    public function ajax_get_allow_list() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $allow_list = $this->get_allow_list();
        wp_send_json_success( $allow_list );
    }

    /**
     * AJAX handler to add email to allow list
     */
    public function ajax_add_to_allow_list() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'kickbox-integration' ) ) );
        }

        $allow_list = $this->get_allow_list();

        // Check if email already exists
        if ( in_array( strtolower( $email ), array_map( 'strtolower', $allow_list ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Email address is already in the allow list.', 'kickbox-integration' ) ) );
        }

        // Add email to list
        $allow_list[] = $email;
        $this->save_allow_list( $allow_list );

        wp_send_json_success( array( 'message' => __( 'Email added to allow list successfully.', 'kickbox-integration' ) ) );
    }

    /**
     * AJAX handler to remove email from allow list
     */
    public function ajax_remove_from_allow_list() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( empty( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Email address is required.', 'kickbox-integration' ) ) );
        }

        $allow_list = $this->get_allow_list();

        // Remove email from list (case-insensitive)
        $allow_list = array_filter( $allow_list, function ( $list_email ) use ( $email ) {
            return strtolower( $list_email ) !== strtolower( $email );
        } );

        $this->save_allow_list( $allow_list );

        wp_send_json_success( array( 'message' => __( 'Email removed from allow list successfully.', 'kickbox-integration' ) ) );
    }

    /**
     * Save allow list
     *
     * @param array $allow_list Array of emails
     */
    private function save_allow_list( $allow_list ) {
        // WordPress automatically serializes arrays
        update_option( 'kickbox_integration_allow_list', $allow_list );
    }

    /**
     * AJAX handler to get flagged emails with pagination and search
     */
    public function ajax_get_flagged_emails() {
        try {
            check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
            }

            $flagged_emails = new Kickbox_Integration_Flagged_Emails();

            $args = array(
                    'page'                => intval( wp_unslash( $_POST['page'] ?? 1 ) ),
                    'per_page'            => intval( wp_unslash( $_POST['per_page'] ?? 20 ) ),
                    'search'              => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
                    'decision'            => sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) ),
                    'origin'              => sanitize_text_field( wp_unslash( $_POST['origin'] ?? '' ) ),
                    'verification_action' => sanitize_text_field( wp_unslash( $_POST['verification_action'] ?? '' ) ),
                    'orderby'             => sanitize_text_field( wp_unslash( $_POST['orderby'] ?? 'flagged_date' ) ),
                    'order'               => sanitize_text_field( wp_unslash( $_POST['order'] ?? 'DESC' ) )
            );

            $result = $flagged_emails->get_flagged_emails( $args );
            wp_send_json_success( $result );
        } catch ( Exception $e ) {
            error_log( '[Kickbox_Integration] Flagged Emails AJAX Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Error loading flagged emails: ', 'kickbox-integration' ) . $e->getMessage() ) );
        }
    }

    /**
     * AJAX handler to update admin decision for flagged email
     */
    public function ajax_update_flagged_decision() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $id       = intval( wp_unslash( $_POST['id'] ?? 0 ) );
        $decision = sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) );
        $notes    = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

        if ( empty( $id ) || ! in_array( $decision, array( 'allow', 'block' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'kickbox-integration' ) ) );
        }

        $flagged_emails = new Kickbox_Integration_Flagged_Emails();
        $result         = $flagged_emails->update_admin_decision( $id, $decision, $notes );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Decision updated successfully.', 'kickbox-integration' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to update decision.', 'kickbox-integration' ) ) );
        }
    }

    /**
     * AJAX handler to edit admin decision for already reviewed flagged email
     */
    public function ajax_edit_flagged_decision() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $id       = intval( wp_unslash( $_POST['id'] ?? 0 ) );
        $decision = sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) );
        $notes    = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

        if ( empty( $id ) || ! in_array( $decision, array( 'allow', 'block' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'kickbox-integration' ) ) );
        }

        $flagged_emails = new Kickbox_Integration_Flagged_Emails();
        $result         = $flagged_emails->edit_admin_decision( $id, $decision, $notes );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Decision updated successfully.', 'kickbox-integration' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to update decision.', 'kickbox-integration' ) ) );
        }
    }

    /**
     * AJAX handler to get pending emails count
     */
    public function ajax_get_pending_count() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $flagged_emails = new Kickbox_Integration_Flagged_Emails();
        $stats          = $flagged_emails->get_statistics();

        wp_send_json_success( array( 'pending_count' => $stats['pending'] ) );
    }

    /**
     * AJAX handler to get flagged emails statistics
     */
    public function ajax_get_flagged_statistics() {
        check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
        }

        $flagged_emails = new Kickbox_Integration_Flagged_Emails();
        $stats          = $flagged_emails->get_statistics();

        wp_send_json_success( $stats );
    }
}
