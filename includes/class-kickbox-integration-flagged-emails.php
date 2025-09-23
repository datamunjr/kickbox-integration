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
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'kickbox_integration_flagged_emails';
	}

	/**
	 * Ensure the flagged emails table exists
	 */
	private function ensure_table_exists() {
		global $wpdb;

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" );

		if ( ! $table_exists ) {
			// Table doesn't exist, create it
			kickbox_integration_create_tables();
		}
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

		return $wpdb->insert_id;
	}

	/**
	 * Get flagged email by ID
	 *
	 * @param int $id Flagged email ID
	 *
	 * @return object|null Flagged email object or null if not found
	 */
	public function get_flagged_email( $id ) {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			)
		);

		if ( $result ) {
			$result->kickbox_result = json_decode( $result->kickbox_result, true );
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
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE email = %s ORDER BY flagged_date DESC LIMIT 1",
				sanitize_email( $email )
			)
		);

		if ( $result ) {
			$result->kickbox_result = json_decode( $result->kickbox_result, true );
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
		global $wpdb;

		// Check if table exists, create if not
		$this->ensure_table_exists();

		$defaults = array(
			'page'     => 1,
			'per_page' => 20,
			'search'   => '',
			'decision' => '',
			'origin'   => '',
			'verification_action' => '',
			'orderby'  => 'flagged_date',
			'order'    => 'DESC'
		);

		$args = wp_parse_args( $args, $defaults );

		$where_conditions = array( '1=1' );
		$where_values     = array();

		// Search by email
		if ( ! empty( $args['search'] ) ) {
			$where_conditions[] = 'email LIKE %s';
			$where_values[]     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		// Filter by decision
		if ( ! empty( $args['decision'] ) ) {
			$where_conditions[] = 'admin_decision = %s';
			$where_values[]     = sanitize_text_field( $args['decision'] );
		}

		// Filter by origin
		if ( ! empty( $args['origin'] ) ) {
			$where_conditions[] = 'origin = %s';
			$where_values[]     = sanitize_text_field( $args['origin'] );
		}

		// Filter by verification action
		if ( ! empty( $args['verification_action'] ) ) {
			$where_conditions[] = 'verification_action = %s';
			$where_values[]     = sanitize_text_field( $args['verification_action'] );
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Count total records
		$count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values );
		}
		$total_items = $wpdb->get_var( $count_query );

		// Calculate pagination
		$offset      = ( $args['page'] - 1 ) * $args['per_page'];
		$total_pages = ceil( $total_items / $args['per_page'] );

		// Get records
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$query   = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";

		$query_values = array_merge( $where_values, array( $args['per_page'], $offset ) );
		$query        = $wpdb->prepare( $query, $query_values );

		$results = $wpdb->get_results( $query );

		// Decode JSON for each result
		foreach ( $results as $result ) {
			$result->kickbox_result = json_decode( $result->kickbox_result, true );
		}

		return array(
			'items'        => $results,
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
			'current_page' => $args['page'],
			'per_page'     => $args['per_page']
		);
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
		global $wpdb;

		$stats = array();

		// Total flagged emails
		$stats['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		// Pending reviews
		$stats['pending'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE admin_decision = 'pending'" );

		// Allowed emails
		$stats['allowed'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE admin_decision = 'allow'" );

		// Blocked emails
		$stats['blocked'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE admin_decision = 'block'" );

		// By origin
		$stats['by_origin'] = $wpdb->get_results(
			"SELECT origin, COUNT(*) as count FROM {$this->table_name} GROUP BY origin",
			OBJECT_K
		);

		// By Kickbox result
		$stats['by_result'] = $wpdb->get_results(
			"SELECT JSON_EXTRACT(kickbox_result, '$.result') as result, COUNT(*) as count FROM {$this->table_name} GROUP BY JSON_EXTRACT(kickbox_result, '$.result')",
			OBJECT_K
		);

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

		return $result !== false;
	}
}
