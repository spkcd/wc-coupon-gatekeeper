<?php
/**
 * Bootstrap class for WC Coupon Gatekeeper.
 *
 * Main entry point that initializes all plugin components.
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper;

/**
 * Class Bootstrap
 *
 * Singleton pattern to ensure single instance and coordinate plugin initialization.
 */
class Bootstrap {

	/**
	 * The single instance of this class.
	 *
	 * @var Bootstrap|null
	 */
	private static $instance = null;

	/**
	 * Settings manager instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Admin settings screen instance.
	 *
	 * @var Admin\Settings_Screen
	 */
	private $admin_settings;

	/**
	 * Admin usage logs screen instance.
	 *
	 * @var Admin\Usage_Logs_Screen
	 */
	private $usage_logs_screen;

	/**
	 * Coupon validator instance.
	 *
	 * @var Validator\Coupon_Validator
	 */
	private $coupon_validator;

	/**
	 * Usage logger instance.
	 *
	 * @var Logger\Usage_Logger
	 */
	private $usage_logger;

	/**
	 * Get singleton instance.
	 *
	 * @return Bootstrap
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		// Prevent cloning and unserialization.
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init() {
		// Initialize settings manager.
		$this->settings = new Settings();

		// Initialize admin components (only in admin context).
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Initialize frontend components.
		$this->init_frontend();

		// Hook into WordPress/WooCommerce lifecycle.
		$this->register_hooks();
	}

	/**
	 * Initialize admin components.
	 *
	 * @return void
	 */
	private function init_admin() {
		$this->admin_settings     = new Admin\Settings_Screen( $this->settings );
		$this->usage_logs_screen  = new Admin\Usage_Logs_Screen( $this->settings );
	}

	/**
	 * Initialize frontend components.
	 *
	 * @return void
	 */
	private function init_frontend() {
		$this->coupon_validator = new Validator\Coupon_Validator( $this->settings );
		$this->usage_logger     = new Logger\Usage_Logger( $this->settings );
	}

	/**
	 * Register WordPress/WooCommerce hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		// TODO: Register validation hooks in Coupon_Validator.
		// TODO: Register logging hooks in Usage_Logger.
	}

	/**
	 * Get settings instance.
	 *
	 * @return Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get coupon validator instance.
	 *
	 * @return Validator\Coupon_Validator
	 */
	public function get_coupon_validator() {
		return $this->coupon_validator;
	}

	/**
	 * Get usage logger instance.
	 *
	 * @return Logger\Usage_Logger
	 */
	public function get_usage_logger() {
		return $this->usage_logger;
	}
}