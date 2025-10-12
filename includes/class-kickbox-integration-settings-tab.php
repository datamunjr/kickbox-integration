<?php
/**
 * Kickbox_Integration_Settings_Tab Class
 *
 * Integrates Kickbox settings into WooCommerce Settings as a tab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure WC_Settings_Page is available
if ( ! class_exists( 'WC_Settings_Page', false ) ) {
	return;
}

/**
 * Settings tab for WooCommerce Settings page
 */
class Kickbox_Integration_Settings_Tab extends WC_Settings_Page {

	/**
	 * Admin instance for React components
	 *
	 * @var Kickbox_Integration_Admin
	 */
	private $admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'kickbox';
		$this->label = __( 'Kickbox', 'kickbox-integration' );

		parent::__construct();
		
		// Add custom field type handlers
		add_action( 'woocommerce_admin_field_kickbox_api_key', array( $this, 'render_api_key_field' ) );
		add_action( 'woocommerce_admin_field_info', array( $this, 'render_info_field' ) );
		add_action( 'woocommerce_admin_field_kickbox_react_section', array( $this, 'render_react_section' ) );
		add_action( 'woocommerce_admin_field_title_with_icon', array( $this, 'render_title_with_icon' ) );
	}

	/**
	 * Get sections for the settings tab
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			''            => __( 'Kickbox API Settings', 'kickbox-integration' ),
			'actions'     => __( 'Verification Actions', 'kickbox-integration' ),
			'allowlist'   => __( 'Allow List', 'kickbox-integration' ),
			'stats'       => __( 'Statistics', 'kickbox-integration' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get settings array for the current section
	 *
	 * @return array
	 */
	public function get_settings() {
		global $current_section;

		switch ( $current_section ) {
			case 'actions':
				$settings = $this->get_verification_actions_settings();
				break;
			case 'allowlist':
				$settings = $this->get_allowlist_settings();
				break;
			case 'stats':
				$settings = $this->get_statistics_settings();
				break;
			default:
				$settings = $this->get_api_settings();
				break;
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Get API Settings section settings
	 *
	 * @return array
	 */
	private function get_api_settings() {
		$verification = new Kickbox_Integration_Verification();
		
		$settings = array(
			array(
				'title' => __( 'API Settings', 'kickbox-integration' ),
				'type'  => 'title_with_icon',
				'desc'  => __( 'Configure your Kickbox API key and connection settings.', 'kickbox-integration' ),
				'id'    => 'kickbox_api_settings',
			),

			array(
				'title'             => __( 'API Key', 'kickbox-integration' ),
				'desc'              => __( 'Enter your Kickbox API key. You can find this in your Kickbox dashboard at kickbox.com', 'kickbox-integration' ),
				'id'                => 'kickbox_integration_api_key',
				'type'              => 'kickbox_api_key', // Custom field type
				'default'           => '',
				'autoload'          => true,
				'custom_attributes' => array(),
			),

			array(
				'title'    => __( 'Current Balance', 'kickbox-integration' ),
				'desc'     => $verification->get_balance_status_message(),
				'id'       => 'kickbox_balance_info',
				'type'     => 'info', // Read-only info field
			),

			array(
				'title'    => __( 'Enable Checkout Verification', 'kickbox-integration' ),
				'desc'     => __( 'Verify customer emails during checkout', 'kickbox-integration' ),
				'id'       => 'kickbox_integration_enable_checkout_verification',
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => true,
			),

			array(
				'title'    => __( 'Enable Registration Verification', 'kickbox-integration' ),
				'desc'     => __( 'Verify customer emails during user registration', 'kickbox-integration' ),
				'id'       => 'kickbox_integration_enable_registration_verification',
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => true,
			),

			array(
				'type' => 'sectionend',
				'id'   => 'kickbox_api_settings',
			),
		);

		return $settings;
	}

	/**
	 * Get Verification Actions section settings
	 *
	 * @return array
	 */
	private function get_verification_actions_settings() {
		$settings = array(
			array(
				'title' => __( 'Verification Actions', 'kickbox-integration' ),
				'type'  => 'title',
				'desc'  => sprintf(
					/* translators: %s: URL to Kickbox documentation */
					__( 'Configure what action to take for each verification result type. For detailed information about each verification result type, see the %s.', 'kickbox-integration' ),
					'<a href="https://docs.kickbox.com/docs/terminology" target="_blank" rel="noopener noreferrer">' . __( 'Kickbox Terminology Documentation', 'kickbox-integration' ) . '</a>'
				),
				'id'    => 'kickbox_verification_actions',
			),

			array(
				'title'    => __( 'Deliverable Action', 'kickbox-integration' ),
				'desc'     => __( 'Action to take when email is confirmed deliverable', 'kickbox-integration' ),
				'desc_tip' => __( 'The recipient\'s mail server confirmed the recipient exists. Kickbox has performed additional analysis and determined this address is safe to send to within our 95% Delivery Guarantee.', 'kickbox-integration' ),
				'id'       => 'kickbox_integration_deliverable_action',
				'type'     => 'select',
				'options'  => array(
					'allow'  => __( 'Allow', 'kickbox-integration' ),
					'review' => __( 'Allow but flag for review', 'kickbox-integration' ),
					'block'  => __( 'Block', 'kickbox-integration' ),
				),
				'default'  => 'allow',
				'autoload' => true,
			),

			array(
				'title'    => __( 'Undeliverable Action', 'kickbox-integration' ),
				'desc'     => __( 'Action to take when email is confirmed undeliverable', 'kickbox-integration' ),
				'desc_tip' => __( 'The email address does not exist or is syntactically incorrect (and thus does not exist).', 'kickbox-integration' ),
				'id'       => 'kickbox_integration_undeliverable_action',
				'type'     => 'select',
				'options'  => array(
					'allow'  => __( 'Allow', 'kickbox-integration' ),
					'review' => __( 'Allow but flag for review', 'kickbox-integration' ),
					'block'  => __( 'Block', 'kickbox-integration' ),
				),
				'default'  => 'allow',
				'autoload' => true,
			),

			array(
				'title'    => __( 'Risky Action', 'kickbox-integration' ),
				'desc'     => __( 'Action to take when email is identified as risky', 'kickbox-integration' ),
				'desc_tip' => __( 'The email address has quality issues and may result in a bounce or low engagement. Use caution when sending to risky addresses. Accept All, Disposable, and Role addresses are classified as Risky.', 'kickbox-integration' ),
				'id'       => 'kickbox_integration_risky_action',
				'type'     => 'select',
				'options'  => array(
					'allow'  => __( 'Allow', 'kickbox-integration' ),
					'review' => __( 'Allow but flag for review', 'kickbox-integration' ),
					'block'  => __( 'Block', 'kickbox-integration' ),
				),
				'default'  => 'allow',
				'autoload' => true,
			),

			array(
				'title'    => __( 'Unknown Action', 'kickbox-integration' ),
				'desc'     => __( 'Action to take when email verification result is unknown', 'kickbox-integration' ),
				'desc_tip' => __( 'Kickbox was unable to get a response from the recipient\'s mail server. This often happens if the destination mail server is too slow or temporarily unavailable. Unknown addresses don\'t count against your verification balance.', 'kickbox-integration' ),
				'id'       => 'kickbox_integration_unknown_action',
				'type'     => 'select',
				'options'  => array(
					'allow'  => __( 'Allow', 'kickbox-integration' ),
					'review' => __( 'Allow but flag for review', 'kickbox-integration' ),
					'block'  => __( 'Block', 'kickbox-integration' ),
				),
				'default'  => 'allow',
				'autoload' => true,
			),

			array(
				'type' => 'sectionend',
				'id'   => 'kickbox_verification_actions',
			),
		);

		return $settings;
	}

	/**
	 * Get Allow List section settings
	 *
	 * @return array
	 */
	private function get_allowlist_settings() {
		$settings = array(
			array(
				'title' => __( 'Email Allow List', 'kickbox-integration' ),
				'type'  => 'title',
				'desc'  => __( 'Manage emails that should skip Kickbox verification.', 'kickbox-integration' ),
				'id'    => 'kickbox_allowlist',
			),

			array(
				'type'    => 'kickbox_react_section',
				'id'      => 'kickbox_allowlist_component',
				'section' => 'allowlist',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'kickbox_allowlist',
			),
		);

		return $settings;
	}

	/**
	 * Get Flagged Emails section settings
	 *
	 * @return array
	 */
	private function get_flagged_emails_settings() {
		$settings = array(
			array(
				'title' => __( 'Flagged Emails', 'kickbox-integration' ),
				'type'  => 'title',
				'desc'  => __( 'Review and manage flagged email addresses.', 'kickbox-integration' ),
				'id'    => 'kickbox_flagged_emails',
			),

			array(
				'type'    => 'kickbox_react_section',
				'id'      => 'kickbox_flagged_component',
				'section' => 'flagged',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'kickbox_flagged_emails',
			),
		);

		return $settings;
	}

	/**
	 * Get Statistics section settings
	 *
	 * @return array
	 */
	private function get_statistics_settings() {
		$settings = array(
			array(
				'title' => __( 'Verification Statistics', 'kickbox-integration' ),
				'type'  => 'title',
				'desc'  => __( 'View email verification statistics and analytics.', 'kickbox-integration' ),
				'id'    => 'kickbox_statistics',
			),

			array(
				'type'    => 'kickbox_react_section',
				'id'      => 'kickbox_stats_component',
				'section' => 'stats',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'kickbox_statistics',
			),
		);

		return $settings;
	}

	/**
	 * Render custom API key field with show/hide functionality
	 *
	 * @param array $value Field settings
	 */
	public function render_api_key_field( $value ) {
		$admin = new Kickbox_Integration_Admin();
		$option_value = get_option( $value['id'], $value['default'] ?? '' );
		$masked_value = $admin->mask_api_key( $option_value );
		$has_api_key = ! empty( $option_value );
		
		$description  = WC_Admin_Settings::get_field_description( $value );
		$tooltip_html = $description['tooltip_html'] ?? '';
		$desc_html    = $description['description'] ?? '';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>">
					<?php echo esc_html( $value['title'] ); ?>
					<?php echo $tooltip_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
			</th>
			<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
				<div 
					id="kickbox-react-api-key-container"
					data-field-id="<?php echo esc_attr( $value['id'] ); ?>"
					data-initial-value="<?php echo esc_attr( $option_value ); ?>"
					data-masked-value="<?php echo esc_attr( $masked_value ); ?>"
					data-has-saved-key="<?php echo $has_api_key ? 'true' : 'false'; ?>"
				>
					<!-- React component will be mounted here -->
				</div>
				<?php echo $desc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render title with icon field
	 *
	 * @param array $value Field settings
	 */
	public function render_title_with_icon( $value ) {
		?>
		<h2>
			<img src="<?php echo esc_url( KICKBOX_INTEGRATION_PLUGIN_URL . 'assets/images/kickbox-logo-icon-255x255.svg' ); ?>"
			     alt="Kickbox Logo"
			     style="width: 24px; height: 24px; vertical-align: middle; margin-right: 8px;" />
			<?php echo esc_html( $value['title'] ); ?>
		</h2>
		<?php if ( ! empty( $value['desc'] ) ) : ?>
			<p><?php echo esc_html( $value['desc'] ); ?></p>
		<?php endif; ?>
		<table class="form-table">
		<?php
	}

	/**
	 * Render info field (read-only display)
	 *
	 * @param array $value Field settings
	 */
	public function render_info_field( $value ) {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>">
					<?php echo esc_html( $value['title'] ); ?>
				</label>
			</th>
			<td class="forminp forminp-text">
				<p class="description" style="margin: 0;">
					<?php echo wp_kses( $value['desc'], array( 'strong' => array() ) ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render custom React section
	 *
	 * @param array $value Field settings
	 */
	public function render_react_section( $value ) {
		$section = $value['section'] ?? '';
		
		?>
		<tr valign="top">
			<td colspan="2" style="padding: 0;">
				<div id="kickbox-react-<?php echo esc_attr( $section ); ?>-container" class="kickbox-react-section">
					<!-- React component will be mounted here -->
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output the settings
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings();
		
		// Manually render custom field types that WooCommerce doesn't understand
		foreach ( $settings as $setting ) {
			$type = $setting['type'] ?? '';
			
			// Handle custom field types directly
			if ( $type === 'title_with_icon' ) {
				$this->render_title_with_icon( $setting );
			} elseif ( $type === 'info' ) {
				$this->render_info_field( $setting );
			} elseif ( $type === 'kickbox_react_section' ) {
				$this->render_react_section( $setting );
			} else {
				// Let WooCommerce handle standard fields
				WC_Admin_Settings::output_fields( array( $setting ) );
			}
		}
	}

	/**
	 * Save settings
	 */
	public function save() {
		global $current_section;

		// Don't save React sections - they handle their own saving via AJAX
		if ( in_array( $current_section, array( 'allowlist', 'flagged', 'stats' ), true ) ) {
			return;
		}

		$settings = $this->get_settings();
		WC_Admin_Settings::save_fields( $settings );
	}
}

return new Kickbox_Integration_Settings_Tab();
