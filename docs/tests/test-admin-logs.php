<?php
/**
 * Tests for Admin Usage Logs Screen.
 *
 * @package WC_Coupon_Gatekeeper
 */

use WC_Coupon_Gatekeeper\Admin\Usage_Logs_Screen;
use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Database;

/**
 * Class Test_Admin_Logs
 *
 * Tests admin logs screen functionality.
 */
class Test_Admin_Logs extends WP_UnitTestCase {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Admin logs screen instance.
	 *
	 * @var Usage_Logs_Screen
	 */
	private $logs_screen;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure database table exists.
		Database::create_table();

		// Clear any existing test data.
		global $wpdb;
		$table_name = Database::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		// Initialize settings.
		$this->settings = new Settings();

		// Initialize logs screen.
		$this->logs_screen = new Usage_Logs_Screen( $this->settings );

		// Create admin user.
		$this->admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $this->admin_user_id );
	}

	/**
	 * Test menu registration.
	 *
	 * @return void
	 */
	public function test_menu_registration() {
		global $_registered_pages;

		// Trigger menu registration.
		do_action( 'admin_menu' );

		// Check if menu was registered.
		$menu_slug = 'wc-coupon-gatekeeper-logs';
		$this->assertArrayHasKey(
			'woocommerce_page_' . $menu_slug,
			$_registered_pages,
			'Admin menu should be registered'
		);
	}

	/**
	 * Test get current filters with no filters applied.
	 *
	 * @return void
	 */
	public function test_get_current_filters_empty() {
		$filters = $this->logs_screen->get_current_filters();

		$this->assertIsArray( $filters );
		$this->assertEmpty( $filters );
	}

	/**
	 * Test get current filters with month filter.
	 *
	 * @return void
	 */
	public function test_get_current_filters_with_month() {
		$_GET['filter_month'] = '2024-01';

		$filters = $this->logs_screen->get_current_filters();

		$this->assertArrayHasKey( 'month', $filters );
		$this->assertEquals( '2024-01', $filters['month'] );

		unset( $_GET['filter_month'] );
	}

	/**
	 * Test get current filters with all filters.
	 *
	 * @return void
	 */
	public function test_get_current_filters_all() {
		$_GET['filter_month']     = '2024-01';
		$_GET['filter_coupon']    = 'test27';
		$_GET['filter_customer']  = 'user:42';
		$_GET['filter_min_count'] = '1';
		$_GET['filter_max_count'] = '5';

		$filters = $this->logs_screen->get_current_filters();

		$this->assertEquals( '2024-01', $filters['month'] );
		$this->assertEquals( 'test27', $filters['coupon'] );
		$this->assertEquals( 'user:42', $filters['customer'] );
		$this->assertEquals( 1, $filters['min_count'] );
		$this->assertEquals( 5, $filters['max_count'] );

		unset( $_GET['filter_month'], $_GET['filter_coupon'], $_GET['filter_customer'], $_GET['filter_min_count'], $_GET['filter_max_count'] );
	}

	/**
	 * Test get logs data without filters.
	 *
	 * @return void
	 */
	public function test_get_logs_data_no_filters() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'vip10', 'user:42', 101, '2024-01' );
		Database::increment_usage( 'test27', 'user:99', 102, '2024-01' );

		$logs = $this->logs_screen->get_logs_data( array(), 0, 10 );

		$this->assertCount( 3, $logs );
	}

	/**
	 * Test get logs data with month filter.
	 *
	 * @return void
	 */
	public function test_get_logs_data_with_month_filter() {
		// Insert test data for different months.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'test27', 'user:42', 101, '2024-02' );
		Database::increment_usage( 'test27', 'user:42', 102, '2024-03' );

		$logs = $this->logs_screen->get_logs_data( array( 'month' => '2024-02' ), 0, 10 );

		$this->assertCount( 1, $logs );
		$this->assertEquals( '2024-02', $logs[0]->month );
	}

	/**
	 * Test get logs data with coupon filter.
	 *
	 * @return void
	 */
	public function test_get_logs_data_with_coupon_filter() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'vip10', 'user:42', 101, '2024-01' );
		Database::increment_usage( 'summer50', 'user:42', 102, '2024-01' );

		$logs = $this->logs_screen->get_logs_data( array( 'coupon' => 'vip' ), 0, 10 );

		$this->assertCount( 1, $logs );
		$this->assertEquals( 'vip10', $logs[0]->coupon_code );
	}

	/**
	 * Test get logs data with customer filter.
	 *
	 * @return void
	 */
	public function test_get_logs_data_with_customer_filter() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'test27', 'user:99', 101, '2024-01' );
		Database::increment_usage( 'test27', 'email:guest@example.com', 102, '2024-01' );

		$logs = $this->logs_screen->get_logs_data( array( 'customer' => 'user:42' ), 0, 10 );

		$this->assertCount( 1, $logs );
		$this->assertEquals( 'user:42', $logs[0]->customer_key );
	}

	/**
	 * Test get logs data with min count filter.
	 *
	 * @return void
	 */
	public function test_get_logs_data_with_min_count_filter() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'test27', 'user:42', 101, '2024-01' ); // Count = 2.
		Database::increment_usage( 'vip10', 'user:99', 102, '2024-01' ); // Count = 1.

		$logs = $this->logs_screen->get_logs_data( array( 'min_count' => 2 ), 0, 10 );

		$this->assertCount( 1, $logs );
		$this->assertEquals( 2, $logs[0]->count );
	}

	/**
	 * Test get logs data with max count filter.
	 *
	 * @return void
	 */
	public function test_get_logs_data_with_max_count_filter() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'test27', 'user:42', 101, '2024-01' );
		Database::increment_usage( 'test27', 'user:42', 102, '2024-01' ); // Count = 3.
		Database::increment_usage( 'vip10', 'user:99', 103, '2024-01' ); // Count = 1.

		$logs = $this->logs_screen->get_logs_data( array( 'max_count' => 1 ), 0, 10 );

		$this->assertCount( 1, $logs );
		$this->assertEquals( 1, $logs[0]->count );
	}

	/**
	 * Test get logs data with pagination.
	 *
	 * @return void
	 */
	public function test_get_logs_data_pagination() {
		// Insert 5 test records.
		for ( $i = 1; $i <= 5; $i++ ) {
			Database::increment_usage( "coupon{$i}", 'user:42', 100 + $i, '2024-01' );
		}

		// Get first page (2 records).
		$page1 = $this->logs_screen->get_logs_data( array(), 0, 2 );
		$this->assertCount( 2, $page1 );

		// Get second page (2 records).
		$page2 = $this->logs_screen->get_logs_data( array(), 2, 2 );
		$this->assertCount( 2, $page2 );

		// Ensure different records.
		$this->assertNotEquals( $page1[0]->id, $page2[0]->id );
	}

	/**
	 * Test get logs count without filters.
	 *
	 * @return void
	 */
	public function test_get_logs_count_no_filters() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'vip10', 'user:42', 101, '2024-01' );
		Database::increment_usage( 'test27', 'user:99', 102, '2024-01' );

		$count = $this->logs_screen->get_logs_count( array() );

		$this->assertEquals( 3, $count );
	}

	/**
	 * Test get logs count with filters.
	 *
	 * @return void
	 */
	public function test_get_logs_count_with_filters() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'test27', 'user:42', 101, '2024-02' );
		Database::increment_usage( 'vip10', 'user:42', 102, '2024-01' );

		$count = $this->logs_screen->get_logs_count( array( 'month' => '2024-01' ) );

		$this->assertEquals( 2, $count );
	}

	/**
	 * Test mask customer key for plain email.
	 *
	 * @return void
	 */
	public function test_mask_customer_key_plain() {
		$key    = 'email:customer@example.com';
		$masked = $this->logs_screen->mask_customer_key( $key );

		$this->assertEquals( 'email:customer@example.com', $masked );
	}

	/**
	 * Test mask customer key for hashed email.
	 *
	 * @return void
	 */
	public function test_mask_customer_key_hashed() {
		$key    = 'email:' . hash( 'sha256', 'test@example.com' . wp_salt() );
		$masked = $this->logs_screen->mask_customer_key( $key );

		$this->assertStringStartsWith( 'email:', $masked );
		$this->assertStringEndsWith( '...', $masked );
		$this->assertLessThan( strlen( $key ), strlen( $masked ) );
	}

	/**
	 * Test mask customer key for user ID.
	 *
	 * @return void
	 */
	public function test_mask_customer_key_user_id() {
		$key    = 'user:42';
		$masked = $this->logs_screen->mask_customer_key( $key );

		$this->assertEquals( 'user:42', $masked );
	}

	/**
	 * Test get available months returns 24 months.
	 *
	 * @return void
	 */
	public function test_get_available_months() {
		$months = $this->logs_screen->get_available_months();

		$this->assertCount( 24, $months );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}$/', $months[0] );
	}

	/**
	 * Test get available months includes current month.
	 *
	 * @return void
	 */
	public function test_get_available_months_includes_current() {
		$months = $this->logs_screen->get_available_months();
		$current_month = wp_date( 'Y-m' );

		$this->assertContains( $current_month, $months );
	}

	/**
	 * Test AJAX view customer history requires authentication.
	 *
	 * @return void
	 */
	public function test_ajax_view_history_requires_auth() {
		// Log out.
		wp_set_current_user( 0 );

		// Simulate AJAX request.
		$_POST['action']       = 'wcgk_view_customer_history';
		$_POST['nonce']        = wp_create_nonce( 'wcgk_logs_action' );
		$_POST['coupon_code']  = 'test27';
		$_POST['customer_key'] = 'user:42';

		// Capture output.
		ob_start();
		try {
			$this->logs_screen->ajax_view_customer_history();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$response = ob_get_clean();

		// Should fail.
		$this->assertStringContainsString( 'Permission denied', $response );

		// Clean up.
		unset( $_POST['action'], $_POST['nonce'], $_POST['coupon_code'], $_POST['customer_key'] );
	}

	/**
	 * Test AJAX reset usage requires authentication.
	 *
	 * @return void
	 */
	public function test_ajax_reset_usage_requires_auth() {
		// Log out.
		wp_set_current_user( 0 );

		// Simulate AJAX request.
		$_POST['action'] = 'wcgk_reset_usage';
		$_POST['nonce']  = wp_create_nonce( 'wcgk_logs_action' );
		$_POST['ids']    = array( 1 );

		// Capture output.
		ob_start();
		try {
			$this->logs_screen->ajax_reset_usage();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$response = ob_get_clean();

		// Should fail.
		$this->assertStringContainsString( 'Permission denied', $response );

		// Clean up.
		unset( $_POST['action'], $_POST['nonce'], $_POST['ids'] );
	}

	/**
	 * Test export CSV requires authentication.
	 *
	 * @return void
	 */
	public function test_export_csv_requires_auth() {
		// Log out.
		wp_set_current_user( 0 );

		// Set up request.
		$_GET['page']     = 'wc-coupon-gatekeeper-logs';
		$_GET['action']   = 'export_csv';
		$_GET['_wpnonce'] = wp_create_nonce( 'wcgk_export_csv' );

		// Expect wp_die to be called.
		$this->expectException( WPDieException::class );

		// Trigger action handler.
		do_action( 'admin_init' );

		// Clean up.
		unset( $_GET['page'], $_GET['action'], $_GET['_wpnonce'] );
	}

	/**
	 * Test combined filters work correctly.
	 *
	 * @return void
	 */
	public function test_combined_filters() {
		// Insert test data.
		Database::increment_usage( 'test27', 'user:42', 100, '2024-01' );
		Database::increment_usage( 'test27', 'user:42', 101, '2024-01' ); // Count = 2.
		Database::increment_usage( 'test27', 'user:99', 102, '2024-01' ); // Different customer.
		Database::increment_usage( 'vip10', 'user:42', 103, '2024-01' ); // Different coupon.
		Database::increment_usage( 'test27', 'user:42', 104, '2024-02' ); // Different month.

		// Filter: test27 + user:42 + 2024-01 + count >= 2.
		$filters = array(
			'coupon'    => 'test27',
			'customer'  => 'user:42',
			'month'     => '2024-01',
			'min_count' => 2,
		);

		$logs = $this->logs_screen->get_logs_data( $filters, 0, 10 );

		$this->assertCount( 1, $logs );
		$this->assertEquals( 'test27', $logs[0]->coupon_code );
		$this->assertEquals( 'user:42', $logs[0]->customer_key );
		$this->assertEquals( '2024-01', $logs[0]->month );
		$this->assertEquals( 2, $logs[0]->count );
	}

	/**
	 * Test logs are ordered by updated_at DESC.
	 *
	 * @return void
	 */
	public function test_logs_ordered_by_updated_at() {
		// Insert test data with slight delays.
		Database::increment_usage( 'coupon1', 'user:1', 100, '2024-01' );
		sleep( 1 );
		Database::increment_usage( 'coupon2', 'user:2', 101, '2024-01' );
		sleep( 1 );
		Database::increment_usage( 'coupon3', 'user:3', 102, '2024-01' );

		$logs = $this->logs_screen->get_logs_data( array(), 0, 10 );

		// Most recent should be first.
		$this->assertEquals( 'coupon3', $logs[0]->coupon_code );
		$this->assertEquals( 'coupon2', $logs[1]->coupon_code );
		$this->assertEquals( 'coupon1', $logs[2]->coupon_code );
	}
}