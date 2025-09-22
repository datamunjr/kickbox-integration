<?php
/**
 * WooCommerce Registration Email Verification
 *
 * @package WCKB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WCKB_Registration class.
 */
class WCKB_Registration {

    /**
     * Verification instance.
     *
     * @var WCKB_Verification
     */
    private $verification;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->verification = new WCKB_Verification();

        // Hook into WooCommerce registration validation
        add_filter( 'woocommerce_process_registration_errors', array( $this, 'validate_registration_email' ), 10, 4 );
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
        $allow_list = get_option( 'wckb_allow_list', array() );
        if ( in_array( $email, $allow_list, true ) ) {
            return $validation_error; // Skip verification for allowed emails
        }

        // Verify the email
        $result = $this->verification->verify_email( $email, array(
            'origin' => 'registration'
        ) );

        if ( is_wp_error( $result ) ) {
            // Log error but don't block registration
            error_log( 'WCKB Registration Verification Error: ' . $result->get_error_message() );
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
    private function add_registration_error( $validation_error, $result ) {
        $messages = array(
            'undeliverable' => esc_html__( 'This email address does not exist or is invalid. Please use a different email address.', 'wckb' ),
            'risky'         => esc_html__( 'This email address has quality issues and may result in bounces. Please use a different email address.', 'wckb' ),
            'unknown'       => esc_html__( 'We were unable to verify this email address due to server timeout. Please use a different email address.', 'wckb' )
        );

        $message = $messages[ $result ] ?? $messages['unknown'];

        $validation_error->add( 'wckb_email_verification', $message );
    }
}
