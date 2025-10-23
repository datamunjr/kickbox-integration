<?php
/**
 * Kickbox_Integration_Checkout Class
 *
 * Handles checkout email verification
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Kickbox_Integration_Checkout {

    private $verification;
    private $cached_email; // Email that we use during woocommerce_blocks_validate_location_address_fields
    private $cached_result;
    private $logger;

    public function __construct() {
        $this->verification = new Kickbox_Integration_Verification();
        $this->logger = wc_get_logger();

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );

        // Use WooCommerce Blocks location address validation for both legacy and blocks checkout
        add_action( 'woocommerce_blocks_validate_location_address_fields', array(
                $this,
                'validate_email_in_address_fields'
        ), 10, 3 );

        add_action( 'woocommerce_blocks_validate_location_contact_fields', array(
                $this,
                'add_verification_errors_to_contact_field'
        ), 10, 3 );

        add_filter( 'woocommerce_form_field_email', array( $this, 'add_checkout_verification_to_email_field' ), 10, 4 );
        add_action( 'wp_footer', array( $this, 'add_blocks_checkout_support' ) );
    }

    /**
     * Enqueue checkout scripts
     */
    public function enqueue_checkout_scripts() {
        if ( ! is_checkout() || ! $this->verification->is_verification_enabled() ) {
            return;
        }


        wp_enqueue_script( 'kickbox-integration-checkout', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/js/checkout.js', array(
                'jquery',
                'wc-checkout'
        ), KICKBOX_INTEGRATION_VERSION, true );
        wp_enqueue_style( 'kickbox-integration-checkout', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/css/checkout-styles.css', array(), KICKBOX_INTEGRATION_VERSION );

        wp_localize_script( 'kickbox-integration-checkout', 'kickbox_integration_checkout', array(
                'ajax_url'             => admin_url( 'admin-ajax.php' ),
                'nonce'                => wp_create_nonce( 'kickbox_integration_verify_email' ),
                'verification_enabled' => $this->verification->is_verification_enabled(),
                'verification_actions' => array(
                        'deliverable'   => get_option( 'kickbox_integration_deliverable_action', 'allow' ),
                        'undeliverable' => get_option( 'kickbox_integration_undeliverable_action', 'allow' ),
                        'risky'         => get_option( 'kickbox_integration_risky_action', 'allow' ),
                        'unknown'       => get_option( 'kickbox_integration_unknown_action', 'allow' )
                ),
                'strings'              => array(
                        'verifying'           => __( 'Verifying email...', 'kickbox-integration' ),
                        'verification_failed' => __( 'Email verification failed. Please check your email address.', 'kickbox-integration' ),
                        'verification_error'  => __( 'Unable to verify email at this time. Please try again.', 'kickbox-integration' ),
                        'deliverable'         => __( 'Email address is deliverable and safe to send to.', 'kickbox-integration' ),
                        'undeliverable'       => __( 'Email address does not exist or is invalid.', 'kickbox-integration' ),
                        'risky'               => __( 'Email address has quality issues and may result in bounces.', 'kickbox-integration' ),
                        'unknown'             => __( 'Unable to verify email address - server timeout or unavailable.', 'kickbox-integration' )
                )
        ) );
    }

    /**
     * Add checkout verification to email field
     */
    public function add_checkout_verification_to_email_field( $field, $key ) {
        // Only modify the billing email field
        if ( $key !== 'billing_email' || ! $this->verification->is_verification_enabled() ) {
            return $field;
        }

        // Add the verification container after the email field
        $field .= '<div id="kickbox-integration-checkout-verification"></div>';

        return $field;
    }

    /**
     * Add support for WooCommerce Blocks checkout
     */
    public function add_blocks_checkout_support() {
        if ( ! is_checkout() || ! $this->verification->is_verification_enabled() ) {
            return;
        }

        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                // Function to add verification container to blocks checkout
                function addVerificationToBlocksCheckout() {
                    // Look for the email field in blocks checkout
                    const emailField = $('.wc-block-components-text-input input[type="email"]');

                    if (emailField.length > 0 && $('#kickbox-integration-checkout-verification').length === 0) {
                        // Find the email field container
                        const emailContainer = emailField.closest('.wc-block-components-text-input');

                        if (emailContainer.length > 0) {
                            // Add verification container after the email field
                            emailContainer.after('<div id="kickbox-integration-checkout-verification"></div>');

                            // Trigger React component initialization
                            if (typeof window.kickboxIntegrationInitCheckoutVerification === 'function') {
                                window.kickboxIntegrationInitCheckoutVerification();
                            }
                        }
                    }
                }

                // Function to intercept Place Order button clicks
                function interceptPlaceOrderClicks() {
                    // Legacy checkout - intercept form submission
                    $('form.checkout').on('submit', function (e) {
                        if (typeof window.kickboxIntegrationVerifyEmailOnSubmit === 'function') {
                            e.preventDefault();
                            e.stopImmediatePropagation();

                            window.kickboxIntegrationVerifyEmailOnSubmit().then(function (canProceed) {
                                if (canProceed) {
                                    // Remove our event handler and submit the form
                                    $('form.checkout').off('submit').submit();
                                }
                            });
                            return false;
                        }
                    });

                    // Blocks checkout - intercept Place Order button click
                    $(document).on('click', '.wc-block-components-checkout-place-order-button', function (e) {
                        if (typeof window.kickboxIntegrationVerifyEmailOnSubmit === 'function') {
                            e.preventDefault();
                            e.stopImmediatePropagation();

                            window.kickboxIntegrationVerifyEmailOnSubmit().then(function (canProceed) {
                                if (canProceed) {
                                    // Remove our event handler and trigger the click again
                                    $(this).off('click').trigger('click');
                                }
                            }.bind(this));
                            return false;
                        }
                    });
                }

                // Check for blocks checkout on page load
                addVerificationToBlocksCheckout();
                interceptPlaceOrderClicks();

                // Also check when blocks are loaded dynamically
                $(document.body).on('updated_checkout', function () {
                    setTimeout(function () {
                        addVerificationToBlocksCheckout();
                        interceptPlaceOrderClicks();
                    }, 100);
                });

                // Check periodically for blocks checkout (fallback)
                const blocksCheckInterval = setInterval(function () {
                    if ($('.wc-block-components-text-input input[type="email"]').length > 0) {
                        addVerificationToBlocksCheckout();
                        interceptPlaceOrderClicks();
                        clearInterval(blocksCheckInterval);
                    }
                }, 500);

                // Clear interval after 10 seconds
                setTimeout(function () {
                    clearInterval(blocksCheckInterval);
                }, 10000);
            });
        </script>
        <?php
    }


    /**
     * Validate email in address fields - see validate_callback() method in Checkout.php in WooCommerce
     *
     * @param WP_Error $errors The errors object
     * @param array $fields The address fields
     * @param string $group The field group (billing or shipping)
     */
    public function validate_email_in_address_fields( $errors, $fields, $group ) {
        // Only validate billing address fields
        if ( $group !== 'billing' ) {
            return;
        }

        if ( ! $this->verification->is_verification_enabled( 'checkout' ) ) {
            return;
        }

        // Cache the email
        $this->cached_email = $fields['email'] ?? '';

        if ( empty( $this->cached_email ) ) {
            return; // No email to validate
        }

        // Get user ID if email is associated with a user
        $user_id = null;
        $user    = get_user_by( 'email', $this->cached_email );
        if ( $user ) {
            $user_id = $user->ID;
        }

        // Verify the email
        $result = $this->verification->verify_email( $this->cached_email, array(
                'origin'  => 'checkout',
                'user_id' => $user_id
        ) );

        if ( is_wp_error( $result ) ) {
            $this->logger->warning( 'Verification error during checkout: ' . $result->get_error_message(), array( 
                'source' => 'kickbox-integration',
                'email' => $this->cached_email
            ) );
            
            // Don't block checkout on verification errors
            return;
        }

        // Cache the result
        $this->cached_result = $result;
    }

    /**
     * There's a weird UX experience where the error banner shows up above the Billing Address section, which we
     * don't want to show it there. Instead, we want it to show over the "contact" section or "additional_fields"
     * section.
     *
     * @param $errors
     * @param $fields
     * @param $group
     *
     * @return void
     */
    public function add_verification_errors_to_contact_field( $errors, $fields, $group ) {
        $verification_result = isset( $this->cached_result ) ? $this->cached_result['result'] : null;
        $reason              = isset( $this->cached_result ) ? $this->cached_result['reason'] : null;

        // If we don't have a cached result, then verification isn't enabled for checkout
        if ( empty( $verification_result ) || empty( $reason ) ) {
            return;
        }

        // Check if this is an admin decision result
        if ( $reason === 'admin_decision' ) {
            // Admin has made a decision - use the result directly
            if ( $verification_result === 'undeliverable' ) {
                $this->add_validation_error( $errors, $verification_result );
            }
        } else {
            // Use the settings-based action system
            $action = $this->verification->get_action_for_result( $verification_result );

            if ( $action === 'block' ) {
                $this->add_validation_error( $errors, $verification_result );
            }
        }
    }

    /**
     * Add validation error to the errors object
     *
     * @param WP_Error $errors The errors object
     * @param string $result The verification result
     */
    private function add_validation_error( $errors, $result ) {
        $messages = array(
                'undeliverable' => esc_html__( 'This email address does not exist or is invalid. Please use a different email address.', 'kickbox-integration' ),
                'risky'         => esc_html__( 'This email address has quality issues and may result in bounces. Please use a different email address.', 'kickbox-integration' ),
                'unknown'       => esc_html__( 'We were unable to verify this email address due to unknown issue. Please use a different email address.', 'kickbox-integration' )
        );

        $message = $messages[ $result ] ?? $messages['unknown'];

        $errors->add( 'kickbox_integration_email_verification', $message );
    }
}
