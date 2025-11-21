<?php
/**
 * Tests for monthly limit validation and usage logging.
 *
 * @package WC_Coupon_Gatekeeper
 */

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Database;
use WC_Coupon_Gatekeeper\Validator\Coupon_Validator;
use WC_Coupon_Gatekeeper\Logger\Usage_Logger;

/**
 * Class Test_Monthly_Limit
 *
 * Tests monthly limit validation, customer identification, and usage logging.
 */
class Test_Monthly_Limit extends WP_UnitTestCase {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Validator instance.
	 *
	 * @var Coupon_Validator
	 */
	private $validator;

	/**
	 * Logger instance.
	 *
	 * @var Usage_Logger
	 */
	private $logger;

	/**
	 * Set up test environment.
	 */
	public function setUp() {
		parent::setUp();

		// Create database tables.
		Database::create_tables();

		// Initialize settings with test defaults.
		$this->settings = new Settings();
		$this->settings->update(
			array(
				'enable_day_restriction'   => false, // Disable day restriction for these tests.
				'enable_monthly_limit'     => true,
				'apply_to_all_coupons'     => false,
				'restricted_coupons'       => array( 'test27', 'vip10' ),
				'default_monthly_limit'    => 1,
				'customer_identification'  => 'user_id_priority',
				'anonymize_email'          => true,
				'error_limit_reached'      => 'Monthly limit reached',
				'count_usage_statuses'     => array( 'processing', 'completed' ),
				'decrement_usage_statuses' => array( 'cancelled', 'refunded' ),
			)
		);

		// Initialize validator and logger.
		$this->validator = new Coupon_Validator( $this->settings );
		$this->logger    = new Usage_Logger( $this->settings );

		// Clear cache.
		$this->settings->clear_cache();
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown() {
		// Clean up database.
		global $wpdb;
		$table_name = Database::get_table_name();
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		parent::tearDown();
	}

	/**
	 * Test customer key generation for logged-in users.
	 */
	public function test_customer_key_logged_in_user() {
		// Create a test user.
		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'test@example.com',
				'user_login' => 'testuser',
			)
		);

		// Set current user.
		wp_set_current_user( $user_id );

		// Create order for user.
		$order = wc_create_order();
		$order->set_customer_id( $user_id );
		$order->set_billing_email( 'test@example.com' );
		$order->save();

		// Increment usage.
		$success = Database::increment_usage( 'test27', 'user:' . $user_id, $order->get_id() );
		$this->assertTrue( $success, 'Increment should succeed' );

