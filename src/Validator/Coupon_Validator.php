<?php
/**
 * Coupon validator for WC Coupon Gatekeeper.
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper\Validator;

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Database;

/**
 * Class Coupon_Validator
 *
 * Validates coupons against day restrictions and monthly usage limits.
 */
class Coupon_Validator {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

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
	 * Initialize WordPress/WooCommerce hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 3 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'fallback_recheck_on_order_creation' ), 10, 1 );
	}

	/**
	 * Validate coupon against day and monthly usage restrictions.
	 *
	 * @param bool       $valid  Whether coupon is valid.
	 * @param \WC_Coupon $coupon Coupon object.
	 * @param \WC_Cart   $cart   Cart object (optional).
	 * @return bool
	 * @throws \Exception If validation fails.
	 */
	public function validate_coupon( $valid, $coupon, $cart = null ) {
		// If already invalid, don't override.
		if ( ! $valid ) {
			return $valid;
		}

		// Get coupon code.
		$coupon_code = strtolower( $coupon->get_code() );

		// Check if this coupon is managed by the plugin.
		if ( ! $this->settings->is_coupon_managed( $coupon_code ) ) {
			return $valid;
		}

		// Check admin bypass (only in wp-admin, not AJAX).
		if ( $this->should_bypass_for_admin() ) {
			return $valid;
		}

		// Track if fallback day was used.
		$is_fallback_day = false;

		// Validate day restriction.
		if ( $this->settings->is_day_restriction_enabled() ) {
			$day_check = $this->check_day_allowed();
			
			if ( ! $day_check['allowed'] ) {
				$error_message = $this->settings->get_error_not_allowed_day();
				throw new \Exception( esc_html( $error_message ) );
			}

			$is_fallback_day = $day_check['is_fallback'];
		}

		// Validate monthly limit.
		if ( $this->settings->is_monthly_limit_enabled() ) {
			$customer_key = $this->get_customer_key();

			// If no customer key available (guest without email at validation time),
			// allow provisional validation. Will re-check at order creation.
			if ( ! empty( $customer_key ) && $this->has_exceeded_limit( $coupon_code, $customer_key ) ) {
				$error_message = $this->settings->get_error_limit_reached();
				throw new \Exception( esc_html( $error_message ) );
			}
		}

		// Add success notices if validation passed.
		$this->add_success_notices( $is_fallback_day );

		return $valid;
	}

	/**
	 * Check if current day is allowed for coupon usage.
	 *
	 * Supports "Use Last Valid Day" logic for missing days in shorter months.
	 *
	 * @return array Array with 'allowed' (bool) and 'is_fallback' (bool).
	 */
	private function check_day_allowed() {
		$current_day  = Database::get_current_day();
		$allowed_days = $this->settings->get_allowed_days();

		// Direct match: current day is in allowed days.
		if ( in_array( $current_day, $allowed_days, true ) ) {
			return array(
				'allowed'     => true,
				'is_fallback' => false,
			);
		}

		// Check "Use Last Valid Day" logic.
		if ( $this->settings->use_last_valid_day() ) {
			$is_fallback = $this->is_today_last_valid_day( $current_day, $allowed_days );
			return array(
				'allowed'     => $is_fallback,
				'is_fallback' => $is_fallback,
			);
		}

		return array(
			'allowed'     => false,
			'is_fallback' => false,
		);
	}

	/**
	 * Add success notices to WooCommerce when coupon is valid.
	 *
	 * @param bool $is_fallback_day Whether today is a fallback day.
	 * @return void
	 */
	private function add_success_notices( $is_fallback_day ) {
		// Don't show notices in admin context.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Show fallback day notice if applicable.
		if ( $is_fallback_day ) {
			wc_add_notice(
				__( 'Coupon valid today because the configured day doesn\'t occur this month.', 'wc-coupon-gatekeeper' ),
				'notice'
			);
			return; // Don't show both notices.
		}

		// Show success message if enabled.
		if ( $this->settings->is_success_message_enabled() ) {
			$success_message = $this->settings->get_success_message();
			wc_add_notice( esc_html( $success_message ), 'success' );
		}
	}

	/**
	 * Check if customer has exceeded monthly usage limit.
	 *
	 * @param string $coupon_code  Coupon code (lowercase).
	 * @param string $customer_key Customer identifier.
	 * @return bool True if limit exceeded, false otherwise.
	 */
	private function has_exceeded_limit( $coupon_code, $customer_key ) {
		// Get current month usage count from database.
		$current_count = Database::get_usage_count( $coupon_code, $customer_key );

		// Get applicable limit for this coupon.
		$limit = $this->settings->get_monthly_limit_for_coupon( $coupon_code );

		// Check if count has reached or exceeded limit.
		return $current_count >= $limit;
	}

	/**
	 * Check if we should bypass validation for admin users.
	 *
	 * Only bypasses in wp-admin context (not AJAX) when editing orders.
	 *
	 * @return bool
	 */
	private function should_bypass_for_admin() {
		// Check if admin bypass is enabled.
		if ( ! $this->settings->is_admin_bypass_enabled() ) {
			return false;
		}

		// Only bypass in wp-admin context, not during AJAX.
		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}

		// Check if current user has woocommerce management capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if today is the last valid day of the month for a missing configured day.
	 *
	 * Example: If allowed days are [31] and today is Feb 28 (non-leap year),
	 * this returns true because Feb doesn't have a 31st day.
	 *
	 * @param int   $current_day  Current day of month (1-31).
	 * @param array $allowed_days Array of allowed days (1-31).
	 * @return bool
	 */
	private function is_today_last_valid_day( $current_day, $allowed_days ) {
		// Get current year and month.
		$current_year  = (int) wp_date( 'Y' );
		$current_month = (int) wp_date( 'n' );

		// Get the last day of current month.
		$last_day_of_month = (int) wp_date( 't' );

		// Check if today is the last day of the month.
		if ( $current_day !== $last_day_of_month ) {
			return false;
		}

		// Check if any allowed day is greater than the last day of this month.
		foreach ( $allowed_days as $allowed_day ) {
			if ( $allowed_day > $last_day_of_month ) {
				// Found a configured day that doesn't exist this month.
				// Today (last day) is allowed as a fallback.
				return true;
			}
		}

		return false;
	}

	/**
	 * Get customer key for tracking during validation.
	 *
	 * Priority:
	 * 1. If logged in and user_id_priority: user:{ID}
	 * 2. If email available in checkout: email:{hash} or email:{lowercase}
	 * 3. Otherwise: empty (provisional validation, will re-check at order creation)
	 *
	 * @return string Customer key or empty string.
	 */
	private function get_customer_key() {
		$identification_method = $this->settings->get_customer_identification();

		// If user is logged in and user_id_priority is enabled.
		if ( 'user_id_priority' === $identification_method && is_user_logged_in() ) {
			return 'user:' . get_current_user_id();
		}

		// Try to get email from customer session or checkout.
		$email = $this->get_customer_email_from_session();

		if ( ! empty( $email ) ) {
			return $this->generate_customer_key_from_email( $email );
		}

		// No customer key available at this time (guest without email in cart).
		// Return empty to allow provisional validation.
		return '';
	}

	/**
	 * Get customer email from WooCommerce session or checkout.
	 *
	 * @return string Email address or empty string.
	 */
	private function get_customer_email_from_session() {
		// Try to get from logged-in user first.
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user && $user->user_email ) {
				return strtolower( trim( $user->user_email ) );
			}
		}

		// Try to get from WooCommerce customer session.
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$email = WC()->customer->get_billing_email();
			if ( ! empty( $email ) ) {
				return strtolower( trim( $email ) );
			}
		}

		return '';
	}

	/**
	 * Generate customer key from email based on anonymization setting.
	 *
	 * @param string $email Email address.
	 * @return string Customer key.
	 */
	private function generate_customer_key_from_email( $email ) {
		$email = strtolower( trim( $email ) );

		if ( $this->settings->is_email_anonymization_enabled() ) {
			return $this->anonymize_customer_key( $email );
		}

		return 'email:' . $email;
	}

	/**
	 * Get customer key from order object.
	 *
	 * Used during order creation and status changes.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string Customer key.
	 */
	private function get_customer_key_from_order( $order ) {
		$identification_method = $this->settings->get_customer_identification();

		// If order has a user ID and user_id_priority is enabled.
		$user_id = $order->get_user_id();
		if ( 'user_id_priority' === $identification_method && $user_id ) {
			return 'user:' . $user_id;
		}

		// Use billing email.
		$email = $order->get_billing_email();
		if ( ! empty( $email ) ) {
			return $this->generate_customer_key_from_email( $email );
		}

		// Fallback: use user ID if available (edge case).
		if ( $user_id ) {
			return 'user:' . $user_id;
		}

		return '';
	}

	/**
	 * Generate anonymized customer key.
	 *
	 * @param string $identifier Customer identifier (user ID or email).
	 * @return string
	 */
	private function anonymize_customer_key( $identifier ) {
		return 'hash:' . hash( 'sha256', $identifier . wp_salt( 'auth' ) );
	}

	/**
	 * Fallback re-check on order creation.
	 *
	 * If validation couldn't get customer key (guest without email in cart),
	 * re-validate with billing email from order. Remove coupon if over limit.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function fallback_recheck_on_order_creation( $order_id ) {
		// Get order object.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );
		if ( empty( $customer_key ) ) {
			return; // No customer key available, can't validate.
		}

		// Get coupons used in order.
		$coupon_codes = $order->get_coupon_codes();
		if ( empty( $coupon_codes ) ) {
			return; // No coupons to validate.
		}

		// Check each coupon for monthly limit compliance.
		foreach ( $coupon_codes as $coupon_code ) {
			$coupon_code = strtolower( $coupon_code );

			// Check if this coupon is managed.
			if ( ! $this->settings->is_coupon_managed( $coupon_code ) ) {
				continue;
			}

			// Skip if monthly limit is disabled.
			if ( ! $this->settings->is_monthly_limit_enabled() ) {
				continue;
			}

			// Check if limit exceeded.
			if ( $this->has_exceeded_limit( $coupon_code, $customer_key ) ) {
				// Remove coupon from order.
				$order->remove_coupon( $coupon_code );

				// Add order note.
				$error_message = $this->settings->get_error_limit_reached();
				$order->add_order_note(
					sprintf(
						/* translators: 1: coupon code, 2: error message */
						__( 'Coupon "%1$s" was automatically removed: %2$s', 'wc-coupon-gatekeeper' ),
						$coupon_code,
						$error_message
					)
				);

				// Recalculate order totals.
				$order->calculate_totals();
				$order->save();

				// Add notice for customer (will show on thank-you page if not already redirected).
				wc_add_notice(
					sprintf(
						/* translators: 1: coupon code, 2: error message */
						__( 'Coupon "%1$s" was removed from your order: %2$s', 'wc-coupon-gatekeeper' ),
						$coupon_code,
						$error_message
					),
					'error'
				);
			}
		}
	}
}