<?php
/**
 * Flagged Emails List Table
 *
 * @package Kickbox_Integration
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure WP_List_Table is loaded when this class is used
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Flagged Emails List Table Class
 *
 * Extends WP_List_Table to display flagged emails with sorting, pagination, and admin page functionality.
 */
class Kickbox_Integration_Flagged_Emails_Table extends WP_List_Table {

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
		// Ensure WP_List_Table is available
		if ( ! class_exists( 'WP_List_Table' ) ) {
			wp_die( esc_html__( 'WP_List_Table class not found. This feature requires WordPress admin.', 'kickbox-integration' ) );
		}

		parent::__construct( array(
			'singular' => 'flagged_email',
			'plural'   => 'flagged_emails',
			'ajax'     => false
		) );

		global $wpdb;
		$this->table_name = $wpdb->prefix . 'kickbox_integration_flagged_emails';
	}

	/**
	 * Initialize admin page functionality
	 */
	public function init_admin_page() {

		// Note: there is a difference between get_current_screen() and the parent->screen at this point in time
		// in the callstack. If we didn't call this method here, the screen options UI wouldn't be displaying the
		// column allow users to hide or reveal specific columns.
		$this->screen = get_current_screen();

		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
		add_action( 'admin_notices', array( $this, 'review_action_notices' ) );

		// Register column filter after screen is available
		if ( $this->screen && $this->screen->id ) {
			add_filter( "manage_{$this->screen->id}_columns", array( $this, 'get_columns' ), 0 );
		}

		// Add filter for screen option saving
		add_filter( 'set_screen_option_flagged_emails_per_page', array( $this, 'set_items_per_page' ), 10, 3 );

		$this->set_items_per_page_option();
		set_screen_options();

		if ( $this->current_action() ) {
			$this->handle_bulk_actions();
		}
	}

	/**
	 * Get table columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'                  => '<input type="checkbox" />',
			'email'               => __( 'Email', 'kickbox-integration' ),
			'kickbox_result'      => __( 'Kickbox Result', 'kickbox-integration' ),
			'verification_action' => __( 'Action at Verification', 'kickbox-integration' ),
			'admin_decision'      => __( 'Decision', 'kickbox-integration' ),
			'origin'              => __( 'Origin', 'kickbox-integration' ),
			'order_id'            => __( 'Order ID', 'kickbox-integration' ),
			'flagged_date'        => __( 'Flagged Date', 'kickbox-integration' ),
		);
	}

	/**
	 * Get filter views for the table
	 *
	 * @return array
	 */
	public function get_views() {
		$views = array();

		// Get current filter
		$current_filter = isset( $_GET['verification_action'] ) ? sanitize_text_field( wp_unslash( $_GET['verification_action'] ) ) : '';

		// All items link
		$class        = ( '' === $current_filter ) ? 'current' : '';
		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( remove_query_arg( 'verification_action' ) ),
			$class,
			__( 'All', 'kickbox-integration' ),
			$this->get_total_items_count()
		);

		// Flagged items link
		$class            = ( 'review' === $current_filter ) ? 'current' : '';
		$views['flagged'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'verification_action', 'review' ) ),
			$class,
			__( 'Flagged', 'kickbox-integration' ),
			$this->get_filtered_items_count( 'review' )
		);

		// Blocked items link
		$class            = ( 'block' === $current_filter ) ? 'current' : '';
		$views['blocked'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'verification_action', 'block' ) ),
			$class,
			__( 'Blocked', 'kickbox-integration' ),
			$this->get_filtered_items_count( 'block' )
		);

		return $views;
	}

	/**
	 * Get total items count for all items
	 *
	 * @return int
	 */
	private function get_total_items_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i",
			$this->table_name
		) );

		return intval( $count );
	}

	/**
	 * Get filtered items count for specific verification action
	 *
	 * @param string $verification_action
	 *
	 * @return int
	 */
	private function get_filtered_items_count( $verification_action ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE verification_action = %s",
			$this->table_name,
			$verification_action
		) );

		return intval( $count );
	}

	/**
	 * Get distinct origin values from database
	 *
	 * @return array
	 */
	private function get_origin_values() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$origins = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT origin FROM %i WHERE origin IS NOT NULL AND origin != '' ORDER BY origin ASC",
			$this->table_name
		) );

		return $origins;
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'email'          => array( 'email', false ),
			'admin_decision' => array( 'admin_decision', false ),
			'flagged_date'   => array( 'flagged_date', true ),
			'origin'         => array( 'origin', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'allow' => __( 'Allow', 'kickbox-integration' ),
			'block' => __( 'Block', 'kickbox-integration' ),
		);
	}

	/**
	 * Column for checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item['id']
		);
	}

	/**
	 * Column default
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'email':
				$output = '<strong>' . esc_html( $item['email'] ) . '</strong>';
				if ( ! empty( $item['user_id'] ) ) {
					$user_id = intval( $item['user_id'] );
					$user    = get_user_by( 'id', $user_id );

					if ( $user ) {
						$user_edit_url = get_edit_user_link( $user_id );
						$output        .= '<div style="font-size: 12px; color: #666;">';
						$output        .= sprintf(
						// translators: %s is the user ID with a link to edit the user
							__( 'User ID: %s (click to edit)', 'kickbox-integration' ),
							'<a href="' . esc_url( $user_edit_url ) . '" target="_blank">' . esc_html( $user_id ) . '</a>'
						);
						$output        .= '</div>';
					} else {
						$output .= '<div style="font-size: 12px; color: #666;">';
						// translators: %s is the user ID
						$output .= sprintf( __( 'User ID: %s', 'kickbox-integration' ), $user_id );
						$output .= '</div>';
					}
				}

				return $output;

			case 'kickbox_result':
				$result = $item['kickbox_result']['result'] ?? 'unknown';
				$reason = $item['kickbox_result']['reason'] ?? '';

				$output = '<strong>' . esc_html( ucfirst( $result ) ) . '</strong>';

				if ( $reason ) {
					$output .= '<div style="font-size: 12px; color: #666; margin-top: 4px;">';
					// translators: %s is the reason for the kickbox result
					$output .= sprintf( __( 'Reason: %s', 'kickbox-integration' ), esc_html( $reason ) );
					$output .= '</div>';
				}

				return $output;

			case 'verification_action':
				$action      = $item['verification_action'];
				$action_text = $action === 'block' ? __( 'Blocked', 'kickbox-integration' ) : __( 'Flagged', 'kickbox-integration' );

				return '<strong>' . esc_html( $action_text ) . '</strong>';

			case 'admin_decision':
				return $this->column_admin_decision( $item );

			case 'origin':
				return esc_html( ucfirst( $item['origin'] ) );

			case 'order_id':
				if ( ! empty( $item['order_id'] ) ) {
					$order_url = admin_url( 'post.php?post=' . $item['order_id'] . '&action=edit' );

					return '<a href="' . esc_url( $order_url ) . '" target="_blank">#' . esc_html( $item['order_id'] ) . '</a>';
				}

				return '-';

			case 'flagged_date':
				return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['flagged_date'] ) ) );

			default:
				return esc_html( $item[ $column_name ] ?? '' );
		}
	}

	/**
	 * Admin decision column content
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function column_admin_decision( $item ) {
		$item_id  = intval( $item['id'] );
		$decision = $item['admin_decision'];

		// Make the decision clickable to open modal
		$output = '<div class="kickbox-decision-clickable" data-item-id="' . esc_attr( $item_id ) . '" style="cursor: pointer; color: #0073aa;">';
		$output .= '<strong>' . esc_html( ucfirst( $decision ) ) . '</strong>';

		if ( ! empty( $item['admin_notes'] ) ) {
			$output .= '<br><em style="font-size: 11px;">' . esc_html( $item['admin_notes'] ) . '</em>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		global $wpdb;

		// Get pagination parameters
		$per_page     = $this->get_items_per_page( 'flagged_emails_per_page' );
		$current_page = $this->get_pagenum();

		// Get search parameter
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		// Get filter parameters
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		$verification_action_filter = isset( $_REQUEST['verification_action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['verification_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		$admin_decision_filter = isset( $_REQUEST['admin_decision'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['admin_decision'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		$origin_filter = isset( $_REQUEST['origin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['origin'] ) ) : '';

		// Get sorting parameters
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) ) : 'flagged_date';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		$order = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		// Note: orderby and order validation is done later in the query building section

		// Validate verification action filter
		if ( ! in_array( $verification_action_filter, array( 'review', 'block' ) ) ) {
			$verification_action_filter = '';
		}

		// Validate admin decision filter
		if ( ! in_array( $admin_decision_filter, array( 'pending', 'allow', 'block' ) ) ) {
			$admin_decision_filter = '';
		}

		// Validate origin filter (check if it exists in database)
		if ( ! empty( $origin_filter ) ) {
			$origin_values = $this->get_origin_values();
			if ( ! in_array( $origin_filter, $origin_values ) ) {
				$origin_filter = '';
			}
		}

		// Build search conditions
		$search_conditions = '';
		$search_params     = array();
		$where_conditions  = array();

		// Add verification action filter
		if ( ! empty( $verification_action_filter ) ) {
			$where_conditions[] = "verification_action = %s";
			$search_params[]    = $verification_action_filter;
		}

		// Add admin decision filter
		if ( ! empty( $admin_decision_filter ) ) {
			$where_conditions[] = "admin_decision = %s";
			$search_params[]    = $admin_decision_filter;
		}

		// Add origin filter
		if ( ! empty( $origin_filter ) ) {
			$where_conditions[] = "origin = %s";
			$search_params[]    = $origin_filter;
		}

		// Add search filter
		if ( ! empty( $search ) ) {
			$where_conditions[] = "email LIKE %s";
			$search_params[]    = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// Combine all WHERE conditions
		if ( ! empty( $where_conditions ) ) {
			$search_conditions = " WHERE " . implode( " AND ", $where_conditions );
		}

		// Get total items with search
		$total_items_query = "SELECT COUNT(*) FROM %i" . $search_conditions;
		if ( ! empty( $search_params ) ) {
			$prepared_params = array_merge( array( $this->table_name ), $search_params );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_items = $wpdb->get_var( $wpdb->prepare( $total_items_query, $prepared_params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_items = $wpdb->get_var( $wpdb->prepare( $total_items_query, $this->table_name ) );
		}

		// Get items with search
		$offset = ( $current_page - 1 ) * $per_page;

		// Build the ORDER BY clause safely using whitelist validation
		$allowed_orderby = array( 'email', 'admin_decision', 'flagged_date', 'origin' );
		$allowed_order   = array( 'ASC', 'DESC' );

		$orderby_safe = in_array( $orderby, $allowed_orderby ) ? $orderby : 'flagged_date';
		$order_safe   = in_array( strtoupper( $order ), $allowed_order ) ? strtoupper( $order ) : 'DESC';

		$items_query = "SELECT * FROM %i" . $search_conditions . " ORDER BY $orderby_safe $order_safe LIMIT %d OFFSET %d";

		if ( ! empty( $search_params ) ) {
			$prepared_params = array_merge( array( $this->table_name ), $search_params, array( $per_page, $offset ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results( $wpdb->prepare( $items_query, $prepared_params ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results( $wpdb->prepare( $items_query, $this->table_name, $per_page, $offset ), ARRAY_A );
		}

		// Process items to decode JSON fields
		$processed_items = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['kickbox_result'] ) ) {
				$item['kickbox_result'] = json_decode( $item['kickbox_result'], true );
			}
			$processed_items[] = $item;
		}

		// Set pagination
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );

		$this->items = $processed_items;
	}

	/**
	 * Handle bulk actions
	 */
	public function handle_bulk_actions() {
		// Check if bulk action is being performed
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'kickbox-integration' ) );
		}

		// Verify nonce
		$nonce_action = 'bulk-' . $this->_args['plural'];
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'kickbox-integration' ) );
		}

		// Check if items are selected
		if ( ! isset( $_GET['flagged_email'] ) || ! is_array( $_GET['flagged_email'] ) ) {
			// Set a notice that no items were selected
			set_transient( 'kickbox_bulk_notice', array(
				'type'    => 'error',
				'message' => __( 'No items were selected for bulk action.', 'kickbox-integration' )
			), 30 );

			return;
		}

		$email_ids     = array_map( 'intval', $_GET['flagged_email'] );
		$success_count = 0;
		$failed_items  = array();

		// Process each selected email
		foreach ( $email_ids as $email_id ) {
			$result = $this->update_email_decision( $email_id, $action );
			if ( $result === true ) {
				$success_count ++;
			} else {
				// Get email address for failed item
				$email_address  = $this->get_email_address_by_id( $email_id );
				$failed_items[] = array(
					'id'    => $email_id,
					'email' => $email_address ?: 'ID: ' . $email_id
				);
			}
		}

		// Store results in transient
		$transient_key = 'kickbox_bulk_action_' . get_current_user_id() . '_' . time();
		$bulk_results  = array(
			'action'          => $action,
			'success_count'   => $success_count,
			'failed_items'    => $failed_items,
			'total_processed' => count( $email_ids ),
			'timestamp'       => current_time( 'mysql' )
		);
		set_transient( $transient_key, $bulk_results, 60 ); // Expire in 1 minute

		// Redirect to prevent resubmission
		$redirect_to = add_query_arg(
			array(
				'paged'       => $this->get_pagenum(),
				'bulk_notice' => $transient_key
			), wp_get_referer() );
		$redirect_to = remove_query_arg( array( 'action', 'action2', 'flagged_email', '_wpnonce' ), $redirect_to );
		wp_safe_redirect( $redirect_to );

		exit;
	}

	/**
	 * Update email decision in database
	 *
	 * @param int $email_id
	 * @param string $decision
	 *
	 * @return bool
	 */
	private function update_email_decision( $email_id, $decision ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table_name,
			array(
				'admin_decision' => $decision,
				'reviewed_date'  => current_time( 'mysql' ),
				'admin_notes'    => __( 'Bulk action applied', 'kickbox-integration' )
			),
			array( 'id' => $email_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get email address by ID
	 *
	 * @param int $email_id
	 *
	 * @return string|false
	 */
	private function get_email_address_by_id( $email_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$email = $wpdb->get_var( $wpdb->prepare(
			"SELECT email FROM %i WHERE id = %d",
			$this->table_name,
			$email_id
		) );

		return $email ?: false;
	}

	/**
	 * Display search box
	 *
	 * @param string $text The 'submit' button label
	 * @param string $input_id The input id attribute
	 */
	public function search_box( $text = '', $input_id = 'flagged-emails-search' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		if ( empty( $text ) ) {
			$text = __( 'Search flagged emails', 'kickbox-integration' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Preserve page parameter
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		if ( isset( $_GET['page'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
			echo '<input type="hidden" name="page" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) . '" />';
		}

		// Preserve other parameters
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		foreach ( $_GET as $key => $value ) {
			if ( ! in_array( $key, array( 's', 'paged', 'orderby', 'order', 'page' ) ) ) {
				echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>
				:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php echo esc_attr( $search ); ?>"
						 placeholder="<?php esc_attr_e( 'Search emails...', 'kickbox-integration' ); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
			<?php if ( $search ): ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kickbox-flagged-emails' ) ); ?>" class="button">
					<?php esc_html_e( 'Clear Search', 'kickbox-integration' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Add extra controls to the table
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			// Get current filter values
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
			$admin_decision_filter = isset( $_GET['admin_decision'] ) ? sanitize_text_field( wp_unslash( $_GET['admin_decision'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
			$origin_filter = isset( $_GET['origin'] ) ? sanitize_text_field( wp_unslash( $_GET['origin'] ) ) : '';

			// Get origin values for dropdown
			$origin_values = $this->get_origin_values();
			?>
			<div class="alignleft actions">
				<label for="admin_decision_filter"
							 class="screen-reader-text"><?php esc_html_e( 'Filter by Admin Decision', 'kickbox-integration' ); ?></label>
				<select name="admin_decision" id="admin_decision_filter">
					<option value=""><?php esc_html_e( 'All Admin Decisions', 'kickbox-integration' ); ?></option>
					<option
						value="pending" <?php selected( $admin_decision_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'kickbox-integration' ); ?></option>
					<option
						value="allow" <?php selected( $admin_decision_filter, 'allow' ); ?>><?php esc_html_e( 'Allow', 'kickbox-integration' ); ?></option>
					<option
						value="block" <?php selected( $admin_decision_filter, 'block' ); ?>><?php esc_html_e( 'Block', 'kickbox-integration' ); ?></option>
				</select>

				<label for="origin_filter"
							 class="screen-reader-text"><?php esc_html_e( 'Filter by Origin', 'kickbox-integration' ); ?></label>
				<select name="origin" id="origin_filter">
					<option value=""><?php esc_html_e( 'All Origins', 'kickbox-integration' ); ?></option>
					<?php foreach ( $origin_values as $origin ) : ?>
						<option
							value="<?php echo esc_attr( $origin ); ?>" <?php selected( $origin_filter, $origin ); ?>><?php echo esc_html( $origin ); ?></option>
					<?php endforeach; ?>
				</select>

				<?php submit_button( __( 'Filter', 'kickbox-integration' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Display the table wrapped in a form
	 */
	public function display() {
		?>
		<!-- phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET form for search functionality -->
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php?page=kickbox-flagged-emails' ) ); ?>">
			<?php
			$this->search_box();

			// Add nonce for bulk actions
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );

			// Preserve GET parameters for after redirect
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
			foreach ( $_GET as $key => $value ) {
				if ( ! in_array( $key, array( 'action', 'action2', 'flagged_email', '_wpnonce' ) ) ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
				}
			}

			// Display views (filters) before the table
			$this->views();

			parent::display();
			?>
		</form>
		<?php
	}

	/**
	 * Add admin page under WooCommerce menu
	 */
	public function add_admin_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Flagged Emails', 'kickbox-integration' ),
			__( 'Flagged Emails', 'kickbox-integration' ),
			'manage_woocommerce',
			'kickbox-flagged-emails',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Add screen options
	 */
	public function set_items_per_page_option() {
		// Add per page option
		add_screen_option( 'per_page', array(
			'label'   => __( 'Flagged Emails per page', 'kickbox-integration' ),
			'default' => 20,
			'option'  => 'flagged_emails_per_page'
		) );
	}

	/**
	 * Saves the items-per-page setting.
	 *
	 * @param mixed $default The default value.
	 * @param string $option The option being configured.
	 * @param int $value The submitted option value.
	 *
	 * @return mixed
	 */
	public function set_items_per_page( $default, $option, $value ) {
		return 'flagged_emails_per_page' === $option ? absint( $value ) : $default;
	}


	/**
	 * Display admin notices for review actions
	 */
	public function review_action_notices() {
		$notice = get_transient( 'kickbox_review_notice' );
		if ( $notice ) {
			$class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
			delete_transient( 'kickbox_review_notice' );
		}
	}

	/**
	 * Display bulk action notices
	 */
	public function bulk_action_notices() {
		// Check if we're on the correct page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'kickbox-flagged-emails' ) {
			return;
		}

		// Check for general bulk notice (like "no items selected")
		$bulk_notice = get_transient( 'kickbox_bulk_notice' );
		if ( $bulk_notice ) {
			$class = $bulk_notice['type'] === 'success' ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $bulk_notice['message'] ) . '</p></div>';

			// Clear the transient
			delete_transient( 'kickbox_bulk_notice' );
		}

		// Check for bulk action notice transient
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
		if ( isset( $_GET['bulk_notice'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for display purposes
			$transient_key = sanitize_text_field( wp_unslash( $_GET['bulk_notice'] ) );
			$bulk_results  = get_transient( $transient_key );

			if ( $bulk_results && is_array( $bulk_results ) ) {
				$action          = $bulk_results['action'];
				$success_count   = $bulk_results['success_count'];
				$failed_items    = $bulk_results['failed_items'];
				$total_processed = $bulk_results['total_processed'];

				// Display success notice
				if ( $success_count > 0 ) {
					$success_message = sprintf(
					// translators: %1$d is the number of emails, %2$s is the action (allowed/blocked)
						_n(
							'%1$d email has been %2$s.',
							'%1$d emails have been %2$s.',
							$success_count,
							'kickbox-integration'
						),
						$success_count,
						$action . 'ed'
					);

					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success_message ) . '</p></div>';
				}

				// Display failure notice if there are failed items
				if ( ! empty( $failed_items ) ) {
					$failed_count   = count( $failed_items );
					$failed_message = sprintf(
					// translators: %d is the number of emails that failed to be processed
						_n(
							'%d email failed to be processed:',
							'%d emails failed to be processed:',
							$failed_count,
							'kickbox-integration'
						),
						$failed_count
					);

					echo '<div class="notice notice-error is-dismissible">';
					echo '<p><strong>' . esc_html( $failed_message ) . '</strong></p>';
					echo '<ul>';
					foreach ( $failed_items as $failed_item ) {
						echo '<li>' . esc_html( $failed_item['email'] ) . '</li>';
					}
					echo '</ul>';
					echo '</div>';
				}

				// Clean up transient and URL parameter
				delete_transient( $transient_key );

				// Clean up URL parameter
				if ( isset( $_SERVER['REQUEST_URI'] ) ) {
					$clean_url = remove_query_arg( 'bulk_notice' );
					if ( $clean_url !== $_SERVER['REQUEST_URI'] ) {
						echo '<script>history.replaceState({}, "", "' . esc_url( $clean_url ) . '");</script>';
					}
				}
			}
		}
	}


	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		// Prepare items using table instance
		$this->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<img
					src="<?php echo esc_url( KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/images/kickbox-logo-icon-255x255.svg' ); ?>"
					alt="Kickbox Logo"
					class="kickbox-header-admin-img"
					style="width: 32px; height: 32px; vertical-align: bottom" />
				<?php esc_html_e( 'Kickbox - Flagged Emails', 'kickbox-integration' ); ?>
			</h1>
			<hr class="wp-header-end">
			<?php $this->display(); ?>
		</div>
		<?php
	}
}
