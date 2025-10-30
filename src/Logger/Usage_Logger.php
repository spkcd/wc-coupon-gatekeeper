<?php
/**
 * Usage logger for WC Coupon Gatekeeper.
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper\Logger;

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Database;

/**
 * Class Usage_Logger
 *
 * Logs coupon usage to database when orders change status.
 * Increments on processing/completed, decrements on cancelled/refunded.
 */
class Usage_Logger {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Track which orders have been processed to avoid double-counting.
	 *
	 * @var array
	 */
	private $processed_orders = array();

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
		// Hook into order status transitions.
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
	}

	/**
	 * Handle order status changes.
	 *
	 * Increment usage when transitioning to count status.
	 * Decrement usage when transitioning to decrement status.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status (without 'wc-' prefix).
	 * @param string $new_status New status (without 'wc-' prefix).
	 * @param object $order      Order object.
	 * @return void
	 */
	public function handle_status_change( $order_id, $old_status, $new_status, $order ) {
		// Prevent double processing in same request.
		$transition_key = $order_id . '_' . $old_status . '_' . $new_status;
		if ( isset( $this->processed_orders[ $transition_key ] ) ) {
			return;
		}
		$this->processed_orders[ $transition_key ] = true;

		// Get status lists from settings.
		$count_statuses     = $this->settings->get_count_usage_statuses();
		$decrement_statuses = $this->settings->get_decrement_usage_statuses();

		// Check if we should increment usage.
		if ( in_array( $new_status, $count_statuses, true ) && ! in_array( $old_status, $count_statuses, true ) ) {
			$this->increment_order_coupons( $order_id, $order );
		}

		// Check if we should decrement usage.
		if ( in_array( $new_status, $decrement_statuses, true ) && in_array( $old_status, $count_statuses, true ) ) {
			$this->decrement_order_coupons( $order_id, $order );
		}
	}

	/**
	 * Increment usage for all managed coupons in an order.
	 *
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    Order object.
	 * @return void
	 */
	private function increment_order_coupons( $order_id, $order ) {
		// Get coupons used in order.
		$coupon_codes = $order->get_coupon_codes();
		if ( empty( $coupon_codes ) ) {
			return;
		}

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );
		if ( empty( $customer_key ) ) {
			// Add order note if customer key couldn't be determined.
			$order->add_order_note(
				__( 'Coupon Gatekeeper: Could not determine customer identifier for usage tracking.', 'wc-coupon-gatekeeper' )
			);
			return;
		}

		// Get order month for tracking.
		$order_month = $this->get_order_month( $order );

		// Process each coupon.
		foreach ( $coupon_codes as $coupon_code ) {
			$coupon_code = strtolower( $coupon_code );

			// Only track managed coupons.
			if ( ! $this->settings->is_coupon_managed( $coupon_code ) ) {
				continue;
			}

			// Increment usage in database.
			$success = Database::increment_usage( $coupon_code, $customer_key, $order_id, $order_month );

			if ( $success ) {
				// Add order note for successful tracking.
				$order->add_order_note(
					sprintf(
						/* translators: 1: coupon code, 2: month */
						__( 'Coupon Gatekeeper: Usage incremented for "%1$s" in %2$s.', 'wc-coupon-gatekeeper' ),
						$coupon_code,
						$order_month
					)
				);
			} else {
				// Log error if increment failed.
				$order->add_order_note(
					sprintf(
						/* translators: %s: coupon code */
						__( 'Coupon Gatekeeper: Failed to increment usage for "%s".', 'wc-coupon-gatekeeper' ),
						$coupon_code
					)
				);
			}
		}
	}

	/**
	 * Decrement usage for all managed coupons in an order.
	 *
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    Order object.
	 * @return void
	 */
	private function decrement_order_coupons( $order_id, $order ) {
		// Get coupons used in order.
		$coupon_codes = $order->get_coupon_codes();
		if ( empty( $coupon_codes ) ) {
			return;
		}

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );
		if ( empty( $customer_key ) ) {
			return;
		}

		// Get order month for tracking.
		$order_month = $this->get_order_month( $order );

		// Process each coupon.
		foreach ( $coupon_codes as $coupon_code ) {
			$coupon_code = strtolower( $coupon_code );

			// Only track managed coupons.
			if ( ! $this->settings->is_coupon_managed( $coupon_code ) ) {
				continue;
			}

			// Decrement usage in database.
			$success = Database::decrement_usage( $coupon_code, $customer_key, $order_id, $order_month );

			if ( $success ) {
				// Add order note for successful tracking.
				$order->add_order_note(
					sprintf(
						/* translators: 1: coupon code, 2: month */
						__( 'Coupon Gatekeeper: Usage decremented for "%1$s" in %2$s.', 'wc-coupon-gatekeeper' ),
						$coupon_code,
						$order_month
					)
				);
			}
		}
	}

	/**
	 * Get customer key from order.
	 *
	 * Priority:
	 * 1. If user_id_priority and order has user: user:{ID}
	 * 2. If billing email available: email:{hash} or email:{lowercase}
	 * 3. Fallback to user ID if available
	 *
	 * @param \WC_Order $order Order object.
	 * @return string Customer key or empty string.
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
	 * Generate anonymized customer key.
	 *
	 * @param string $identifier Customer identifier (email).
	 * @return string
	 */
	private function anonymize_customer_key( $identifier ) {
		return 'hash:' . hash( 'sha256', $identifier . wp_salt( 'auth' ) );
	}

	/**
	 * Get order month in YYYY-MM format.
	 *
	 * Uses order creation date for determining the month.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string Month in YYYY-MM format.
	 */
	private function get_order_month( $order ) {
		$order_date = $order->get_date_created();
		if ( $order_date ) {
			return $order_date->format( 'Y-m' );
		}

		// Fallback to current month if order date not available.
		return Database::get_current_month();
	}
}