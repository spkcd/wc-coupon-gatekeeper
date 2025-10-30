<?php
/**
 * Plugin Name: WC Coupon Gatekeeper
 * Plugin URI: https://sparkwebstudio.com/wc-coupon-gatekeeper
 * Description: Restrict WooCommerce coupons to specific days of the month and enforce per-customer monthly usage limits.
 * Version: 1.0.0
 * Author: SPARKWEB Studio
 * Author URI: https://sparkwebstudio.com
 * Text Domain: wc-coupon-gatekeeper
 * Domain Path: /languages
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * WC requires at least: 3.5
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WC_COUPON_GATEKEEPER_VERSION', '1.0.0' );
define( 'WC_COUPON_GATEKEEPER_FILE', __FILE__ );
define( 'WC_COUPON_GATEKEEPER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_COUPON_GATEKEEPER_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_COUPON_GATEKEEPER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'WC_Coupon_Gatekeeper\\';
		$base_dir = WC_COUPON_GATEKEEPER_PATH . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 *
 * @return void
 */
function declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_COUPON_GATEKEEPER_FILE, true );
	}
}
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_hpos_compatibility' );

/**
 * Check if WooCommerce is active and initialize plugin.
 *
 * @return void
 */
function init_plugin() {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\display_woocommerce_missing_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'wc-coupon-gatekeeper', false, dirname( WC_COUPON_GATEKEEPER_BASENAME ) . '/languages' );

	// Bootstrap the plugin.
	Bootstrap::instance()->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_plugin' );

/**
 * Display admin notice when WooCommerce is not active.
 *
 * @return void
 */
function display_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: WooCommerce plugin link */
					__( '<strong>WC Coupon Gatekeeper</strong> requires WooCommerce to be installed and active. Please install %s first.', 'wc-coupon-gatekeeper' ),
					'<a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">WooCommerce</a>'
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function activate_plugin() {
	// Ensure WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( WC_COUPON_GATEKEEPER_BASENAME );
		wp_die(
			wp_kses_post( __( 'WC Coupon Gatekeeper requires WooCommerce to be installed and active.', 'wc-coupon-gatekeeper' ) ),
			esc_html__( 'Plugin Activation Error', 'wc-coupon-gatekeeper' ),
			array( 'back_link' => true )
		);
	}

	require_once WC_COUPON_GATEKEEPER_PATH . 'src/Database.php';
	Database::create_tables();

	// Set default options if not already set.
	if ( false === get_option( 'wc_coupon_gatekeeper_settings' ) ) {
		$defaults = array(
			'enable_day_restriction'      => true,
			'enable_monthly_limit'        => true,
			'restricted_coupons'          => array(),
			'apply_to_all_coupons'        => false,
			'allowed_days'                => array( 27 ),
			'use_last_valid_day'          => false,
			'default_monthly_limit'       => 1,
			'coupon_limit_overrides'      => array(),
			'customer_identification'     => 'user_id_priority',
			'anonymize_email'             => true,
			'error_not_allowed_day'       => __( 'This coupon can only be used on the allowed day(s) each month.', 'wc-coupon-gatekeeper' ),
			'error_limit_reached'         => __( "You've already used this coupon this month.", 'wc-coupon-gatekeeper' ),
			'count_usage_statuses'        => array( 'processing', 'completed' ),
			'decrement_usage_statuses'    => array( 'cancelled', 'refunded' ),
			'admin_bypass_edit_order'     => true,
			'log_retention_months'        => 18,
			'delete_data_on_uninstall'    => false,
		);
		add_option( 'wc_coupon_gatekeeper_settings', $defaults );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate_plugin' );

/**
 * Handle new site creation in multisite.
 *
 * @param WP_Site $new_site New site object.
 * @return void
 */
function on_new_site_created( $new_site ) {
	if ( is_plugin_active_for_network( WC_COUPON_GATEKEEPER_BASENAME ) ) {
		switch_to_blog( $new_site->blog_id );
		activate_plugin();
		restore_current_blog();
	}
}
add_action( 'wp_initialize_site', __NAMESPACE__ . '\\on_new_site_created' );

/**
 * Handle site deletion in multisite.
 *
 * @param WP_Site $old_site Site object being deleted.
 * @return void
 */
function on_site_deleted( $old_site ) {
	// Get plugin settings before switching.
	$settings = get_option( 'wc_coupon_gatekeeper_settings', array() );
	
	// Only cleanup if delete_data_on_uninstall is enabled.
	if ( isset( $settings['delete_data_on_uninstall'] ) && true === $settings['delete_data_on_uninstall'] ) {
		global $wpdb;
		
		switch_to_blog( $old_site->blog_id );
		
		// Drop the custom table.
		$table_name = $wpdb->prefix . 'wc_coupon_gatekeeper_usage';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		
		// Delete plugin options.
		delete_option( 'wc_coupon_gatekeeper_settings' );
		delete_option( 'wc_coupon_gatekeeper_db_version' );
		
		restore_current_blog();
	}
}
add_action( 'wp_delete_site', __NAMESPACE__ . '\\on_site_deleted' );