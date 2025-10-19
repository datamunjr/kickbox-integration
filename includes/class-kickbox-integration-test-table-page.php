<?php
/**
 * Test Table Page for Flagged Emails
 *
 * @package Kickbox_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Kickbox_Integration_Test_Table_Page {

    /**
     * @var Kickbox_Integration_Flagged_Emails_Table
     */
    private $flagged_emails_table;

    public function __construct() {
        // Add menu item under WooCommerce
        add_action('admin_menu', array($this, 'add_test_table_page'), 65);
        add_action('load-woocommerce_page_kickbox-test-table', array($this, 'add_screen_options'));
        add_action('admin_init', array($this, 'handle_bulk_actions'));
        add_action('admin_notices', array($this, 'bulk_action_notices'));

        // Add filter for screen option saving
        add_filter('set_screen_option_flagged_emails_per_page', array($this, 'set_items_per_page'), 10, 3);
    }

    /**
     * Get the flagged emails table instance
     *
     * @return Kickbox_Integration_Flagged_Emails_Table
     */
    private function get_flagged_emails_table() {
        if (!$this->flagged_emails_table) {
            $this->flagged_emails_table = new Kickbox_Integration_Flagged_Emails_Table();
        }
        return $this->flagged_emails_table;
    }

    /**
     * Saves the items-per-page setting.
     *
     * @param mixed  $default The default value.
     * @param string $option  The option being configured.
     * @param int    $value   The submitted option value.
     *
     * @return mixed
     */
    public function set_items_per_page($default, $option, $value) {
        return 'flagged_emails_per_page' === $option ? absint($value) : $default;
    }

    /**
     * Display bulk action notices
     */
    public function bulk_action_notices() {
        // Check if we're on the correct page
        if (!isset($_GET['page']) || $_GET['page'] !== 'kickbox-test-table') {
            return;
        }

        // Check for bulk action notice transient
        if (isset($_GET['bulk_notice'])) {
            $transient_key = sanitize_text_field($_GET['bulk_notice']);
            $bulk_results = get_transient($transient_key);

            if ($bulk_results && is_array($bulk_results)) {
                $action = $bulk_results['action'];
                $success_count = $bulk_results['success_count'];
                $failed_items = $bulk_results['failed_items'];
                $total_processed = $bulk_results['total_processed'];

                // Display success notice
                if ($success_count > 0) {
                    $success_message = sprintf(
                        _n(
                            '%d email has been %s.',
                            '%d emails have been %s.',
                            $success_count,
                            'kickbox-integration'
                        ),
                        $success_count,
                        $action . 'ed'
                    );

                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
                }

                // Display failure notice if there are failed items
                if (!empty($failed_items)) {
                    $failed_count = count($failed_items);
                    $failed_message = sprintf(
                        _n(
                            '%d email failed to be processed:',
                            '%d emails failed to be processed:',
                            $failed_count,
                            'kickbox-integration'
                        ),
                        $failed_count
                    );

                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p><strong>' . esc_html($failed_message) . '</strong></p>';
                    echo '<ul>';
                    foreach ($failed_items as $failed_item) {
                        echo '<li>' . esc_html($failed_item['email']) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }

                // Clean up transient and URL parameter
                delete_transient($transient_key);

                // Clean up URL parameter
                if (isset($_SERVER['REQUEST_URI'])) {
                    $clean_url = remove_query_arg('bulk_notice');
                    if ($clean_url !== $_SERVER['REQUEST_URI']) {
                        echo '<script>history.replaceState({}, "", "' . esc_url($clean_url) . '");</script>';
                    }
                }
            }
        }
    }

    /**
     * Add Test Table page under WooCommerce menu
     */
    public function add_test_table_page() {
        add_submenu_page(
            'woocommerce',
            __('Flagged Emails Table', 'kickbox-integration'),
            __('Flagged Emails Table', 'kickbox-integration'),
            'manage_woocommerce',
            'kickbox-test-table',
            array($this, 'render_test_table_page')
        );
    }

    /**
     * Add screen options
     */
    public function add_screen_options() {
        $screen = get_current_screen();

        if (!$screen) {
            return;
        }

        // Add per page option
        add_screen_option('per_page', array(
            'label' => __('Flagged Emails per page', 'kickbox-integration'),
            'default' => 20,
            'option' => 'flagged_emails_per_page'
        ));

        // Add columns option
        add_screen_option('columns', array(
            'default' => 7,
            'max' => 7
        ));

        // Add column visibility filter
        add_filter('manage_' . $screen->id . '_columns', array($this, 'manage_columns_with_visibility'));
    }

    /**
     * Manage columns with visibility support
     *
     * @param array $columns
     * @return array
     */
    public function manage_columns_with_visibility($columns) {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'woocommerce_page_kickbox-test-table') {
            return $columns;
        }

				// Get our custom columns
				return array(
					'cb'           => '<input type="checkbox" />',
					'email'        => __('Email', 'kickbox-integration'),
					'kickbox_result' => __('Kickbox Result', 'kickbox-integration'),
					'verification_action' => __('Action at Verification', 'kickbox-integration'),
					'admin_decision' => __('Decision', 'kickbox-integration'),
					'origin'       => __('Origin', 'kickbox-integration'),
					'order_id'     => __('Order ID', 'kickbox-integration'),
					'flagged_date' => __('Flagged Date', 'kickbox-integration'),
				);
		}

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions() {
        // Check if we're on the correct page
        if (!isset($_GET['page']) || $_GET['page'] !== 'kickbox-test-table') {
            return;
        }

        // Process bulk actions using table instance
        $this->get_flagged_emails_table()->process_bulk_action();
    }

    /**
     * Render test table page
     */
    public function render_test_table_page() {
        // Get the table instance
        $table = $this->get_flagged_emails_table();

        // Set the screen for the table
        $table->screen = get_current_screen();

        // Prepare items using table instance
        $table->prepare_items();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <img src="<?php echo esc_url(KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/images/kickbox-logo-icon-255x255.svg'); ?>"
                     alt="Kickbox Logo"
                     class="kickbox-header-admin-img"
                     style="width: 32px; height: 32px; vertical-align: bottom"/>
                <?php esc_html_e('Kickbox - Flagged Emails Table', 'kickbox-integration'); ?>
            </h1>
            <hr class="wp-header-end">

            <?php $table->display(); ?>
        </div>
        <?php
    }
}
