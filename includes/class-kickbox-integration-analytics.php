<?php
/**
 * Kickbox_Integration_Analytics Class
 *
 * Handles email verification analytics and statistics calculations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kickbox_Integration_Analytics {

	/**
	 * Cache group for analytics data
	 *
	 * @var string
	 */
	private $cache_group = 'kickbox_analytics';

	/**
	 * Cache expiration time in seconds (1 hour)
	 *
	 * @var int
	 */
	private $cache_expiration = 3600;

	/**
	 * Database table name for verification logs
	 *
	 * @var string
	 */
	private $verification_logs_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->verification_logs_table = $wpdb->prefix . 'kickbox_integration_verification_log';

		// Register AJAX handlers
		add_action( 'wp_ajax_kickbox_integration_get_stats', array( $this, 'ajax_get_verification_stats' ) );
		add_action( 'wp_ajax_kickbox_integration_dashboard_stats', array( $this, 'ajax_get_dashboard_stats' ) );
		
		// Register WooCommerce Analytics report
		add_filter( 'woocommerce_analytics_report_menu_items', array( $this, 'register_analytics_report' ) );
		
		// Enqueue scripts and styles for WooCommerce Analytics
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 1 );
	}

	/**
	 * Generate cache key for verification statistics
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_stats() {
		return "verification_stats";
	}

	/**
	 * Generate cache key for verification reason statistics
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_reason_stats() {
		return "verification_reason_stats";
	}

	/**
	 * Generate cache key for dashboard statistics
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_dashboard_stats() {
		return "dashboard_stats";
	}

	/**
	 * Clear all cached analytics data
	 */
	public function clear_analytics_cache() {
		wp_cache_flush_group( $this->cache_group );
	}

	/**
	 * Get verification statistics
	 *
	 * @param string $start_date Start date (Y-m-d format)
	 * @param string $end_date End date (Y-m-d format)
	 * @return array Statistics
	 */
	public function get_verification_stats( $start_date = null, $end_date = null ) {
		$cache_key = $this->get_cache_key_for_stats() . '_' . ( $start_date ?: 'all' ) . '_' . ( $end_date ?: 'all' );

		// Try to get from cache first
		$stats = wp_cache_get( $cache_key, $this->cache_group );

		if ( $stats !== false ) {
			return $stats;
		}

		global $wpdb;

		$where_clause = '';
		$prepare_args = array( $this->verification_logs_table );

		// Add date filtering if provided
		if ( $start_date && $end_date ) {
			$where_clause = ' WHERE created_at >= %s AND created_at <= %s';
			$prepare_args[] = $start_date . ' 00:00:00';
			$prepare_args[] = $end_date . ' 23:59:59';
		} elseif ( $start_date ) {
			$where_clause = ' WHERE created_at >= %s';
			$prepare_args[] = $start_date . ' 00:00:00';
		} elseif ( $end_date ) {
			$where_clause = ' WHERE created_at <= %s';
			$prepare_args[] = $end_date . ' 23:59:59';
		}

		// Build the complete SQL query
		$sql = "SELECT verification_result, COUNT(*) as count FROM {$this->verification_logs_table}{$where_clause} GROUP BY verification_result";
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $prepare_args ) ) {
			$stats = $wpdb->get_results( $sql );
		} else {
			$stats = $wpdb->get_results( $wpdb->prepare( $sql, $prepare_args ) );
		}

		// Cache the result
		wp_cache_set( $cache_key, $stats, $this->cache_group, $this->cache_expiration );

		return $stats;
	}

	/**
	 * Get verification result reason statistics
	 *
	 * @param string $start_date Start date (Y-m-d format)
	 * @param string $end_date End date (Y-m-d format)
	 * @return array Statistics
	 */
	public function get_verification_reason_stats( $start_date = null, $end_date = null ) {
		$cache_key = $this->get_cache_key_for_reason_stats() . '_' . ( $start_date ?: 'all' ) . '_' . ( $end_date ?: 'all' );

		// Try to get from cache first
		$stats = wp_cache_get( $cache_key, $this->cache_group );

		if ( $stats !== false ) {
			return $stats;
		}

		global $wpdb;

		$where_clause = ' WHERE verification_data IS NOT NULL';
		$prepare_args = array( $this->verification_logs_table );

		// Add date filtering if provided
		if ( $start_date && $end_date ) {
			$where_clause .= ' AND created_at >= %s AND created_at <= %s';
			$prepare_args[] = $start_date . ' 00:00:00';
			$prepare_args[] = $end_date . ' 23:59:59';
		} elseif ( $start_date ) {
			$where_clause .= ' AND created_at >= %s';
			$prepare_args[] = $start_date . ' 00:00:00';
		} elseif ( $end_date ) {
			$where_clause .= ' AND created_at <= %s';
			$prepare_args[] = $end_date . ' 23:59:59';
		}

		// Build the complete SQL query
		$sql = "SELECT verification_data FROM {$this->verification_logs_table}{$where_clause}";
		
		// Get all verification records with their data
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $prepare_args ) ) {
			$records = $wpdb->get_results( $sql );
		} else {
			$records = $wpdb->get_results( $wpdb->prepare( $sql, $prepare_args ) );
		}

		$reason_counts = array();

		foreach ( $records as $record ) {
			$data = json_decode( $record->verification_data, true );
			if ( $data && isset( $data['reason'] ) ) {
				$reason                   = $data['reason'];
				$reason_counts[ $reason ] = ( $reason_counts[ $reason ] ?? 0 ) + 1;
			}
		}

		// Convert to array format similar to verification stats
		$stats = array();
		foreach ( $reason_counts as $reason => $count ) {
			$stats[] = array(
				'result_reason' => $reason,
				'count'         => $count
			);
		}

		// Sort by count descending
		usort( $stats, function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		// Cache the result
		wp_cache_set( $cache_key, $stats, $this->cache_group, $this->cache_expiration );

		return $stats;
	}

	/**
	 * Get comprehensive verification statistics for dashboard
	 *
	 * @return array Dashboard statistics
	 */
	public function get_dashboard_stats() {
		$cache_key = $this->get_cache_key_for_dashboard_stats();

		// Try to get from cache first
		$stats = wp_cache_get( $cache_key, $this->cache_group );

		if ( $stats !== false ) {
			return $stats;
		}

		$stats = array(
			'verification_stats' => $this->get_verification_stats(),
			'reason_stats'       => $this->get_verification_reason_stats(),
			'total_verifications' => $this->get_total_verifications(),
			'today_verifications' => $this->get_today_verifications(),
			'this_week_verifications' => $this->get_this_week_verifications(),
			'this_month_verifications' => $this->get_this_month_verifications()
		);

		// Cache the result
		wp_cache_set( $cache_key, $stats, $this->cache_group, $this->cache_expiration );

		return $stats;
	}

	/**
	 * Get total number of verifications
	 *
	 * @param string $start_date Start date (Y-m-d format)
	 * @param string $end_date End date (Y-m-d format)
	 * @return int Total verifications
	 */
	public function get_total_verifications( $start_date = null, $end_date = null ) {
		$cache_key = $this->get_cache_key_for_total_verifications() . '_' . ( $start_date ?: 'all' ) . '_' . ( $end_date ?: 'all' );
		
		// Try to get from cache first
		$count = wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $count !== false ) {
			return intval( $count );
		}

		global $wpdb;

		$where_clause = '';
		$prepare_args = array( $this->verification_logs_table );

		// Add date filtering if provided
		if ( $start_date && $end_date ) {
			$where_clause = ' WHERE created_at >= %s AND created_at <= %s';
			$prepare_args[] = $start_date . ' 00:00:00';
			$prepare_args[] = $end_date . ' 23:59:59';
		} elseif ( $start_date ) {
			$where_clause = ' WHERE created_at >= %s';
			$prepare_args[] = $start_date . ' 00:00:00';
		} elseif ( $end_date ) {
			$where_clause = ' WHERE created_at <= %s';
			$prepare_args[] = $end_date . ' 23:59:59';
		}

		// Build the complete SQL query
		$sql = "SELECT COUNT(*) FROM {$this->verification_logs_table}{$where_clause}";
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $prepare_args ) ) {
			$count = $wpdb->get_var( $sql );
		} else {
			$count = $wpdb->get_var( $wpdb->prepare( $sql, $prepare_args ) );
		}

		// Cache the result
		wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expiration );

		return intval( $count );
	}

	/**
	 * Get verifications for today
	 *
	 * @return int Today's verifications
	 */
	public function get_today_verifications() {
		$cache_key = $this->get_cache_key_for_today_verifications();
		
		// Try to get from cache first
		$count = wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $count !== false ) {
			return intval( $count );
		}

		global $wpdb;

		$today = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE DATE(created_at) = %s",
			$this->verification_logs_table,
			$today
		) );

		// Cache the result
		wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expiration );

		return intval( $count );
	}

	/**
	 * Get verifications for this week
	 *
	 * @return int This week's verifications
	 */
	public function get_this_week_verifications() {
		$cache_key = $this->get_cache_key_for_week_verifications();
		
		// Try to get from cache first
		$count = wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $count !== false ) {
			return intval( $count );
		}

		global $wpdb;

		$week_start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE DATE(created_at) >= %s",
			$this->verification_logs_table,
			$week_start
		) );

		// Cache the result
		wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expiration );

		return intval( $count );
	}

	/**
	 * Get verifications for this month
	 *
	 * @return int This month's verifications
	 */
	public function get_this_month_verifications() {
		$cache_key = $this->get_cache_key_for_month_verifications();
		
		// Try to get from cache first
		$count = wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $count !== false ) {
			return intval( $count );
		}

		global $wpdb;

		$month_start = gmdate( 'Y-m-01' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE DATE(created_at) >= %s",
			$this->verification_logs_table,
			$month_start
		) );

		// Cache the result
		wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expiration );

		return intval( $count );
	}

	/**
	 * Get verification statistics by date range
	 *
	 * @param string $start_date Start date (Y-m-d format)
	 * @param string $end_date End date (Y-m-d format)
	 * @return array Statistics for date range
	 */
	public function get_verification_stats_by_date_range( $start_date, $end_date ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT verification_result, COUNT(*) as count FROM %i WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY verification_result",
			$this->verification_logs_table,
			$start_date,
			$end_date
		) );

		return $stats;
	}

	/**
	 * Get verification trends (daily counts for the last 30 days)
	 *
	 * @return array Daily verification counts
	 */
	public function get_verification_trends() {
		$cache_key = $this->get_cache_key_for_trends();
		
		// Try to get from cache first
		$trends = wp_cache_get( $cache_key, $this->cache_group );
		
		if ( $trends !== false ) {
			return $trends;
		}

		global $wpdb;

		$thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$trends = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) as date, COUNT(*) as count FROM %i WHERE DATE(created_at) >= %s GROUP BY DATE(created_at) ORDER BY date ASC",
			$this->verification_logs_table,
			$thirty_days_ago
		) );

		// Cache the result
		wp_cache_set( $cache_key, $trends, $this->cache_group, $this->cache_expiration );

		return $trends;
	}

	/**
	 * AJAX handler for getting verification statistics
	 */
	public function ajax_get_verification_stats() {
		check_ajax_referer( 'kickbox_integration_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
		}

		// Get date range parameters
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null;
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null;

		// Validate date format if provided
		if ( $start_date && ! $this->validate_date_format( $start_date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid start date format. Use YYYY-MM-DD.', 'kickbox-integration' ) ) );
		}
		if ( $end_date && ! $this->validate_date_format( $end_date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid end date format. Use YYYY-MM-DD.', 'kickbox-integration' ) ) );
		}

		$stats        = $this->get_verification_stats( $start_date, $end_date );
		$reason_stats = $this->get_verification_reason_stats( $start_date, $end_date );
		$rates       = $this->get_success_failure_rates( $start_date, $end_date );

		wp_send_json_success( array(
			'verification_stats' => $stats,
			'reason_stats'       => $reason_stats,
			'success_failure_rates' => $rates,
			'date_range' => array(
				'start_date' => $start_date,
				'end_date' => $end_date
			)
		) );
	}

	/**
	 * AJAX handler for getting dashboard statistics
	 */
	public function ajax_get_dashboard_stats() {
		check_ajax_referer( 'kickbox_integration_dashboard', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kickbox-integration' ) ) );
		}

		$stats = $this->get_dashboard_stats();

		wp_send_json_success( $stats );
	}

	/**
	 * Get verification statistics summary for display
	 *
	 * @return array Formatted statistics for UI display
	 */
	public function get_stats_summary() {
		$verification_stats = $this->get_verification_stats();
		$reason_stats = $this->get_verification_reason_stats();
		$total = $this->get_total_verifications();

		$summary = array(
			'total_verifications' => $total,
			'by_result' => array(),
			'by_reason' => $reason_stats,
			'percentages' => array()
		);

		// Calculate percentages and format by result
		foreach ( $verification_stats as $stat ) {
			$result = $stat->verification_result;
			$count = intval( $stat->count );
			$percentage = $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0;

			$summary['by_result'][$result] = array(
				'count' => $count,
				'percentage' => $percentage
			);

			$summary['percentages'][$result] = $percentage;
		}

		return $summary;
	}

	/**
	 * Get success and failure rates for analytics
	 *
	 * @param string $start_date Start date (Y-m-d format)
	 * @param string $end_date End date (Y-m-d format)
	 * @return array Success and failure rate statistics
	 */
	public function get_success_failure_rates( $start_date = null, $end_date = null ) {
		$cache_key = 'success_failure_rates_' . ( $start_date ?: 'all' ) . '_' . ( $end_date ?: 'all' );

		// Try to get from cache first
		$rates = wp_cache_get( $cache_key, $this->cache_group );

		if ( $rates !== false ) {
			return $rates;
		}

		$verification_stats = $this->get_verification_stats( $start_date, $end_date );
		$total = $this->get_total_verifications( $start_date, $end_date );

		// Initialize counters
		$successful_verifications = 0;
		$failed_verifications = 0;

		// Count successful and failed verifications
		foreach ( $verification_stats as $stat ) {
			$result = $stat->verification_result;
			$count = intval( $stat->count );

			// Consider 'deliverable' as successful, everything else as failed
			if ( $result === 'deliverable' ) {
				$successful_verifications += $count;
			} else {
				$failed_verifications += $count;
			}
		}

		// Calculate rates
		$success_rate = $total > 0 ? round( ( $successful_verifications / $total ) * 100, 1 ) : 0;
		$failure_rate = $total > 0 ? round( ( $failed_verifications / $total ) * 100, 1 ) : 0;

		$rates = array(
			'successful_verifications' => $successful_verifications,
			'failed_verifications' => $failed_verifications,
			'success_rate' => $success_rate,
			'failure_rate' => $failure_rate,
			'total_verifications' => $total
		);

		// Cache the result
		wp_cache_set( $cache_key, $rates, $this->cache_group, $this->cache_expiration );

		return $rates;
	}

	/**
	 * Validate date format (YYYY-MM-DD)
	 *
	 * @param string $date Date string to validate
	 * @return bool True if valid format
	 */
	private function validate_date_format( $date ) {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Register analytics report with WooCommerce
	 *
	 * @param array $reports Existing reports
	 * @return array Modified reports
	 */
	public function register_analytics_report( $report_pages ) {
		// Find the position of the Settings page
		$settings_index = -1;
		foreach ( $report_pages as $index => $page ) {
			if ( isset( $page['id'] ) && $page['id'] === 'woocommerce-analytics-settings' ) {
				$settings_index = $index;
				break;
			}
		}
		
		// Create our report page
		$email_verification_report = array(
			'id'     => 'kickbox-email-verifications',
			'title'  => __( 'Email Verification', 'kickbox-integration' ),
			'parent' => 'woocommerce-analytics',
			'path'   => '/analytics/kickbox-email-verifications',
		);
		
		// Insert our report before the Settings page
		if ( $settings_index >= 0 ) {
			array_splice( $report_pages, $settings_index, 0, array( $email_verification_report ) );
		} else {
			// If Settings page not found, just append to the end
			$report_pages[] = $email_verification_report;
		}
		
		return $report_pages;
	}

	/**
	 * Enqueue admin scripts and styles for WooCommerce Analytics
	 */
	public function enqueue_admin_scripts() {

		// For whatever reason, adding this script to the dependencies to the wp_enqueue_script() below brakes the
		// Email Verification Report page
		wp_enqueue_script( 'wc-admin-components' );
		
		// Enqueue the analytics JavaScript bundle
		wp_enqueue_script(
			'kickbox-integration-analytics',
			KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/js/analytics.js',
			array( 'react', 'react-dom', 'wp-hooks', 'wp-i18n', 'wp-components', 'wc-components', 'wc-admin-app' ),
			KICKBOX_INTEGRATION_VERSION,
			true
		);

		// Enqueue analytics CSS if it exists
		$css_file = KICKBOX_INTEGRATION_PLUGIN_DIR . 'assets/css/analytics-styles.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'kickbox-integration-analytics',
				KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/css/analytics-styles.css',
				array(),
				KICKBOX_INTEGRATION_VERSION
			);
		}

		// Localize script for AJAX
		wp_localize_script( 'kickbox-integration-analytics', 'kickboxAnalytics', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'kickbox_integration_admin' ),
		) );
	}

	/**
	 * Get cache key for total verifications
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_total_verifications() {
		return 'kickbox_total_verifications';
	}

	/**
	 * Get cache key for today's verifications
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_today_verifications() {
		return 'kickbox_today_verifications_' . current_time( 'Y-m-d' );
	}

	/**
	 * Get cache key for week verifications
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_week_verifications() {
		return 'kickbox_week_verifications_' . gmdate( 'Y-W' );
	}

	/**
	 * Get cache key for month verifications
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_month_verifications() {
		return 'kickbox_month_verifications_' . gmdate( 'Y-m' );
	}

	/**
	 * Get cache key for trends
	 *
	 * @return string Cache key
	 */
	private function get_cache_key_for_trends() {
		return 'kickbox_trends_' . gmdate( 'Y-m-d' );
	}

}
