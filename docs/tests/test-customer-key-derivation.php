<?php
/**
 * Unit tests for customer key derivation.
 *
 * Tests various scenarios for customer identification:
 * - Logged-in users with user_id_priority
 * - Logged-in users with email_only
 * - Guest users with email
 * - Guest users without email
 * - Anonymized vs non-anonymized keys
 * - Order-based customer key generation
 *
 * @package WC_Coupon_Gatekeeper
 */

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Validator\Coupon_Validator;

/**
 * Class Test_Customer_Key_Derivation
 */
class Test_Customer_Key_Derivation extends WP_UnitTestCase {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Coupon validator instance.
	 *
	 * @var Coupon_Validator
	 */
	private $validator;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private $test_user_id;

	/**
	 * Setup test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Create settings instance.
		$this->settings = new Settings();

		// Reset settings to defaults.
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'customer_identification' => 'user_id_priority',
				'anonymize_email'         => true,
			)
		);
		$this->settings->clear_cache();

		// Create coupon validator instance.
		$this->validator = new Coupon_Validator( $this->settings );

		// Create test user.
		$this->test_user_id = $this->factory->user->create(
			array(
				'user_login' => 'testuser',
				'user_email' => 'testuser@example.com',
			)
		);
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Logout any logged-in users.
		wp_set_current_user( 0 );

		// Delete test user.
		if ( $this->test_user_id ) {
			wp_delete_user( $this->test_user_id );
		}

		parent::tearDown();
	}

	/**
	 * Test customer key for logged-in user with user_id_priority.
	 *
	 * @return void
	 */
	public function test_logged_in_user_with_user_id_priority() {
		// Set identification method to user_id_priority.
		$this->settings->update( array( 'customer_identification' => 'user_id_priority' ) );

		// Login user.
		wp_set_current_user( $this->test_user_id );

		// Get customer key using reflection (private method).
		$customer_key = $this->get_customer_key_from_validator();

		// Should return user:{ID}.
		$this->assertSame( 'user:' . $this->test_user_id, $customer_key );
	}

	/**
	 * Test customer key for logged-in user with email_only.
	 *
	 * @return void
	 */
	public function test_logged_in_user_with_email_only() {
		// Set identification method to email_only.
		$this->settings->update( array( 'customer_identification' => 'email_only' ) );

		// Login user.
		wp_set_current_user( $this->test_user_id );

		// Get customer key.
		$customer_key = $this->get_customer_key_from_validator();

		// Should return hash:... (because anonymize_email is true by default).
		$this->assertStringStartsWith( 'hash:', $customer_key );

		// Verify it's a valid SHA-256 hash.
		$hash_part = substr( $customer_key, 5 ); // Remove 'hash:' prefix.
		$this->assertSame( 64, strlen( $hash_part ), 'Hash should be 64 characters (SHA-256)' );
	}

	/**
	 * Test customer key for logged-in user with email_only and no anonymization.
	 *
	 * @return void
	 */
	public function test_logged_in_user_email_only_no_anonymization() {
		// Set identification method to email_only and disable anonymization.
		$this->settings->update(
			array(
				'customer_identification' => 'email_only',
				'anonymize_email'         => false,
			)
		);

		// Login user.
		wp_set_current_user( $this->test_user_id );

		// Get customer key.
		$customer_key = $this->get_customer_key_from_validator();

		// Should return email:testuser@example.com.
		$this->assertSame( 'email:testuser@example.com', $customer_key );
	}

	/**
	 * Test customer key consistency for same user.
	 *
	 * @return void
	 */
	public function test_customer_key_consistency() {
		// Set settings.
		$this->settings->update(
			array(
				'customer_identification' => 'user_id_priority',
				'anonymize_email'         => true,
			)
		);

		// Login user.
		wp_set_current_user( $this->test_user_id );

		// Get customer key twice.
		$key1 = $this->get_customer_key_from_validator();
		$key2 = $this->get_customer_key_from_validator();

		// Should be identical.
		$this->assertSame( $key1, $key2 );
	}

	/**
	 * Test customer key from order object with user ID.
	 *
	 * @return void
	 */
	public function test_customer_key_from_order_with_user_id() {
		// Set identification method to user_id_priority.
		$this->settings->update( array( 'customer_identification' => 'user_id_priority' ) );

		// Create mock order.
		$order = $this->create_mock_order( $this->test_user_id, 'testuser@example.com' );

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );

