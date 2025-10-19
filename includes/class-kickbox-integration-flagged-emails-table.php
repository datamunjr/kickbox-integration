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
 * Extends WP_List_Table to display flagged emails with sorting and pagination.
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
			wp_die( __( 'WP_List_Table class not found. This feature requires WordPress admin.', 'kickbox-integration' ) );
		}

		global $wpdb;
		$this->table_name = $wpdb->prefix . 'kickbox_integration_flagged_emails';

		parent::__construct( array(
			'singular' => 'flagged_email',
			'plural'   => 'flagged_emails',
			'ajax'     => false
		) );
	}

	/**
	 * Get table columns
	 *
	 * @return array
	 */
	public function get_columns() {

		$screen = get_current_screen();

		// Fallback columns if no screen available
		$columns = array(
			'cb'                  => '<input type="checkbox" />',
			'email'               => __( 'Email', 'kickbox-integration' ),
			'kickbox_result'      => __( 'Kickbox Result', 'kickbox-integration' ),
			'verification_action' => __( 'Action at Verification', 'kickbox-integration' ),
			'admin_decision'      => __( 'Decision', 'kickbox-integration' ),
			'origin'              => __( 'Origin', 'kickbox-integration' ),
			'order_id'            => __( 'Order ID', 'kickbox-integration' ),
			'flagged_date'        => __( 'Flagged Date', 'kickbox-integration' ),
		);

		// Apply the visibility filter
		// Retrieve hidden columns from user meta
		$hidden = get_user_meta( get_current_user_id(), 'manage' . $screen->id . 'columnshidden', true );
		if ( is_array( $hidden ) ) {
			foreach ( $hidden as $column_to_hide ) {
				// Don't allow hiding the checkbox column
				if ( $column_to_hide !== 'cb' ) {
					unset( $columns[ $column_to_hide ] );
				}
			}
		}

		return $columns;
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'email'          => array( 'email', false ),
			'admin_decision' => array( 'admin_decision', false ),
			'flagged_date'   => array( 'flagged_date', true ),
			'origin'         => array( 'origin', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'allow' => __( 'Allow', 'kickbox-integration' ),
			'block' => __( 'Block', 'kickbox-integration' ),
		);

		return $actions;
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
					$output .= '<div style="font-size: 12px; color: #666;">';
					$output .= sprintf( __( 'User ID: %s', 'kickbox-integration' ), $item['user_id'] );
					$output .= '</div>';
				}

				return $output;

			case 'kickbox_result':
				$result = $item['kickbox_result']['result'] ?? 'unknown';
				$reason = $item['kickbox_result']['reason'] ?? '';

				$output = '<span class="kickbox_integration-badge kickbox_integration-badge-' . esc_attr( $result ) . '">';
				$output .= esc_html( ucfirst( $result ) );
				$output .= '</span>';

				if ( $reason ) {
					$output .= '<div style="font-size: 12px; color: #666; margin-top: 4px;">';
					$output .= esc_html( $reason );
					$output .= '</div>';
				}

				return $output;

			case 'verification_action':
				$action      = $item['verification_action'];
				$action_text = $action === 'block' ? __( 'Blocked', 'kickbox-integration' ) : __( 'Flagged', 'kickbox-integration' );

				return '<span class="kickbox_integration-badge kickbox_integration-badge-' . esc_attr( $action ) . '">' . esc_html( $action_text ) . '</span>';

			case 'admin_decision':
				$decision = $item['admin_decision'];

				return '<span class="kickbox_integration-badge kickbox_integration-badge-' . esc_attr( $decision ) . '">' . esc_html( ucfirst( $decision ) ) . '</span>';

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
	 * Prepare items for display
	 */
	public function prepare_items() {
		global $wpdb;

		// Set up column headers
		$screen                = get_current_screen();
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get pagination parameters
		$per_page     = $this->get_items_per_page( 'flagged_emails_per_page' );
		$current_page = $this->get_pagenum();

		// Get search parameter
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

		// Get sorting parameters
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'flagged_date';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'DESC';

		// Validate orderby
		if ( ! in_array( $orderby, array( 'email', 'admin_decision', 'flagged_date', 'origin' ) ) ) {
			$orderby = 'flagged_date';
		}

		// Validate order
		if ( ! in_array( strtoupper( $order ), array( 'ASC', 'DESC' ) ) ) {
			$order = 'DESC';
		}

		// Build search conditions
		$search_conditions = '';
		$search_params     = array();

		if ( ! empty( $search ) ) {
			$search_conditions = " WHERE email LIKE %s";
			$search_params[]   = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// Get total items with search
		$total_items_query = "SELECT COUNT(*) FROM {$this->table_name}" . $search_conditions;
		if ( ! empty( $search_params ) ) {
			$total_items = $wpdb->get_var( $wpdb->prepare( $total_items_query, $search_params ) );
		} else {
			$total_items = $wpdb->get_var( $total_items_query );
		}

		// Get items with search
		$offset      = ( $current_page - 1 ) * $per_page;
		$items_query = "SELECT * FROM {$this->table_name}" . $search_conditions . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		if ( ! empty( $search_params ) ) {
			$search_params[] = $per_page;
			$search_params[] = $offset;
			$items           = $wpdb->get_results( $wpdb->prepare( $items_query, $search_params ), ARRAY_A );
		} else {
			$items = $wpdb->get_results( $wpdb->prepare( $items_query, $per_page, $offset ), ARRAY_A );
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
	public function process_bulk_action() {
		// Check if bulk action is being performed
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to perform this action.', 'kickbox-integration' ) );
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
			wp_die( __( 'Security check failed.', 'kickbox-integration' ) );
		}

		// Check if items are selected
		if ( ! isset( $_POST['flagged_email'] ) || ! is_array( $_POST['flagged_email'] ) ) {
			return;
		}

		$email_ids     = array_map( 'intval', $_POST['flagged_email'] );
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
		$redirect_url = add_query_arg( array(
			'bulk_notice' => $transient_key
		), remove_query_arg( array( 'action', 'action2', 'flagged_email', '_wpnonce' ) ) );

		wp_safe_redirect( $redirect_url );
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

		$email = $wpdb->get_var( $wpdb->prepare(
			"SELECT email FROM {$this->table_name} WHERE id = %d",
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
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		if ( empty( $text ) ) {
			$text = __( 'Search flagged emails', 'kickbox-integration' );
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>
				:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php echo esc_attr( $search ); ?>"
						 placeholder="<?php _e( 'Search emails...', 'kickbox-integration' ); ?>" />
			<input type="submit" id="search-submit" class="button" value="<?php echo esc_attr( $text ); ?>" />
			<?php if ( $search ): ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kickbox-test-table' ) ); ?>" class="button">
					<?php _e( 'Clear Search', 'kickbox-integration' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Display the table wrapped in a form
	 */
	public function display() {
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php?page=kickbox-test-table' ) ); ?>">
			<?php
			// Preserve page parameter
			if ( isset( $_GET['page'] ) ) {
				echo '<input type="hidden" name="page" value="' . esc_attr( $_GET['page'] ) . '" />';
			}

			// Preserve other parameters
			foreach ( $_GET as $key => $value ) {
				if ( ! in_array( $key, array( 's', 'paged', 'orderby', 'order', 'page' ) ) ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
				}
			}

			$this->search_box();
			?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=kickbox-test-table' ) ); ?>">
			<?php
			// Add nonce for bulk actions
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );

			// Preserve GET parameters for after redirect
			foreach ( $_GET as $key => $value ) {
				if ( ! in_array( $key, array( 'action', 'action2', 'flagged_email', '_wpnonce' ) ) ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
				}
			}

			parent::display();
			?>
		</form>
		<?php
	}
}
