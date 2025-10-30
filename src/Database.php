<?php
/**
 * Database management for WC Coupon Gatekeeper.
 *
 * Handles table creation and schema updates.
 *
 * @package WC_Coupon_Gatekeeper
 */

namespace WC_Coupon_Gatekeeper;

/**
 * Class Database
 *
 * Manages custom database tables for usage tracking.
 */
class Database {

	/**
	 * Database version for schema management.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Get the usage table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wc_coupon_gatekeeper_usage';
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			coupon_code varchar(100) NOT NULL,
			customer_key varchar(191) NOT NULL,
			month char(7) NOT NULL,
			count int(10) unsigned NOT NULL DEFAULT 0,
			last_order_id bigint(20) unsigned DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY coupon_customer_month (coupon_code, customer_key, month),
			KEY coupon_month (coupon_code, month),
			KEY customer_month (customer_key, month)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Update DB version.
		update_option( 'wc_coupon_gatekeeper_db_version', self::DB_VERSION );
	}

	/**
	 * Check if tables exist.
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		return $table_name === $result;
	}

	/**
	 * Get current month string in YYYY-MM format.
	 *
	 * @return string
	 */
	public static function get_current_month() {
		return wp_date( 'Y-m' );
	}

	/**
	 * Get current day of the month.
	 *
	 * @return int
	 */
	public static function get_current_day() {
		return (int) wp_date( 'j' );
	}

	/**
	 * Get current datetime string for database.
	 *
	 * @return string
	 */
	public static function get_current_datetime() {
		return wp_date( 'Y-m-d H:i:s' );
	}

	/**
	 * Get usage count for a coupon and customer in current month.
	 *
	 * @param string $coupon_code  Coupon code (lowercase).
	 * @param string $customer_key Customer identifier.
	 * @param string $month        Month in YYYY-MM format (optional, defaults to current).
	 * @return int Usage count.
	 */
	public static function get_usage_count( $coupon_code, $customer_key, $month = null ) {
		global $wpdb;

		if ( null === $month ) {
			$month = self::get_current_month();
		}

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count FROM {$table_name} WHERE coupon_code = %s AND customer_key = %s AND month = %s",
				$coupon_code,
				$customer_key,
				$month
			)
		);

		return $count ? absint( $count ) : 0;
	}

	/**
	 * Increment usage count for a coupon and customer.
	 *
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE for concurrency safety.
	 *
	 * @param string $coupon_code  Coupon code (lowercase).
	 * @param string $customer_key Customer identifier.
	 * @param int    $order_id     Order ID.
	 * @param string $month        Month in YYYY-MM format (optional, defaults to current).
	 * @return bool Success status.
	 */
	public static function increment_usage( $coupon_code, $customer_key, $order_id, $month = null ) {
		global $wpdb;

		if ( null === $month ) {
			$month = self::get_current_month();
		}

		$table_name = self::get_table_name();
		$now        = self::get_current_datetime();

		// Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (coupon_code, customer_key, month, count, last_order_id, updated_at)
				VALUES (%s, %s, %s, 1, %d, %s)
				ON DUPLICATE KEY UPDATE
					count = count + 1,
					last_order_id = VALUES(last_order_id),
					updated_at = VALUES(updated_at)",
				$coupon_code,
				$customer_key,
				$month,
				$order_id,
				$now
			)
		);

		return false !== $result;
	}

	/**
	 * Decrement usage count for a coupon and customer.
	 *
	 * Only decrements if count > 0 to prevent negative values.
	 *
	 * @param string $coupon_code  Coupon code (lowercase).
	 * @param string $customer_key Customer identifier.
	 * @param int    $order_id     Order ID.
	 * @param string $month        Month in YYYY-MM format (optional, defaults to current).
	 * @return bool Success status.
	 */
	public static function decrement_usage( $coupon_code, $customer_key, $order_id, $month = null ) {
		global $wpdb;

		if ( null === $month ) {
			$month = self::get_current_month();
		}

		$table_name = self::get_table_name();
		$now        = self::get_current_datetime();

		// Only decrement if count > 0.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET count = GREATEST(0, count - 1),
					last_order_id = %d,
					updated_at = %s
				WHERE coupon_code = %s
					AND customer_key = %s
					AND month = %s
					AND count > 0",
				$order_id,
				$now,
				$coupon_code,
				$customer_key,
				$month
			)
		);

		return false !== $result;
	}

	/**
	 * Delete old usage records based on retention period.
	 *
	 * @param int $retention_months Number of months to retain.
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_records( $retention_months ) {
		global $wpdb;

		$table_name   = self::get_table_name();
		$cutoff_month = wp_date( 'Y-m', strtotime( "-{$retention_months} months" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE month < %s",
				$cutoff_month
			)
		);

		return absint( $deleted );
	}
}