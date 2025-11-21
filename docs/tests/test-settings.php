<?php
/**
 * Tests for Settings class.
 *
 * @package WC_Coupon_Gatekeeper
 */

/**
 * Test Settings functionality.
 */
class Test_Settings extends WP_UnitTestCase {

	/**
	 * Settings instance.
	 *
	 * @var \WC_Coupon_Gatekeeper\Settings
	 */
	private $settings;

	/**
	 * Setup test environment.
	 */
	public function setUp() {
		parent::setUp();
		$this->settings = new \WC_Coupon_Gatekeeper\Settings();
		
		// Clean up options.
		delete_option( 'wc_coupon_gatekeeper_settings' );
	}

	/**
	 * Test default settings.
	 */
	public function test_default_settings() {
		$this->assertTrue( $this->settings->is_day_restriction_enabled() );
		$this->assertTrue( $this->settings->is_monthly_limit_enabled() );
		$this->assertFalse( $this->settings->apply_to_all_coupons() );
		$this->assertEquals( array( 27 ), $this->settings->get_allowed_days() );
		$this->assertEquals( 1, $this->settings->get_default_monthly_limit() );
		$this->assertTrue( $this->settings->is_email_anonymization_enabled() );
		$this->assertEquals( 18, $this->settings->get_log_retention_months() );
	}

	/**
	 * Test coupon management check.
	 */
	public function test_is_coupon_managed() {
		// With apply_to_all_coupons = false and empty list.
		$this->assertFalse( $this->settings->is_coupon_managed( 'testcode' ) );

		// With restricted coupons.
		$this->settings->update( array(
			'restricted_coupons' => array( '27off', 'vip27' ),
		) );
		$this->settings->clear_cache();

		$this->assertTrue( $this->settings->is_coupon_managed( '27off' ) );
		$this->assertTrue( $this->settings->is_coupon_managed( '27OFF' ) ); // Case insensitive.
		$this->assertTrue( $this->settings->is_coupon_managed( 'vip27' ) );
		$this->assertFalse( $this->settings->is_coupon_managed( 'other' ) );

		// With apply_to_all_coupons = true.
		$this->settings->update( array( 'apply_to_all_coupons' => true ) );
		$this->settings->clear_cache();

		$this->assertTrue( $this->settings->is_coupon_managed( 'any_coupon' ) );
		$this->assertTrue( $this->settings->is_coupon_managed( 'random123' ) );
	}

	/**
	 * Test per-coupon limit overrides.
	 */
	public function test_coupon_limit_overrides() {
		$this->settings->update( array(
			'default_monthly_limit'  => 1,
			'coupon_limit_overrides' => array(
				'vip27'   => 5,
				'special' => 10,
			),
		) );
		$this->settings->clear_cache();

		$this->assertEquals( 5, $this->settings->get_monthly_limit_for_coupon( 'vip27' ) );
		$this->assertEquals( 5, $this->settings->get_monthly_limit_for_coupon( 'VIP27' ) ); // Case insensitive.
		$this->assertEquals( 10, $this->settings->get_monthly_limit_for_coupon( 'special' ) );
		$this->assertEquals( 1, $this->settings->get_monthly_limit_for_coupon( 'other' ) ); // Default.
	}

	/**
	 * Test customer identification methods.
	 */
	public function test_customer_identification() {
		$this->assertEquals( 'user_id_priority', $this->settings->get_customer_identification() );

		$this->settings->update( array( 'customer_identification' => 'email_only' ) );
		$this->settings->clear_cache();

		$this->assertEquals( 'email_only', $this->settings->get_customer_identification() );
	}

	/**
	 * Test error messages.
	 */
	public function test_error_messages() {
		$day_error   = $this->settings->get_error_not_allowed_day();
		$limit_error = $this->settings->get_error_limit_reached();

		$this->assertNotEmpty( $day_error );
		$this->assertNotEmpty( $limit_error );

		// Test custom messages.
		$this->settings->update( array(
			'error_not_allowed_day' => 'Custom day error',
			'error_limit_reached'   => 'Custom limit error',
		) );
		$this->settings->clear_cache();

		$this->assertEquals( 'Custom day error', $this->settings->get_error_not_allowed_day() );
		$this->assertEquals( 'Custom limit error', $this->settings->get_error_limit_reached() );
	}

	/**
	 * Test order statuses.
	 */
	public function test_order_statuses() {
		$count_statuses = $this->settings->get_count_usage_statuses();
		$this->assertContains( 'processing', $count_statuses );
		$this->assertContains( 'completed', $count_statuses );

		$decrement_statuses = $this->settings->get_decrement_usage_statuses();
		$this->assertContains( 'cancelled', $decrement_statuses );
		$this->assertContains( 'refunded', $decrement_statuses );
	}

	/**
	 * Cleanup after tests.
	 */
	public function tearDown() {
		delete_option( 'wc_coupon_gatekeeper_settings' );
		parent::tearDown();
	}
}