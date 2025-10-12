<?php
/**
 * Kickbox Integration Flagged Emails Page
 *
 * Standalone page for reviewing and managing flagged emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kickbox_Integration_Flagged_Emails_Page {

	public function __construct() {
		// Add menu item under WooCommerce, after Orders
		add_action( 'admin_menu', array( $this, 'add_flagged_emails_page' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_flagged_emails_scripts' ) );
	}

	/**
	 * Add Flagged Emails page under WooCommerce menu
	 */
	public function add_flagged_emails_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Flagged Emails', 'kickbox-integration' ),
			__( 'Flagged Emails', 'kickbox-integration' ),
			'manage_woocommerce',
			'kickbox-flagged-emails',
			array( $this, 'render_flagged_emails_page' )
		);
	}

	/**
	 * Enqueue scripts for flagged emails page
	 */
	public function enqueue_flagged_emails_scripts( $hook ) {
		// Check if we're on the flagged emails page
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		
		if ( $page !== 'kickbox-flagged-emails' ) {
			return;
		}

		// Enqueue React and admin scripts
		wp_enqueue_script( 'kickbox-integration-admin', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/js/admin.js', array(
			'jquery',
			'react',
			'react-dom'
		), KICKBOX_INTEGRATION_VERSION, true );

		wp_enqueue_style( 'kickbox-integration-admin', KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/css/admin.css', array( 'woocommerce_admin_styles' ), KICKBOX_INTEGRATION_VERSION );

		wp_localize_script( 'kickbox-integration-admin', 'kickbox_integration_admin', array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'plugin_url' => KICKBOX_INTEGRATION_PLUGIN_URL,
			'nonce'      => wp_create_nonce( 'kickbox_integration_admin' ),
		) );
	}

	/**
	 * Render flagged emails page
	 */
	public function render_flagged_emails_page() {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<img src="<?php echo esc_url( KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/images/kickbox-logo-icon-255x255.svg' ); ?>"
				     alt="Kickbox Logo"
				     class="kickbox-header-admin-img"
				     style="width: 32px; height: 32px; vertical-align: middle; margin-right: 8px;" />
				<?php esc_html_e( 'Flagged Emails', 'kickbox-integration' ); ?>
			</h1>
			<hr class="wp-header-end">
			
			<div id="kickbox-flagged-emails-page-container">
				<!-- React FlaggedEmails component will be mounted here -->
			</div>
		</div>
		<?php
	}
}
