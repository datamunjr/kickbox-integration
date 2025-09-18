<?php
/**
 * WCKB_Verification Class
 *
 * Handles email verification using Kickbox API
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCKB_Verification {

	private $api_key;
	private $sandbox_mode;
	private $api_url;

	public function __construct() {
		$this->api_key      = get_option( 'wckb_api_key', '' );
		$this->sandbox_mode = strpos( $this->api_key, 'test_' ) === 0;
		$this->api_url      = 'https://api.kickbox.com/v2/verify';

		add_action( 'wp_ajax_wckb_verify_email', array( $this, 'ajax_verify_email' ) );
		add_action( 'wp_ajax_nopriv_wckb_verify_email', array( $this, 'ajax_verify_email' ) );
	}

	/**
	 * Verify a single email address
	 *
	 * @param string $email Email address to verify
	 * @param array $options Additional verification options
	 *
	 * @return array|WP_Error Verification result or error
	 */
	public function verify_email( $email, $options = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Kickbox API key is not configured.', 'wckb' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address format.', 'wckb' ) );
		}

		// Check if email is in allow list
		$admin = new WCKB_Admin();
		if ( $admin->is_email_in_allow_list( $email ) ) {
			// Return a deliverable result for allow list emails
			return array(
				'result' => 'deliverable',
				'reason' => 'allow_list',
				'sendex' => 1,
				'role' => false,
				'free' => false,
				'disposable' => false,
				'accept_all' => false,
				'did_you_mean' => null,
				'domain' => substr( strrchr( $email, '@' ), 1 ),
				'user' => substr( $email, 0, strpos( $email, '@' ) )
			);
		}

		$default_options = array(
			'timeout' => 30,
			'timeout' => 30,
			'timeout' => 30
		);

		$options = wp_parse_args( $options, $default_options );

		$url = add_query_arg( array(
			'email'   => $email,
			'apikey'  => $this->api_key,
			'timeout' => $options['timeout']
		), $this->api_url );

		$response = wp_remote_get( $url, array(
			'timeout' => $options['timeout'],
			'headers' => array(
				'User-Agent' => 'WooCommerce-Kickbox-Integration/' . WCKB_VERSION
			)
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Kickbox API.', 'wckb' ) );
		}

		// Track balance from response headers
		$this->update_balance_from_response( $response );

		// Log the verification
		$this->log_verification( $email, $data );

		return $data;
	}


	/**
	 * AJAX handler for email verification
	 */
	public function ajax_verify_email() {
		check_ajax_referer( 'wckb_verify_email', 'nonce' );

		$email = sanitize_email( $_POST['email'] ?? '' );

		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email address is required.', 'wckb' ) ) );
		}

		$result = $this->verify_email( $email );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Log verification result to database
	 *
	 * @param string $email Email address
	 * @param array $result Verification result
	 * @param int $user_id User ID (optional)
	 * @param int $order_id Order ID (optional)
	 */
	private function log_verification( $email, $result, $user_id = null, $order_id = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wckb_verification_log';

		$verification_result = $result['result'] ?? 'unknown';
		$verification_data   = json_encode( $result );

		$wpdb->insert(
			$table_name,
			array(
				'email'               => $email,
				'verification_result' => $verification_result,
				'verification_data'   => $verification_data,
				'user_id'             => $user_id,
				'order_id'            => $order_id,
				'created_at'          => current_time( 'mysql' )
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Get verification history for an email
	 *
	 * @param string $email Email address
	 *
	 * @return array Verification history
	 */
	public function get_verification_history( $email ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wckb_verification_log';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE email = %s ORDER BY created_at DESC",
			$email
		) );

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
		return get_option( 'wckb_enable_checkout_verification', 'no' ) === 'yes';
	}

	/**
	 * Get action for verification result
	 *
	 * @param string $result Verification result (deliverable, undeliverable, risky, unknown)
	 *
	 * @return string Action (allow, block, review)
	 */
	public function get_action_for_result( $result ) {
		$option_map = array(
			'deliverable'   => 'wckb_deliverable_action',
			'undeliverable' => 'wckb_undeliverable_action',
			'risky'         => 'wckb_risky_action',
			'unknown'       => 'wckb_unknown_action'
		);

		$option = $option_map[ $result ] ?? 'wckb_unknown_action';

		return get_option( $option, 'allow' );
	}

	/**
	 * Update balance from API response headers
	 *
	 * @param array $response WordPress HTTP response
	 */
	private function update_balance_from_response( $response ) {

		$headers = wp_remote_retrieve_headers( $response );

		if ( isset( $headers['x-kickbox-balance'] ) ) {
			$balance = intval( $headers['x-kickbox-balance'] );
			update_option( 'wckb_api_balance', $balance );
			update_option( 'wckb_balance_last_updated', current_time( 'mysql' ) );
		}
	}

	/**
	 * Get current API balance
	 *
	 * @return int Current balance
	 */
	public function get_balance() {
		return intval( get_option( 'wckb_api_balance', 0 ) );
	}

	/**
	 * Get balance last updated timestamp
	 *
	 * @return string Last updated timestamp
	 */
	public function get_balance_last_updated() {
		return get_option( 'wckb_balance_last_updated', '' );
	}

	/**
	 * Check if balance is low (less than 50)
	 *
	 * @return bool True if balance is low
	 */
	public function is_balance_low() {
		$balance = $this->get_balance();

		// Only consider balance low if we have an actual value and it's less than 50
		return $balance > 0 && $balance < 50;
	}

	/**
	 * Check if balance has been determined (has a value from API)
	 *
	 * @return bool True if balance has been determined
	 */
	public function has_balance_been_determined() {
		$balance      = $this->get_balance();
		$last_updated = $this->get_balance_last_updated();

		// Balance is determined if we have a value > 0 or if we have a last updated timestamp
		return $balance > 0 || ! empty( $last_updated );
	}

	/**
	 * Get balance status message
	 *
	 * @return string Balance status message
	 */
	public function get_balance_status_message() {
		$balance      = $this->get_balance();
		$last_updated = $this->get_balance_last_updated();

		if ( $balance === 0 && empty( $last_updated ) ) {
			return __( 'Balance not yet determined. Make a verification request to check your balance.', 'wckb' );
		}

		$message = sprintf( __( 'Current balance: %d verifications', 'wckb' ), $balance );

		if ( ! empty( $last_updated ) ) {
			$message .= ' ' . sprintf( __( '(last updated: %s)', 'wckb' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_updated ) ) );
		}

		return $message;
	}
}
