<?php
/**
 * Kickbox_Integration Flagged Emails Management
 *
 * Handles flagged email storage, retrieval, and admin decision management.
 *
 * @package Kickbox_Integration
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kickbox_Integration_Flagged_Emails {

	/**
	 * Table name for flagged emails
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Cache group for flagged emails
	 *
	 * @var string
	 */
	private $cache_group = 'kickbox_flagged_emails';

	/**
	 * Cache expiration time in seconds (1 hour)
	 *
	 * @var int
	 */
	private $cache_expiration = 3600;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'kickbox_integration_flagged_emails';
	}

	/**
	 * Generate cache key for flagged email by ID
	 *
	 * @param int $id Flagged email ID
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_by_id( $id ) {
		return "flagged_email_id_{$id}";
	}

	/**
	 * Generate cache key for flagged email by email address
	 *
	 * @param string $email Email address
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_by_email( $email ) {
		return "flagged_email_email_" . md5( sanitize_email( $email ) );
	}

	/**
	 * Generate cache key for flagged emails list
	 *
	 * @param array $args Query arguments
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_list( $args ) {
		$key_data = array(
			'page'                => $args['page'] ?? 1,
			'per_page'            => $args['per_page'] ?? 20,
			'search'              => $args['search'] ?? '',
			'decision'            => $args['decision'] ?? '',
			'origin'              => $args['origin'] ?? '',
			'verification_action' => $args['verification_action'] ?? '',
			'orderby'             => $args['orderby'] ?? 'flagged_date',
			'order'               => $args['order'] ?? 'DESC'
		);

		return "flagged_emails_list_" . md5( serialize( $key_data ) );
	}

	/**
	 * Generate cache key for statistics
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_stats() {
		return "flagged_emails_stats";
	}

	/**
	 * Clear all cached data for flagged emails
	 */
	private function clear_all_cache() {
		wp_cache_flush_group( $this->cache_group );
	}

	/**
	 * Flag an email for admin review
	 *
	 * @param string $email Email address to flag
	 * @param array $kickbox_result Full Kickbox API response
	 * @param int|null $order_id Order ID if from checkout
	 * @param int|null $user_id User ID if email is associated with a user
	 * @param string $origin Origin of the flag (checkout, signup, etc.)
	 * @param string $verification_action The verification action that led to flagging (block, review)
	 *
	 * @return int|false Flagged email ID on success, false on failure
	 */
	public function flag_email( $email, $kickbox_result, $order_id = null, $user_id = null, $origin = 'checkout', $verification_action = 'review' ) {
		global $wpdb;

		// Check if email is already flagged and pending
		$existing = $this->get_flagged_email_by_email( $email, 'pending' );
		if ( $existing ) {
			return $existing->id;
		}

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'email'               => sanitize_email( $email ),
				'order_id'            => $order_id,
				'user_id'             => $user_id,
				'origin'              => sanitize_text_field( $origin ),
				'kickbox_result'      => wp_json_encode( $kickbox_result ),
				'verification_action' => sanitize_text_field( $verification_action ),
				'admin_decision'      => 'pending',
				'flagged_date'        => current_time( 'mysql' )
			),
			array(
				'%s', // email
				'%d', // order_id
				'%d', // user_id
				'%s', // origin
				'%s', // kickbox_result
				'%s', // verification_action
				'%s', // admin_decision
				'%s'  // flagged_date
			)
		);

		if ( $result === false ) {
			return false;
		}

		$insert_id = $wpdb->insert_id;

		// Clear cache when new data is inserted
		$this->clear_all_cache();

		return $insert_id;
	}

	/**
	 * Get flagged email by ID
	 *
	 * @param int $id Flagged email ID
	 *
	 * @return object|null Flagged email object or null if not found
	 */
	public function get_flagged_email( $id ) {
		$cache_key = $this->get_cache_key_by_id( $id );

		// Try to get from cache first
		$result = wp_cache_get( $cache_key, $this->cache_group );

		if ( $result === false ) {
			global $wpdb;

			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE id = %d",
					$this->table_name,
					$id
				)
			);

			if ( $result ) {
				$result->kickbox_result = json_decode( $result->kickbox_result, true );
			}

			// Cache the result (even if null)
			wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_expiration );
		}

		return $result;
	}

	/**
	 * Get flagged email by email address and decision status
	 *
	 * @param string $email Email address
	 *
	 * @return object|null Flagged email object or null if not found
	 */
	public function get_flagged_email_by_email( $email ) {
		$cache_key = $this->get_cache_key_by_email( $email );

		// Try to get from cache first
		$result = wp_cache_get( $cache_key, $this->cache_group );

		if ( $result === false ) {
			global $wpdb;

			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE email = %s ORDER BY flagged_date DESC LIMIT 1",
					$this->table_name,
					sanitize_email( $email )
				)
			);

			if ( $result ) {
				$result->kickbox_result = json_decode( $result->kickbox_result, true );
			}

			// Cache the result (even if null)
			wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_expiration );
		}

		return $result;
	}

	/**
	 * Get all flagged emails with pagination and search
	 *
	 * @param array $args Query arguments
	 *
	 * @return array Array of flagged emails with pagination info
	 */
	public function get_flagged_emails( $args = array() ) {
		$defaults = array(
			'page'                => 1,
			'per_page'            => 20,
			'search'              => '',
			'decision'            => '',
			'origin'              => '',
			'verification_action' => '',
			'orderby'             => 'flagged_date',
			'order'               => 'DESC'
		);

		$args = wp_parse_args( $args, $defaults );

		$cache_key = $this->get_cache_key_for_list( $args );

		// Try to get from cache first
		$result = wp_cache_get( $cache_key, $this->cache_group );

		if ( $result !== false ) {
			return $result;
		}

		global $wpdb;

		// Search by email
		$email_like_filter = '%';
		if ( ! empty( $args['search'] ) ) {
			$email_like_filter = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		// Filter by decision
		$admin_decision_filter = '%';
		if ( ! empty( $args['decision'] ) ) {
			$admin_decision_filter = $wpdb->esc_like( $args['decision'] );
		}

		// Filter by origin
		$origin_filter = '%';
		if ( ! empty( $args['origin'] ) ) {
			$origin_filter = $wpdb->esc_like( $args['origin'] );
		}

		// Filter by verification action
		$verification_action_filter = '%';
		if ( ! empty( $args['verification_action'] ) ) {
			$verification_action_filter = $wpdb->esc_like( $args['verification_action'] );
		}

		// Count total records
		$total_items =
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE admin_decision LIKE %s AND origin LIKE %s AND verification_action LIKE %s AND email LIKE %s",
					$this->table_name,
					$admin_decision_filter,
					$origin_filter,
					$verification_action_filter,
					$email_like_filter
				)
			);

		// Calculate pagination
		$offset      = ( $args['page'] - 1 ) * $args['per_page'];
		$total_pages = ceil( $total_items / $args['per_page'] );

		// Get records
		$orderby = sanitize_sql_orderby( $args['orderby'] );

		//TODO: Need to figure out how to dynamically set this
		$asc_or_desc = sanitize_sql_orderby( $args['order'] );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i  WHERE admin_decision LIKE %s AND origin LIKE %s AND verification_action LIKE %s AND email LIKE %s ORDER BY %i DESC LIMIT %d OFFSET %d",
				$this->table_name,
				$admin_decision_filter,
				$origin_filter,
				$verification_action_filter,
				$email_like_filter,
				$orderby,
				$args['per_page'],
				$offset
			)
		);

		// Decode JSON for each result
		foreach ( $results as $result ) {
			$result->kickbox_result = json_decode( $result->kickbox_result, true );
		}

		$result_data = array(
			'items'        => $results,
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
			'current_page' => $args['page'],
			'per_page'     => $args['per_page']
		);

		// Cache the result
		wp_cache_set( $cache_key, $result_data, $this->cache_group, $this->cache_expiration );

		return $result_data;
	}

	/**
	 * Update admin decision for a flagged email
	 *
	 * @param int $id Flagged email ID
	 * @param string $decision Admin decision (allow, block)
	 * @param string $notes Optional admin notes
	 *
	 * @return bool True on success, false on failure
	 */
	public function update_admin_decision( $id, $decision, $notes = '' ) {
		global $wpdb;

		if ( ! in_array( $decision, array( 'allow', 'block' ), true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table_name,
			array(
				'admin_decision' => sanitize_text_field( $decision ),
				'admin_notes'    => sanitize_textarea_field( $notes ),
				'reviewed_date'  => current_time( 'mysql' ),
				'reviewed_by'    => get_current_user_id()
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Clear cache when data is updated
			$this->clear_all_cache();
		}

		return $result !== false;
	}

	/**
	 * Edit admin decision for an already reviewed email
	 *
	 * @param int $id Flagged email ID
	 * @param string $decision Admin decision (allow, block)
	 * @param string $notes Optional admin notes
	 *
	 * @return bool True on success, false on failure
	 */
	public function edit_admin_decision( $id, $decision, $notes = '' ) {
		global $wpdb;

		if ( ! in_array( $decision, array( 'allow', 'block' ), true ) ) {
			return false;
		}

		// Check if the email exists and has been reviewed
		$existing = $this->get_flagged_email( $id );
		if ( ! $existing || $existing->admin_decision === 'pending' ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table_name,
			array(
				'admin_decision' => sanitize_text_field( $decision ),
				'admin_notes'    => sanitize_textarea_field( $notes ),
				'reviewed_date'  => current_time( 'mysql' ),
				'reviewed_by'    => get_current_user_id()
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Clear cache when data is updated
			$this->clear_all_cache();
		}

		return $result !== false;
	}

	/**
	 * Get admin decision for an email
	 *
	 * @param string $email Email address
	 *
	 * @return string|null Admin decision or null if no decision
	 */
	public function get_admin_decision( $email ) {
		$flagged_email = $this->get_flagged_email_by_email( $email );
		if ( $flagged_email ) {
			return $flagged_email->admin_decision;
		}

		return null;
	}

	/**
	 * Get kickbox result of a flagged email
	 *
	 * @param $email
	 *
	 * @return mixed
	 */
	public function get_kickbox_result( $email ) {
		$flagged_email = $this->get_flagged_email_by_email( $email );

		return $flagged_email->kickbox_result;
	}

	/**
	 * Get statistics for flagged emails
	 *
	 * @return array Statistics array
	 */
	public function get_statistics() {
		$cache_key = $this->get_cache_key_for_stats();

		// Try to get from cache first
		$stats = wp_cache_get( $cache_key, $this->cache_group );

		if ( $stats !== false ) {
			return $stats;
		}

		global $wpdb;

		$stats = array();

		// Total flagged emails
		$stats['total'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $this->table_name ) );

		// Pending reviews
		$stats['pending'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE admin_decision = %s", $this->table_name, 'pending' ) );

		// Allowed emails
		$stats['allowed'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE admin_decision = %s", $this->table_name, 'allow' ) );

		// Blocked emails
		$stats['blocked'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE admin_decision = %s", $this->table_name, 'block' ) );

		// By origin
		$stats['by_origin'] = $wpdb->get_results(
			$wpdb->prepare( "SELECT origin, COUNT(*) as count FROM %i GROUP BY origin", $this->table_name ),
			OBJECT_K
		);

		// By Kickbox result
		$stats['by_result'] = $wpdb->get_results(
			$wpdb->prepare( "SELECT JSON_EXTRACT(kickbox_result, '$.result') as result, COUNT(*) as count FROM %i GROUP BY JSON_EXTRACT(kickbox_result, '$.result')", $this->table_name ),
			OBJECT_K
		);

		// Cache the result
		wp_cache_set( $cache_key, $stats, $this->cache_group, $this->cache_expiration );

		return $stats;
	}

	/**
	 * Delete flagged email
	 *
	 * @param int $id Flagged email ID
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete_flagged_email( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Clear cache when data is deleted
			$this->clear_all_cache();
		}

		return $result !== false;
	}
}