		// Should return user:{ID}.
		$this->assertSame( 'user:' . $this->test_user_id, $customer_key );
	}

	/**
	 * Test customer key from order object without user ID (guest).
	 *
	 * @return void
	 */
	public function test_customer_key_from_order_guest() {
		// Set identification method to user_id_priority with anonymization.
		$this->settings->update(
			array(
				'customer_identification' => 'user_id_priority',
				'anonymize_email'         => true,
			)
		);

		// Create mock order without user ID (guest).
		$order = $this->create_mock_order( 0, 'guest@example.com' );

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );

		// Should return hash:... (anonymized email).
		$this->assertStringStartsWith( 'hash:', $customer_key );
	}

	/**
	 * Test customer key from order with email_only mode.
	 *
	 * @return void
	 */
	public function test_customer_key_from_order_email_only() {
		// Set identification method to email_only without anonymization.
		$this->settings->update(
			array(
				'customer_identification' => 'email_only',
				'anonymize_email'         => false,
			)
		);

		// Create mock order with user ID (should still use email).
		$order = $this->create_mock_order( $this->test_user_id, 'testuser@example.com' );

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );

		// Should return email:testuser@example.com (ignores user ID).
		$this->assertSame( 'email:testuser@example.com', $customer_key );
	}

	/**
	 * Test anonymized customer key is deterministic.
	 *
	 * @return void
	 */
	public function test_anonymized_key_is_deterministic() {
		// Enable anonymization.
		$this->settings->update(
			array(
				'customer_identification' => 'email_only',
				'anonymize_email'         => true,
			)
		);

		// Create two orders with same email.
		$order1 = $this->create_mock_order( 0, 'same@example.com' );
		$order2 = $this->create_mock_order( 0, 'same@example.com' );

		// Get customer keys.
		$key1 = $this->get_customer_key_from_order( $order1 );
		$key2 = $this->get_customer_key_from_order( $order2 );

		// Should be identical (same email = same hash).
		$this->assertSame( $key1, $key2 );
	}

	/**
	 * Test anonymized customer key is different for different emails.
	 *
	 * @return void
	 */
	public function test_anonymized_key_different_emails() {
		// Enable anonymization.
		$this->settings->update(
			array(
				'customer_identification' => 'email_only',
				'anonymize_email'         => true,
			)
		);

		// Create two orders with different emails.
		$order1 = $this->create_mock_order( 0, 'email1@example.com' );
		$order2 = $this->create_mock_order( 0, 'email2@example.com' );

		// Get customer keys.
		$key1 = $this->get_customer_key_from_order( $order1 );
		$key2 = $this->get_customer_key_from_order( $order2 );

		// Should be different.
		$this->assertNotSame( $key1, $key2 );
	}

	/**
	 * Test customer key with email case normalization.
	 *
	 * @return void
	 */
	public function test_email_case_normalization() {
		// Disable anonymization for clear comparison.
		$this->settings->update(
			array(
				'customer_identification' => 'email_only',
				'anonymize_email'         => false,
			)
		);

		// Create orders with different email cases.
		$order1 = $this->create_mock_order( 0, 'TEST@EXAMPLE.COM' );
		$order2 = $this->create_mock_order( 0, 'test@example.com' );
		$order3 = $this->create_mock_order( 0, 'TeSt@ExAmPlE.cOm' );

		// Get customer keys.
		$key1 = $this->get_customer_key_from_order( $order1 );
		$key2 = $this->get_customer_key_from_order( $order2 );
		$key3 = $this->get_customer_key_from_order( $order3 );

		// All should be lowercase and identical.
		$this->assertSame( 'email:test@example.com', $key1 );
		$this->assertSame( 'email:test@example.com', $key2 );
		$this->assertSame( 'email:test@example.com', $key3 );
		$this->assertSame( $key1, $key2 );
		$this->assertSame( $key2, $key3 );
	}

	/**
	 * Test customer key fallback when no email or user ID.
	 *
	 * @return void
	 */
	public function test_customer_key_fallback_no_email_no_user() {
		// Create order with no user ID and no email (edge case).
		$order = $this->create_mock_order( 0, '' );

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );

		// Should return empty string.
		$this->assertSame( '', $customer_key );
	}

	/**
	 * Test customer key priority: user_id_priority prefers user ID over email.
	 *
	 * @return void
	 */
	public function test_customer_key_priority_user_id_over_email() {
		// Set to user_id_priority.
		$this->settings->update( array( 'customer_identification' => 'user_id_priority' ) );

		// Create order with both user ID and email.
		$order = $this->create_mock_order( 123, 'user123@example.com' );

		// Get customer key from order.
		$customer_key = $this->get_customer_key_from_order( $order );

		// Should prefer user ID.
		$this->assertSame( 'user:123', $customer_key );
	}

	/**
	 * Test switching between identification methods.
	 *
	 * @return void
	 */
	public function test_switching_identification_methods() {
		$order = $this->create_mock_order( $this->test_user_id, 'test@example.com' );

		// Test with user_id_priority.
		$this->settings->update( array( 'customer_identification' => 'user_id_priority' ) );
		$key1 = $this->get_customer_key_from_order( $order );
		$this->assertSame( 'user:' . $this->test_user_id, $key1 );

		// Switch to email_only with no anonymization.
		$this->settings->update(
			array(
				'customer_identification' => 'email_only',
				'anonymize_email'         => false,
			)
		);
		$key2 = $this->get_customer_key_from_order( $order );
		$this->assertSame( 'email:test@example.com', $key2 );

		// Keys should be different.
		$this->assertNotSame( $key1, $key2 );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Get customer key from validator using reflection.
	 *
	 * @return string
	 */
	private function get_customer_key_from_validator() {
		$reflection = new ReflectionClass( $this->validator );
		$method     = $reflection->getMethod( 'get_customer_key' );
		$method->setAccessible( true );
		return $method->invoke( $this->validator );
	}

	/**
	 * Get customer key from order using reflection.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function get_customer_key_from_order( $order ) {
		$reflection = new ReflectionClass( $this->validator );
		$method     = $reflection->getMethod( 'get_customer_key_from_order' );
		$method->setAccessible( true );
		return $method->invoke( $this->validator, $order );
	}

	/**
	 * Create mock WooCommerce order.
	 *
	 * @param int    $user_id User ID (0 for guest).
	 * @param string $email   Billing email.
	 * @return WC_Order
	 */
	private function create_mock_order( $user_id, $email ) {
		$order = $this->getMockBuilder( 'WC_Order' )
						->disableOriginalConstructor()
						->getMock();

		$order->method( 'get_user_id' )->willReturn( $user_id );
		$order->method( 'get_billing_email' )->willReturn( $email );

		return $order;
	}
}