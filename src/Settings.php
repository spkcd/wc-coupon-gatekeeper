<?php
/**
 * Settings manager for WC Coupon Gatekeeper.
 *
 * Handles retrieval and updating of plugin settings.
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper;

/**
 * Class Settings
 *
 * Centralized settings management with typed getters.
 */
class Settings {

	/**
	 * Option name in wp_options table.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wc_coupon_gatekeeper_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_all() {
		if ( null === $this->settings ) {
			$defaults       = $this->get_defaults();
			$saved          = get_option( self::OPTION_NAME, array() );
			$this->settings = wp_parse_args( $saved, $defaults );
		}
		return $this->settings;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_all();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array $new_settings New settings to merge with existing.
	 * @return bool
	 */
	public function update( $new_settings ) {
		$settings       = $this->get_all();
		$settings       = array_merge( $settings, $new_settings );
		$this->settings = $settings;
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Clear cached settings (force reload from DB).
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->settings = null;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	private function get_defaults() {
		return array(
			// Feature Toggles.
			'enable_day_restriction'      => true,
			'enable_monthly_limit'        => true,

			// Coupon Targeting.
			'restricted_coupons'          => array(),
			'apply_to_all_coupons'        => false,

			// Allowed Days.
			'allowed_days'                => array( 27 ),
			'use_last_valid_day'          => false,

			// Monthly Limit.
			'default_monthly_limit'       => 1,
			'coupon_limit_overrides'      => array(),
			'customer_identification'     => 'user_id_priority',
			'anonymize_email'             => true,

			// Messages.
			'error_not_allowed_day'       => __( 'This coupon can only be used on the allowed day(s) each month.', 'wc-coupon-gatekeeper' ),
			'error_limit_reached'         => __( "You've already used this coupon this month.", 'wc-coupon-gatekeeper' ),
			'enable_success_message'      => false,
			'success_message'             => __( 'Nice timing! This coupon is valid today.', 'wc-coupon-gatekeeper' ),

			// Advanced.
			'count_usage_statuses'        => array( 'processing', 'completed' ),
			'decrement_usage_statuses'    => array( 'cancelled', 'refunded' ),
			'admin_bypass_edit_order'     => true,
			'log_retention_months'        => 18,

			// Legacy/other.
			'delete_data_on_uninstall'    => false,
		);
	}

	// =========================================================================
	// Feature Toggles
	// =========================================================================

	/**
	 * Check if day-of-month restriction is enabled.
	 *
	 * @return bool
	 */
	public function is_day_restriction_enabled() {
		return (bool) $this->get( 'enable_day_restriction', true );
	}

	/**
	 * Check if per-customer monthly limit is enabled.
	 *
	 * @return bool
	 */
	public function is_monthly_limit_enabled() {
		return (bool) $this->get( 'enable_monthly_limit', true );
	}

	// =========================================================================
	// Coupon Targeting
	// =========================================================================

	/**
	 * Get restricted coupon codes (lowercase).
	 *
	 * @return array
	 */
	public function get_restricted_coupons() {
		$coupons = $this->get( 'restricted_coupons', array() );
		return is_array( $coupons ) ? $coupons : array();
	}

	/**
	 * Check if rules apply to all coupons.
	 *
	 * @return bool
	 */
	public function apply_to_all_coupons() {
		return (bool) $this->get( 'apply_to_all_coupons', false );
	}

	/**
	 * Check if a specific coupon is managed by this plugin.
	 *
	 * @param string $coupon_code Coupon code to check.
	 * @return bool
	 */
	public function is_coupon_managed( $coupon_code ) {
		if ( $this->apply_to_all_coupons() ) {
			return true;
		}

		$restricted_coupons = $this->get_restricted_coupons();
		return in_array( strtolower( $coupon_code ), $restricted_coupons, true );
	}

	// =========================================================================
	// Allowed Days
	// =========================================================================

	/**
	 * Get allowed days of the month.
	 *
	 * @return array Array of integers (1-31).
	 */
	public function get_allowed_days() {
		$days = $this->get( 'allowed_days', array( 27 ) );
		$days = is_array( $days ) ? array_map( 'intval', $days ) : array( 27 );
		return ! empty( $days ) ? $days : array( 27 );
	}

	/**
	 * Check if should use last valid day of month when selected day doesn't exist.
	 *
	 * @return bool
	 */
	public function use_last_valid_day() {
		return (bool) $this->get( 'use_last_valid_day', false );
	}

	// =========================================================================
	// Monthly Limit
	// =========================================================================

	/**
	 * Get default monthly usage limit.
	 *
	 * @return int
	 */
	public function get_default_monthly_limit() {
		return max( 1, absint( $this->get( 'default_monthly_limit', 1 ) ) );
	}

	/**
	 * Get monthly limit for a specific coupon code.
	 *
	 * @param string $coupon_code Coupon code.
	 * @return int
	 */
	public function get_monthly_limit_for_coupon( $coupon_code ) {
		$overrides = $this->get_coupon_limit_overrides();
		$code      = strtolower( $coupon_code );

		if ( isset( $overrides[ $code ] ) ) {
			return max( 1, absint( $overrides[ $code ] ) );
		}

		return $this->get_default_monthly_limit();
	}

	/**
	 * Get per-coupon limit overrides as associative array.
	 *
	 * @return array [coupon_code => limit]
	 */
	public function get_coupon_limit_overrides() {
		$overrides = $this->get( 'coupon_limit_overrides', array() );
		return is_array( $overrides ) ? $overrides : array();
	}

	/**
	 * Get customer identification method.
	 *
	 * @return string 'user_id_priority' or 'email_only'
	 */
	public function get_customer_identification() {
		return $this->get( 'customer_identification', 'user_id_priority' );
	}

	/**
	 * Check if email anonymization is enabled.
	 *
	 * @return bool
	 */
	public function is_email_anonymization_enabled() {
		return (bool) $this->get( 'anonymize_email', true );
	}

	// =========================================================================
	// Messages
	// =========================================================================

	/**
	 * Get error message for not allowed day.
	 *
	 * @return string
	 */
	public function get_error_not_allowed_day() {
		return $this->get(
			'error_not_allowed_day',
			__( 'This coupon can only be used on the allowed day(s) each month.', 'wc-coupon-gatekeeper' )
		);
	}

	/**
	 * Get error message for monthly limit reached.
	 *
	 * @return string
	 */
	public function get_error_limit_reached() {
		return $this->get(
			'error_limit_reached',
			__( "You've already used this coupon this month.", 'wc-coupon-gatekeeper' )
		);
	}

	/**
	 * Check if success message is enabled.
	 *
	 * @return bool
	 */
	public function is_success_message_enabled() {
		return (bool) $this->get( 'enable_success_message', false );
	}

	/**
	 * Get success message for allowed days.
	 *
	 * @return string
	 */
	public function get_success_message() {
		return $this->get(
			'success_message',
			__( 'Nice timing! This coupon is valid today.', 'wc-coupon-gatekeeper' )
		);
	}

	// =========================================================================
	// Advanced
	// =========================================================================

	/**
	 * Get order statuses that should count towards usage.
	 *
	 * @return array
	 */
	public function get_count_usage_statuses() {
		$statuses = $this->get( 'count_usage_statuses', array( 'processing', 'completed' ) );
		return is_array( $statuses ) ? $statuses : array( 'processing', 'completed' );
	}

	/**
	 * Get order statuses that should decrement usage.
	 *
	 * @return array
	 */
	public function get_decrement_usage_statuses() {
		$statuses = $this->get( 'decrement_usage_statuses', array( 'cancelled', 'refunded' ) );
		return is_array( $statuses ) ? $statuses : array( 'cancelled', 'refunded' );
	}

	/**
	 * Check if admin bypass is enabled for order edit screen.
	 *
	 * @return bool
	 */
	public function is_admin_bypass_enabled() {
		return (bool) $this->get( 'admin_bypass_edit_order', true );
	}

	/**
	 * Get log retention period in months.
	 *
	 * @return int
	 */
	public function get_log_retention_months() {
		return max( 1, absint( $this->get( 'log_retention_months', 18 ) ) );
	}

	/**
	 * Check if data should be deleted on uninstall.
	 *
	 * @return bool
	 */
	public function delete_data_on_uninstall() {
		return (bool) $this->get( 'delete_data_on_uninstall', false );
	}
}