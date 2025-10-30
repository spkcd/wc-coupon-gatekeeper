<?php
/**
 * Admin settings screen for WC Coupon Gatekeeper.
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper\Admin;

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Database;

/**
 * Class Settings_Screen
 *
 * Handles the admin settings interface under WooCommerce > Settings > Coupon Gatekeeper.
 */
class Settings_Screen {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_coupon_gatekeeper', array( $this, 'output_settings' ) );
		add_action( 'woocommerce_update_options_coupon_gatekeeper', array( $this, 'save_settings' ) );
		add_action( 'woocommerce_admin_field_wcgk_purge_logs_button', array( $this, 'output_purge_button' ) );
		add_action( 'wp_ajax_wcgk_purge_old_logs', array( $this, 'ajax_purge_old_logs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Hook to provide values from our settings array to WooCommerce fields.
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'get_option_value' ), 10, 3 );
	}

	/**
	 * Add settings tab to WooCommerce settings.
	 *
	 * @param array $tabs Existing settings tabs.
	 * @return array Modified tabs array.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['coupon_gatekeeper'] = __( 'Coupon Gatekeeper', 'wc-coupon-gatekeeper' );
		return $tabs;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || 'coupon_gatekeeper' !== $_GET['tab'] ) {
			return;
		}

		wp_enqueue_script(
			'wc-coupon-gatekeeper-admin',
			WC_COUPON_GATEKEEPER_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WC_COUPON_GATEKEEPER_VERSION,
			true
		);

		wp_localize_script(
			'wc-coupon-gatekeeper-admin',
			'wcgkAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcgk_purge_logs' ),
				'i18n'     => array(
					'confirm_purge' => __( 'Are you sure you want to purge old logs? This action cannot be undone.', 'wc-coupon-gatekeeper' ),
					'purging'       => __( 'Purging logs...', 'wc-coupon-gatekeeper' ),
					'purge_success' => __( 'Old logs have been purged successfully.', 'wc-coupon-gatekeeper' ),
					'purge_error'   => __( 'Failed to purge logs. Please try again.', 'wc-coupon-gatekeeper' ),
				),
			)
		);
	}

	/**
	 * Output settings fields.
	 *
	 * @return void
	 */
	public function output_settings() {
		// Display validation errors if any.
		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
		}

		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		// Verify nonce (WooCommerce handles this).
		check_admin_referer( 'woocommerce-settings' );

		// Verify user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage WooCommerce settings.', 'wc-coupon-gatekeeper' ) );
		}

		// Process and validate custom fields.
		$new_settings = $this->sanitize_and_validate_settings();

		// If validation errors, don't save.
		if ( ! empty( $this->errors ) ) {
			add_action(
				'admin_notices',
				function () {
					foreach ( $this->errors as $error ) {
						echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
					}
				}
			);
			return;
		}

		// Update settings.
		$this->settings->update( $new_settings );

		// Clear cache.
		$this->settings->clear_cache();
	}

	/**
	 * Sanitize and validate all settings from POST data.
	 *
	 * @return array Sanitized settings array.
	 */
	private function sanitize_and_validate_settings() {
		$new_settings = array();

		// Feature Toggles.
		$new_settings['enable_day_restriction'] = isset( $_POST['wcgk_enable_day_restriction'] );
		$new_settings['enable_monthly_limit']   = isset( $_POST['wcgk_enable_monthly_limit'] );

		// Coupon Targeting: Restricted Coupons.
		$restricted_coupons_raw = isset( $_POST['wcgk_restricted_coupons'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcgk_restricted_coupons'] ) ) : '';
		$new_settings['restricted_coupons'] = $this->parse_coupon_list( $restricted_coupons_raw );

		// Coupon Targeting: Apply to All.
		$new_settings['apply_to_all_coupons'] = isset( $_POST['wcgk_apply_to_all_coupons'] );

		// Allowed Days: Parse multiselect.
		$allowed_days_raw = isset( $_POST['wcgk_allowed_days'] ) ? array_map( 'absint', (array) $_POST['wcgk_allowed_days'] ) : array();
		$allowed_days     = array_filter(
			$allowed_days_raw,
			function ( $day ) {
				return $day >= 1 && $day <= 31;
			}
		);

		if ( empty( $allowed_days ) ) {
			$this->errors[] = __( 'You must select at least one allowed day.', 'wc-coupon-gatekeeper' );
			$allowed_days   = array( 27 );
		}

		$new_settings['allowed_days'] = array_values( $allowed_days );

		// Allowed Days: Use Last Valid Day.
		$new_settings['use_last_valid_day'] = isset( $_POST['wcgk_use_last_valid_day'] );

		// Monthly Limit: Default.
		$default_limit = isset( $_POST['wcgk_default_monthly_limit'] ) ? absint( $_POST['wcgk_default_monthly_limit'] ) : 1;
		if ( $default_limit < 1 ) {
			$this->errors[] = __( 'Default monthly limit must be at least 1.', 'wc-coupon-gatekeeper' );
			$default_limit  = 1;
		}
		$new_settings['default_monthly_limit'] = $default_limit;

		// Monthly Limit: Per-Coupon Overrides.
		$overrides_raw = isset( $_POST['wcgk_coupon_limit_overrides'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcgk_coupon_limit_overrides'] ) ) : '';
		$new_settings['coupon_limit_overrides'] = $this->parse_coupon_overrides( $overrides_raw );

		// Monthly Limit: Customer Identification.
		$customer_id = isset( $_POST['wcgk_customer_identification'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgk_customer_identification'] ) ) : 'user_id_priority';
		if ( ! in_array( $customer_id, array( 'user_id_priority', 'email_only' ), true ) ) {
			$customer_id = 'user_id_priority';
		}
		$new_settings['customer_identification'] = $customer_id;

		// Monthly Limit: Anonymize Email.
		$new_settings['anonymize_email'] = isset( $_POST['wcgk_anonymize_email'] );

		// Messages.
		$new_settings['error_not_allowed_day'] = isset( $_POST['wcgk_error_not_allowed_day'] )
			? sanitize_text_field( wp_unslash( $_POST['wcgk_error_not_allowed_day'] ) )
			: __( 'This coupon can only be used on the allowed day(s) each month.', 'wc-coupon-gatekeeper' );

		$new_settings['error_limit_reached'] = isset( $_POST['wcgk_error_limit_reached'] )
			? sanitize_text_field( wp_unslash( $_POST['wcgk_error_limit_reached'] ) )
			: __( "You've already used this coupon this month.", 'wc-coupon-gatekeeper' );

		$new_settings['enable_success_message'] = isset( $_POST['wcgk_enable_success_message'] );

		$new_settings['success_message'] = isset( $_POST['wcgk_success_message'] )
			? sanitize_text_field( wp_unslash( $_POST['wcgk_success_message'] ) )
			: __( 'Nice timing! This coupon is valid today.', 'wc-coupon-gatekeeper' );

		// Advanced: Count Usage Statuses.
		$count_statuses = isset( $_POST['wcgk_count_usage_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['wcgk_count_usage_statuses'] ) : array( 'processing', 'completed' );
		if ( empty( $count_statuses ) ) {
			$this->errors[]  = __( 'You must select at least one status to count usage.', 'wc-coupon-gatekeeper' );
			$count_statuses = array( 'processing', 'completed' );
		}
		$new_settings['count_usage_statuses'] = $count_statuses;

		// Advanced: Decrement Usage Statuses.
		$decrement_statuses = isset( $_POST['wcgk_decrement_usage_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['wcgk_decrement_usage_statuses'] ) : array();
		$new_settings['decrement_usage_statuses'] = $decrement_statuses;

		// Advanced: Admin Bypass.
		$new_settings['admin_bypass_edit_order'] = isset( $_POST['wcgk_admin_bypass_edit_order'] );

		// Advanced: Log Retention.
		$retention = isset( $_POST['wcgk_log_retention_months'] ) ? absint( $_POST['wcgk_log_retention_months'] ) : 18;
		if ( $retention < 1 ) {
			$this->errors[] = __( 'Log retention must be at least 1 month.', 'wc-coupon-gatekeeper' );
			$retention      = 18;
		}
		$new_settings['log_retention_months'] = $retention;

		// Other: Delete Data on Uninstall.
		$new_settings['delete_data_on_uninstall'] = isset( $_POST['wcgk_delete_data_on_uninstall'] );

		return $new_settings;
	}

	/**
	 * Parse comma or line-separated coupon list to array.
	 *
	 * @param string $input Raw input string.
	 * @return array Array of lowercase coupon codes.
	 */
	private function parse_coupon_list( $input ) {
		$input = str_replace( array( "\r\n", "\r" ), "\n", $input );
		$input = str_replace( ',', "\n", $input );
		$lines = explode( "\n", $input );
		$codes = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$codes[] = strtolower( $line );
			}
		}

		return array_unique( $codes );
	}

	/**
	 * Parse per-coupon limit overrides (code:limit format).
	 *
	 * @param string $input Raw input string.
	 * @return array Associative array [coupon_code => limit].
	 */
	private function parse_coupon_overrides( $input ) {
		$input     = str_replace( array( "\r\n", "\r" ), "\n", $input );
		$lines     = explode( "\n", $input );
		$overrides = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			// Parse code:limit format.
			if ( strpos( $line, ':' ) === false ) {
				continue; // Invalid format, skip.
			}

			list( $code, $limit ) = explode( ':', $line, 2 );
			$code  = strtolower( trim( $code ) );
			$limit = absint( trim( $limit ) );

			if ( '' !== $code && $limit >= 1 ) {
				$overrides[ $code ] = $limit;
			}
		}

		return $overrides;
	}

	/**
	 * Get settings array for WooCommerce settings API.
	 *
	 * @return array
	 */
	private function get_settings() {
		$current = $this->settings->get_all();

		$settings = array(
			// =====================================================================
			// Feature Toggles
			// =====================================================================
			array(
				'title' => __( 'Feature Toggles', 'wc-coupon-gatekeeper' ),
				'type'  => 'title',
				'desc'  => __( 'Enable or disable specific restriction features.', 'wc-coupon-gatekeeper' ),
				'id'    => 'wcgk_feature_toggles',
			),

			array(
				'title'   => __( 'Enable Day-of-Month Restriction', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'Restrict coupons to specific days of each month.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_enable_day_restriction',
				'type'    => 'checkbox',
				'default' => 'yes',
			),

			array(
				'title'   => __( 'Enable Per-Customer Monthly Limit', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'Enforce monthly usage limits per customer.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_enable_monthly_limit',
				'type'    => 'checkbox',
				'default' => 'yes',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wcgk_feature_toggles',
			),

			// =====================================================================
			// Coupon Targeting
			// =====================================================================
			array(
				'title' => __( 'Coupon Targeting', 'wc-coupon-gatekeeper' ),
				'type'  => 'title',
				'desc'  => __( 'Select which coupons should be restricted by this plugin.', 'wc-coupon-gatekeeper' ),
				'id'    => 'wcgk_coupon_targeting',
			),

			array(
				'title'             => __( 'Restricted Coupons', 'wc-coupon-gatekeeper' ),
				'desc'              => __( 'Enter coupon codes (comma or line-separated). Example: 27off, vip27, SUMMERSALE', 'wc-coupon-gatekeeper' ),
				'id'                => 'wcgk_restricted_coupons',
				'type'              => 'textarea',
				'css'               => 'min-height: 100px;',
				'default'           => '',
				'desc_tip'          => __( 'Coupon codes will be normalized to lowercase.', 'wc-coupon-gatekeeper' ),
				'custom_attributes' => array(
					'placeholder' => '27off, vip27',
				),
			),

			array(
				'title'   => __( 'Apply to ALL Coupons', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'When enabled, restrictions apply to every coupon (ignores the list above).', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_apply_to_all_coupons',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wcgk_coupon_targeting',
			),

			// =====================================================================
			// Allowed Days
			// =====================================================================
			array(
				'title' => __( 'Allowed Day(s)', 'wc-coupon-gatekeeper' ),
				'type'  => 'title',
				'desc'  => __( 'Configure which days of the month coupons can be used.', 'wc-coupon-gatekeeper' ),
				'id'    => 'wcgk_allowed_days_section',
			),

			array(
				'title'    => __( 'Allowed Day(s) of Month', 'wc-coupon-gatekeeper' ),
				'desc'     => __( 'Select which days coupons can be used (1-31).', 'wc-coupon-gatekeeper' ),
				'id'       => 'wcgk_allowed_days',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'options'  => $this->get_days_options(),
				'default'  => array( 27 ),
				'desc_tip' => true,
			),

			array(
				'title'   => __( 'If Day Missing → Use Last Valid Day of Month', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'If selected day doesn\'t exist (e.g., Feb 31), allow usage on the last day of that month.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_use_last_valid_day',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wcgk_allowed_days_section',
			),

			// =====================================================================
			// Monthly Limit
			// =====================================================================
			array(
				'title' => __( 'Monthly Limit', 'wc-coupon-gatekeeper' ),
				'type'  => 'title',
				'desc'  => __( 'Configure per-customer monthly usage limits and customer identification.', 'wc-coupon-gatekeeper' ),
				'id'    => 'wcgk_monthly_limit_section',
			),

			array(
				'title'             => __( 'Default Monthly Limit per Customer', 'wc-coupon-gatekeeper' ),
				'desc'              => __( 'Maximum number of times a customer can use each coupon per month.', 'wc-coupon-gatekeeper' ),
				'id'                => 'wcgk_default_monthly_limit',
				'type'              => 'number',
				'default'           => '1',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),

			array(
				'title'             => __( 'Per-Coupon Overrides', 'wc-coupon-gatekeeper' ),
				'desc'              => __( 'Override limit for specific coupons (one per line). Format: <code>coupon_code:limit</code><br>Example:<br><code>vip27:5</code><br><code>special:10</code>', 'wc-coupon-gatekeeper' ),
				'id'                => 'wcgk_coupon_limit_overrides',
				'type'              => 'textarea',
				'css'               => 'min-height: 80px;',
				'default'           => '',
				'custom_attributes' => array(
					'placeholder' => "vip27:5\nspecial:10",
				),
			),

			array(
				'title'   => __( 'Identify Customer By', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'How to track customers for usage limits.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_customer_identification',
				'type'    => 'radio',
				'options' => array(
					'user_id_priority' => __( 'Logged-in User ID (preferred), fallback to billing email for guests', 'wc-coupon-gatekeeper' ),
					'email_only'       => __( 'Always use billing email (even for logged-in users)', 'wc-coupon-gatekeeper' ),
				),
				'default' => 'user_id_priority',
			),

			array(
				'title'   => __( 'Anonymize Email in Logs', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'Store email addresses as salted, irreversible hashes for privacy.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_anonymize_email',
				'type'    => 'checkbox',
				'default' => 'yes',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wcgk_monthly_limit_section',
			),

			// =====================================================================
			// Messages
			// =====================================================================
			array(
				'title' => __( 'Error Messages', 'wc-coupon-gatekeeper' ),
				'type'  => 'title',
				'desc'  => __( 'Customize error messages shown to customers.', 'wc-coupon-gatekeeper' ),
				'id'    => 'wcgk_messages_section',
			),

			array(
				'title'             => __( 'Error: Not Allowed Day', 'wc-coupon-gatekeeper' ),
				'desc'              => __( 'Message shown when coupon is used on a non-allowed day.', 'wc-coupon-gatekeeper' ),
				'id'                => 'wcgk_error_not_allowed_day',
				'type'              => 'text',
				'default'           => __( 'This coupon can only be used on the allowed day(s) each month.', 'wc-coupon-gatekeeper' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'style' => 'width: 100%; max-width: 600px;',
				),
			),

			array(
				'title'             => __( 'Error: Monthly Limit Reached', 'wc-coupon-gatekeeper' ),
				'desc'              => __( 'Message shown when customer has exceeded monthly usage limit.', 'wc-coupon-gatekeeper' ),
				'id'                => 'wcgk_error_limit_reached',
				'type'              => 'text',
				'default'           => __( "You've already used this coupon this month.", 'wc-coupon-gatekeeper' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'style' => 'width: 100%; max-width: 600px;',
				),
			),

			array(
				'title'   => __( 'Show Success Message', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'Display a positive message when coupon is applied successfully on an allowed day.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_enable_success_message',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'             => __( 'Success Message', 'wc-coupon-gatekeeper' ),
				'desc'              => __( 'Message shown when coupon is successfully applied on an allowed day.', 'wc-coupon-gatekeeper' ),
				'id'                => 'wcgk_success_message',
				'type'              => 'text',
				'default'           => __( 'Nice timing! This coupon is valid today.', 'wc-coupon-gatekeeper' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'style' => 'width: 100%; max-width: 600px;',
				),
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wcgk_messages_section',
			),

			// =====================================================================
			// Advanced
			// =====================================================================
			array(
				'title' => __( 'Advanced Settings', 'wc-coupon-gatekeeper' ),
				'type'  => 'title',
				'desc'  => __( 'Advanced configuration options for usage tracking and admin features.', 'wc-coupon-gatekeeper' ),
				'id'    => 'wcgk_advanced_section',
			),

			array(
				'title'    => __( 'Count Usage On Status', 'wc-coupon-gatekeeper' ),
				'desc'     => __( 'Order statuses that should increment usage counter.', 'wc-coupon-gatekeeper' ),
				'id'       => 'wcgk_count_usage_statuses',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'options'  => $this->get_order_statuses(),
				'default'  => array( 'processing', 'completed' ),
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Decrement On Status', 'wc-coupon-gatekeeper' ),
				'desc'     => __( 'Order statuses that should decrement usage counter.', 'wc-coupon-gatekeeper' ),
				'id'       => 'wcgk_decrement_usage_statuses',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'options'  => $this->get_order_statuses(),
				'default'  => array( 'cancelled', 'refunded' ),
				'desc_tip' => true,
			),

			array(
				'title'   => __( 'Admin Bypass in Edit Order', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'Skip validation checks when admin manually adds coupons in WP Admin order edit screen.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_admin_bypass_edit_order',
				'type'    => 'checkbox',
				'default' => 'yes',
			),

			array(
				'title'             => __( 'Clear Logs Older Than N Months', 'wc-coupon-gatekeeper' ),
				'desc'              => __( 'Logs older than this many months can be purged.', 'wc-coupon-gatekeeper' ),
				'id'                => 'wcgk_log_retention_months',
				'type'              => 'number',
				'default'           => '18',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),

			array(
				'title' => __( 'Purge Old Logs', 'wc-coupon-gatekeeper' ),
				'type'  => 'wcgk_purge_logs_button',
				'id'    => 'wcgk_purge_logs_button',
			),

			array(
				'title'   => __( 'Delete Data on Uninstall', 'wc-coupon-gatekeeper' ),
				'desc'    => __( 'Remove all plugin data (settings and logs) when the plugin is uninstalled.', 'wc-coupon-gatekeeper' ),
				'id'      => 'wcgk_delete_data_on_uninstall',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wcgk_advanced_section',
			),
		);

		return apply_filters( 'wc_coupon_gatekeeper_settings', $settings );
	}

	/**
	 * Get days of month options for multiselect.
	 *
	 * @return array
	 */
	private function get_days_options() {
		$days = array();
		for ( $i = 1; $i <= 31; $i++ ) {
			$days[ $i ] = $i;
		}
		return $days;
	}

	/**
	 * Get WooCommerce order statuses.
	 *
	 * @return array
	 */
	private function get_order_statuses() {
		$statuses = wc_get_order_statuses();
		// Remove 'wc-' prefix from keys.
		$clean = array();
		foreach ( $statuses as $key => $label ) {
			$clean[ str_replace( 'wc-', '', $key ) ] = $label;
		}
		return $clean;
	}

	/**
	 * Output purge logs button.
	 *
	 * @param array $value Field configuration.
	 * @return void
	 */
	public function output_purge_button( $value ) {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<button type="button" id="wcgk-purge-logs-btn" class="button button-secondary">
					<?php esc_html_e( 'Purge Old Logs Now', 'wc-coupon-gatekeeper' ); ?>
				</button>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of months */
						esc_html__( 'This will permanently delete logs older than %d months.', 'wc-coupon-gatekeeper' ),
						absint( $this->settings->get_log_retention_months() )
					);
					?>
				</p>
				<div id="wcgk-purge-logs-result" style="margin-top: 10px;"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get option value from our settings array for WooCommerce fields.
	 *
	 * @param mixed  $value  Current value.
	 * @param array  $option Field configuration.
	 * @param mixed  $raw_value Raw value.
	 * @return mixed
	 */
	public function get_option_value( $value, $option, $raw_value ) {
		// Only handle our fields.
		if ( ! isset( $option['id'] ) || strpos( $option['id'], 'wcgk_' ) !== 0 ) {
			return $value;
		}

		// Map field ID to settings key.
		$field_id     = $option['id'];
		$settings_key = str_replace( 'wcgk_', '', $field_id );
		$current      = $this->settings->get_all();

		// Special handling for specific fields.
		switch ( $settings_key ) {
			case 'restricted_coupons':
				// Convert array to textarea format.
				if ( isset( $current['restricted_coupons'] ) && is_array( $current['restricted_coupons'] ) ) {
					return implode( "\n", $current['restricted_coupons'] );
				}
				return '';

			case 'coupon_limit_overrides':
				// Convert array to textarea format (code:limit).
				if ( isset( $current['coupon_limit_overrides'] ) && is_array( $current['coupon_limit_overrides'] ) ) {
					$lines = array();
					foreach ( $current['coupon_limit_overrides'] as $code => $limit ) {
						$lines[] = $code . ':' . $limit;
					}
					return implode( "\n", $lines );
				}
				return '';

			case 'allowed_days':
				// Return as array for multiselect.
				if ( isset( $current['allowed_days'] ) ) {
					return $current['allowed_days'];
				}
				return array( 27 );

			case 'count_usage_statuses':
			case 'decrement_usage_statuses':
				// Return as array for multiselect.
				if ( isset( $current[ $settings_key ] ) ) {
					return $current[ $settings_key ];
				}
				return $option['default'] ?? array();

			default:
				// For checkboxes, convert bool to 'yes'/'no'.
				if ( isset( $option['type'] ) && 'checkbox' === $option['type'] ) {
					if ( isset( $current[ $settings_key ] ) ) {
						return $current[ $settings_key ] ? 'yes' : 'no';
					}
					return $option['default'] ?? 'no';
				}

				// For other fields, return the value directly.
				if ( isset( $current[ $settings_key ] ) ) {
					return $current[ $settings_key ];
				}

				return $option['default'] ?? '';
		}
	}

	/**
	 * AJAX handler to purge old logs.
	 *
	 * @return void
	 */
	public function ajax_purge_old_logs() {
		// Verify nonce.
		check_ajax_referer( 'wcgk_purge_logs', 'nonce' );

		// Verify capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wc-coupon-gatekeeper' ) ) );
		}

		global $wpdb;

		$table_name       = Database::get_table_name();
		$retention_months = $this->settings->get_log_retention_months();

		// Calculate cutoff date.
		$cutoff_date = wp_date( 'Y-m-d H:i:s', strtotime( "-{$retention_months} months" ) );

		// Delete old records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE updated_at < %s",
				$cutoff_date
			)
		);

		if ( false === $deleted ) {
			wp_send_json_error(
				array(
					'message' => __( 'Database error while purging logs.', 'wc-coupon-gatekeeper' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of deleted records */
					_n( 'Successfully deleted %d log record.', 'Successfully deleted %d log records.', $deleted, 'wc-coupon-gatekeeper' ),
					$deleted
				),
				'deleted' => $deleted,
			)
		);
	}
}