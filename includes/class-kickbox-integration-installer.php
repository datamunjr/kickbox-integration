<?php
/**
 * Kickbox Integration Installer
 *
 * Handles plugin installation, database table creation, and default option setup.
 *
 * @package Kickbox_Integration
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kickbox Integration Installer class
 */
class Kickbox_Integration_Installer {

	/**
	 * Install the plugin
	 *
	 * @since 1.0.0
	 */
	public static function install() {
		// Create database tables
		self::create_tables();

		// Add performance indexes
		self::add_performance_indexes();

		// Set default options
		self::set_default_options();
	}

	/**
	 * Create database tables
	 *
	 * @since 1.0.0
	 */
	public static function create_tables() {
		global $wpdb;

		$verification_log_table = $wpdb->prefix . 'kickbox_integration_verification_log';
		$flagged_emails_table   = $wpdb->prefix . 'kickbox_integration_flagged_emails';

		$charset_collate = $wpdb->get_charset_collate();

		// Verification log table
		$sql_verification = "CREATE TABLE $verification_log_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			verification_result varchar(50) NOT NULL,
			verification_data longtext,
			user_id bigint(20),
			order_id bigint(20),
			origin varchar(50) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY email (email),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY origin (origin),
			KEY email_created (email, created_at),
			KEY result_created (verification_result, created_at),
			KEY origin_created (origin, created_at)
		) $charset_collate;";

		// Flagged emails table
		$sql_flagged = "CREATE TABLE $flagged_emails_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			email varchar(100) NOT NULL,
			order_id bigint(20) NULL,
			user_id bigint(20) NULL,
			origin varchar(50) NOT NULL DEFAULT 'checkout',
			kickbox_result longtext NOT NULL,
			verification_action varchar(20) NOT NULL DEFAULT 'review',
			admin_decision varchar(20) NOT NULL DEFAULT 'pending',
			admin_notes text NULL,
			flagged_date datetime DEFAULT CURRENT_TIMESTAMP,
			reviewed_date datetime NULL,
			reviewed_by bigint(20) NULL,
			PRIMARY KEY (id),
			KEY email (email),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY admin_decision (admin_decision),
			KEY verification_action (verification_action),
			KEY flagged_date (flagged_date),
			KEY origin (origin),
			KEY email_flagged (email, flagged_date),
			KEY decision_flagged (admin_decision, flagged_date),
			KEY origin_decision (origin, admin_decision),
			KEY action_decision (verification_action, admin_decision)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_verification );
		dbDelta( $sql_flagged );
	}

	/**
	 * Add performance indexes to database tables
	 *
	 * @return array Array of errors, empty if successful
	 * @since 1.0.0
	 */
	public static function add_performance_indexes() {
		global $wpdb;

		$verification_log_table = $wpdb->prefix . 'kickbox_integration_verification_log';
		$flagged_emails_table   = $wpdb->prefix . 'kickbox_integration_flagged_emails';

		// Add composite indexes to verification log table
		$verification_indexes = array(
			'email_created'  => 'email, created_at',
			'result_created' => 'verification_result, created_at',
			'origin_created' => 'origin, created_at',
		);

		foreach ( $verification_indexes as $index_name => $columns ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$index_exists = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = %s", $verification_log_table, $index_name ) );
			if ( empty( $index_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD INDEX %i (%s)", $verification_log_table, $index_name, $columns ) );
			}
		}

		// Add composite indexes to flagged emails table
		$flagged_indexes = array(
			'email_flagged'    => 'email, flagged_date',
			'decision_flagged' => 'admin_decision, flagged_date',
			'origin_decision'  => 'origin, admin_decision',
			'action_decision'  => 'verification_action, admin_decision',
		);

		$results = array();
		foreach ( $flagged_indexes as $index_name => $columns ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$index_exists = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = %s", $flagged_emails_table, $index_name ) );
			if ( empty( $index_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$results[ $index_name ] = $wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD INDEX %i (%s)", $flagged_emails_table, $index_name, $columns ) );
			}
		}

		// Collect any errors
		$errors = array();
		foreach ( $results as $index => $result ) {
			if ( false === $result ) {
				$errors[] = "Index $index could not be created, please check your mysql logs.";
			}
		}

		// Display an admin notice if the indexes weren't created
		if ( ! empty( $errors ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'display_index_creation_failure_notice' ) );
		}

		return $errors;
	}

	/**
	 * Set default plugin options
	 *
	 * @return array Array of errors, empty if successful
	 * @since 1.0.0
	 */
	public static function set_default_options() {
		$default_options = array(
			'kickbox_integration_api_key'                          => '',
			'kickbox_integration_deliverable_action'               => 'allow',
			'kickbox_integration_undeliverable_action'             => 'block',
			'kickbox_integration_risky_action'                     => 'block',
			'kickbox_integration_unknown_action'                   => 'block',
			'kickbox_integration_enable_checkout_verification'     => 'no',
			'kickbox_integration_enable_registration_verification' => 'no',
			'kickbox_integration_allow_list'                       => array(),
		);

		$results = array();
		foreach ( $default_options as $option => $value ) {
			if ( false === get_option( $option ) ) {
				$results[ $option ] = add_option( $option, $value );
			}
		}

		$errors = array();
		foreach ( $results as $option => $result ) {
			if ( false === $result ) {
				$errors[] = "Option $option failed to persist. Please check your mysql logs.";
			}
		}

		return $errors;
	}

	/**
	 * Display admin notice for index creation failure
	 *
	 * @since 1.0.0
	 */
	public static function display_index_creation_failure_notice() {
		?>
		<div class="error">
			<p>
				<strong><?php esc_html_e( 'Kickbox Integration', 'kickbox-integration' ); ?></strong>
				<?php esc_html_e( 'could not create certain MySQL indexes. Please check your MySQL logs or reach out to support.', 'kickbox-integration' ); ?>
			</p>
		</div>
		<?php
	}
}