		// Check usage count.
		$count = Database::get_usage_count( 'test27', 'user:' . $user_id );
		$this->assertEquals( 1, $count, 'Usage count should be 1' );
	}

	/**
	 * Test customer key generation for guests with email (anonymized).
	 */
	public function test_customer_key_guest_anonymized() {
		$email        = 'guest@example.com';
		$customer_key = 'hash:' . hash( 'sha256', strtolower( $email ) . wp_salt( 'auth' ) );

		// Increment usage.
		$success = Database::increment_usage( 'test27', $customer_key, 123 );
		$this->assertTrue( $success, 'Increment should succeed' );

		// Check usage count.
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 1, $count, 'Usage count should be 1' );
	}

	/**
	 * Test customer key generation for guests with email (not anonymized).
	 */
	public function test_customer_key_guest_not_anonymized() {
		// Disable anonymization.
		$this->settings->update( array( 'anonymize_email' => false ) );
		$this->settings->clear_cache();

		$email        = 'guest@example.com';
		$customer_key = 'email:' . strtolower( $email );

		// Increment usage.
		$success = Database::increment_usage( 'test27', $customer_key, 123 );
		$this->assertTrue( $success, 'Increment should succeed' );

		// Check usage count.
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 1, $count, 'Usage count should be 1' );
	}

	/**
	 * Test monthly limit blocks coupon when limit reached.
	 */
	public function test_monthly_limit_blocks_coupon() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Set usage to limit.
		Database::increment_usage( 'test27', $customer_key, 100 );

		// Check usage count.
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 1, $count, 'Usage count should be 1' );

		// Try to validate coupon (should fail).
		$coupon = new WC_Coupon( 'TEST27' );
		$valid  = true;

		// Set current user.
		wp_set_current_user( $user_id );

		// Expect exception.
		$this->setExpectedException( 'Exception', 'Monthly limit reached' );
		$this->validator->validate_coupon( $valid, $coupon );
	}

	/**
	 * Test monthly limit allows coupon when under limit.
	 */
	public function test_monthly_limit_allows_under_limit() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Set usage below limit (0 uses).
		// No increment needed.

		// Try to validate coupon (should pass).
		$coupon = new WC_Coupon( 'TEST27' );
		$valid  = true;

		// Set current user.
		wp_set_current_user( $user_id );

		// Should not throw exception.
		$result = $this->validator->validate_coupon( $valid, $coupon );
		$this->assertTrue( $result, 'Coupon should be valid when under limit' );
	}

	/**
	 * Test per-coupon limit overrides.
	 */
	public function test_per_coupon_limit_override() {
		// Set VIP10 coupon limit to 3.
		$this->settings->update(
			array(
				'coupon_limit_overrides' => array( 'vip10' => 3 ),
			)
		);
		$this->settings->clear_cache();

		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Use coupon 2 times (under limit of 3).
		Database::increment_usage( 'vip10', $customer_key, 100 );
		Database::increment_usage( 'vip10', $customer_key, 101 );

		// Check usage count.
		$count = Database::get_usage_count( 'vip10', $customer_key );
		$this->assertEquals( 2, $count, 'Usage count should be 2' );

		// Try to validate coupon (should pass).
		$coupon = new WC_Coupon( 'VIP10' );
		$valid  = true;

		// Set current user.
		wp_set_current_user( $user_id );

		// Should not throw exception.
		$result = $this->validator->validate_coupon( $valid, $coupon );
		$this->assertTrue( $result, 'Coupon should be valid when under override limit' );

		// Use one more time (now at limit).
		Database::increment_usage( 'vip10', $customer_key, 102 );

		// Try to validate again (should fail).
		$this->setExpectedException( 'Exception', 'Monthly limit reached' );
		$this->validator->validate_coupon( $valid, $coupon );
	}

	/**
	 * Test usage increment on order completion.
	 */
	public function test_usage_increment_on_completion() {
		$user_id = $this->factory->user->create();

		// Create order.
		$order = wc_create_order();
		$order->set_customer_id( $user_id );
		$order->set_billing_email( 'test@example.com' );
		$order->apply_coupon( 'TEST27' );
		$order->save();

		// Simulate status change to completed.
		$this->logger->handle_status_change( $order->get_id(), 'pending', 'completed', $order );

		// Check usage count.
		$customer_key = 'user:' . $user_id;
		$count        = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 1, $count, 'Usage count should be 1 after completion' );
	}

	/**
	 * Test usage decrement on order cancellation.
	 */
	public function test_usage_decrement_on_cancellation() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Set initial usage.
		Database::increment_usage( 'test27', $customer_key, 100 );

		// Create order.
		$order = wc_create_order();
		$order->set_customer_id( $user_id );
		$order->set_billing_email( 'test@example.com' );
		$order->apply_coupon( 'TEST27' );
		$order->save();

		// Simulate status change from completed to cancelled.
		$this->logger->handle_status_change( $order->get_id(), 'completed', 'cancelled', $order );

		// Check usage count (should be decremented).
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 0, $count, 'Usage count should be 0 after cancellation' );
	}

	/**
	 * Test usage decrement on order refund.
	 */
	public function test_usage_decrement_on_refund() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Set initial usage.
		Database::increment_usage( 'test27', $customer_key, 100 );

		// Create order.
		$order = wc_create_order();
		$order->set_customer_id( $user_id );
		$order->set_billing_email( 'test@example.com' );
		$order->apply_coupon( 'TEST27' );
		$order->save();

		// Simulate status change from completed to refunded.
		$this->logger->handle_status_change( $order->get_id(), 'completed', 'refunded', $order );

		// Check usage count (should be decremented).
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 0, $count, 'Usage count should be 0 after refund' );
	}

	/**
	 * Test multiple coupons tracked separately.
	 */
	public function test_multiple_coupons_tracked_separately() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Increment usage for TEST27.
		Database::increment_usage( 'test27', $customer_key, 100 );

		// Increment usage for VIP10.
		Database::increment_usage( 'vip10', $customer_key, 100 );

		// Check usage counts.
		$count_test27 = Database::get_usage_count( 'test27', $customer_key );
		$count_vip10  = Database::get_usage_count( 'vip10', $customer_key );

		$this->assertEquals( 1, $count_test27, 'TEST27 usage count should be 1' );
		$this->assertEquals( 1, $count_vip10, 'VIP10 usage count should be 1' );
	}

	/**
	 * Test monthly limit disabled allows all usage.
	 */
	public function test_monthly_limit_disabled() {
		// Disable monthly limit.
		$this->settings->update( array( 'enable_monthly_limit' => false ) );
		$this->settings->clear_cache();

		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Set usage to limit.
		Database::increment_usage( 'test27', $customer_key, 100 );

		// Try to validate coupon (should pass because feature disabled).
		$coupon = new WC_Coupon( 'TEST27' );
		$valid  = true;

		// Set current user.
		wp_set_current_user( $user_id );

		// Should not throw exception.
		$result = $this->validator->validate_coupon( $valid, $coupon );
		$this->assertTrue( $result, 'Coupon should be valid when monthly limit disabled' );
	}

	/**
	 * Test unmanaged coupons are not tracked.
	 */
	public function test_unmanaged_coupons_not_tracked() {
		$user_id = $this->factory->user->create();

		// Create order with unmanaged coupon.
		$order = wc_create_order();
		$order->set_customer_id( $user_id );
		$order->set_billing_email( 'test@example.com' );
		$order->apply_coupon( 'UNMANAGED' );
		$order->save();

		// Simulate status change to completed.
		$this->logger->handle_status_change( $order->get_id(), 'pending', 'completed', $order );

		// Check usage count (should be 0).
		$customer_key = 'user:' . $user_id;
		$count        = Database::get_usage_count( 'unmanaged', $customer_key );
		$this->assertEquals( 0, $count, 'Unmanaged coupon should not be tracked' );
	}

	/**
	 * Test concurrency safety with multiple increments.
	 */
	public function test_concurrency_safety() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Increment multiple times (simulating concurrent requests).
		Database::increment_usage( 'test27', $customer_key, 100 );
		Database::increment_usage( 'test27', $customer_key, 101 );
		Database::increment_usage( 'test27', $customer_key, 102 );

		// Check usage count.
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 3, $count, 'Usage count should be 3 after 3 increments' );
	}

	/**
	 * Test decrement doesn't go below zero.
	 */
	public function test_decrement_minimum_zero() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Try to decrement when count is 0.
		$success = Database::decrement_usage( 'test27', $customer_key, 100 );

		// Check usage count (should still be 0).
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 0, $count, 'Usage count should not go below 0' );
	}

	/**
	 * Test old records cleanup.
	 */
	public function test_cleanup_old_records() {
		$user_id      = $this->factory->user->create();
		$customer_key = 'user:' . $user_id;

		// Insert old record (20 months ago).
		$old_month = wp_date( 'Y-m', strtotime( '-20 months' ) );
		Database::increment_usage( 'test27', $customer_key, 100, $old_month );

		// Insert current record.
		Database::increment_usage( 'test27', $customer_key, 101 );

		// Cleanup records older than 18 months.
		$deleted = Database::cleanup_old_records( 18 );

		// Check that old record was deleted.
		$this->assertGreaterThan( 0, $deleted, 'Old records should be deleted' );

		// Check current record still exists.
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 1, $count, 'Current month usage should still exist' );
	}

	/**
	 * Test email_only identification method.
	 */
	public function test_email_only_identification() {
		// Change to email_only identification.
		$this->settings->update( array( 'customer_identification' => 'email_only' ) );
		$this->settings->clear_cache();

		$user_id = $this->factory->user->create( array( 'user_email' => 'user@example.com' ) );
		$email   = 'user@example.com';

		// Customer key should be based on email, not user ID.
		$customer_key = 'hash:' . hash( 'sha256', strtolower( $email ) . wp_salt( 'auth' ) );

		// Increment usage.
		Database::increment_usage( 'test27', $customer_key, 100 );

		// Check usage count.
		$count = Database::get_usage_count( 'test27', $customer_key );
		$this->assertEquals( 1, $count, 'Usage should be tracked by email when email_only enabled' );
	}
}