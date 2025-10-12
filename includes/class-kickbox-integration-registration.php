<?php
/**
 * WooCommerce Registration Email Verification
 *
 * @package Kickbox_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Kickbox_Integration_Registration class.
 */
class Kickbox_Integration_Registration {

    /**
     * Verification instance.
     *
     * @var Kickbox_Integration_Verification
     */
    private $verification;

    /**
     * WooCommerce logger instance.
     *
     * @var WC_Logger
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->verification = new Kickbox_Integration_Verification();
        $this->logger = wc_get_logger();

        // Hook into WooCommerce registration validation
        add_filter( 'woocommerce_process_registration_errors', array( $this, 'validate_registration_email' ), 10, 4 );
    }

    /**
     * Get the allow list
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_allow_list() {
        return get_option( 'kickbox_integration_allow_list', array() );
    }

    /**
     * Validate email during WooCommerce registration
     *
     * @param WP_Error $validation_error The validation error object
     * @param string $username The username
     * @param string $password The password
     * @param string $email The email address
     * @return WP_Error
     */
    public function validate_registration_email( $validation_error, $username, $password, $email ) {
        // Check if verification is enabled
        if ( ! $this->verification->is_verification_enabled( 'registration' ) ) {
            return $validation_error;
        }

        // Skip if email is empty or invalid format
        if ( empty( $email ) || ! is_email( $email ) ) {
            return $validation_error;
        }

        // Check if email is in allow list
        $allow_list = $this->get_allow_list();
        if ( in_array( $email, $allow_list, true ) ) {
            return $validation_error; // Skip verification for allowed emails
        }

        // Verify the email
        $result = $this->verification->verify_email( $email, array(
            'origin' => 'registration'
        ) );

        if ( is_wp_error( $result ) ) {
            $this->logger->warning( 'Verification error during registration: ' . $result->get_error_message(), array( 
                'source' => 'kickbox-integration',
                'email' => $email
            ) );
            
            // Don't block registration on verification errors
            return $validation_error;
        }

        $verification_result = $result['result'] ?? 'unknown';
        $reason = $result['reason'] ?? '';

        // Check if this is an admin decision result
        if ( $reason === 'admin_decision' ) {
            // Admin has made a decision - use the result directly
            if ( $verification_result === 'undeliverable' ) {
                $this->add_registration_error( $validation_error, $verification_result );
            }
            // If admin decision is 'allow' or 'deliverable', proceed with registration
        } else {
            // Use the settings-based action system
            $action = $this->verification->get_action_for_result( $verification_result );

            if ( $action === 'block' ) {
                $this->add_registration_error( $validation_error, $verification_result );
            }
            // For 'review' and 'allow' actions, proceed with registration
        }

        return $validation_error;
    }

    /**
     * Add validation error to the registration errors object
     *
     * @param WP_Error $validation_error The validation error object
     * @param string $result The verification result
     */
    protected function add_registration_error( $validation_error, $result ) {
        $messages = array(
            'undeliverable' => esc_html__( 'This email address does not exist or is invalid. Please use a different email address.', 'kickbox-integration' ),
            'risky'         => esc_html__( 'This email address has quality issues and may result in bounces. Please use a different email address.', 'kickbox-integration' ),
            'unknown'       => esc_html__( 'We were unable to verify this email address due to server timeout. Please use a different email address.', 'kickbox-integration' )
        );

        $message = $messages[ $result ] ?? $messages['unknown'];

        $validation_error->add( 'kickbox_integration_email_verification', $message );
    }
}
