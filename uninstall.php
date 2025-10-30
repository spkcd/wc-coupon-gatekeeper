<?php
/**
 * Uninstall script for WC Coupon Gatekeeper.
 *
 * @package WC_Coupon_Gatekeeper
 */

// Exit if accessed directly or not in uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Get plugin settings.
$settings = get_option( 'wc_coupon_gatekeeper_settings', array() );

// Check if user wants to delete data on uninstall.
if ( isset( $settings['delete_data_on_uninstall'] ) && true === $settings['delete_data_on_uninstall'] ) {
	// Drop the custom table.
	$table_name = $wpdb->prefix . 'wc_coupon_gatekeeper_usage';
	
	// Use %i placeholder on WordPress 6.2+, direct query on older versions.
	if ( version_compare( $GLOBALS['wp_version'], '6.2', '>=' ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	// Delete plugin options.
	delete_option( 'wc_coupon_gatekeeper_settings' );
	delete_option( 'wc_coupon_gatekeeper_db_version' );

	// Delete transients and cached data.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_coupon_gatekeeper_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_coupon_gatekeeper_%'" );
}