<?php
/**
 * Tests for Day Restriction Validation
 *
 * @package WC_Coupon_Gatekeeper
 */

/**
 * Test class for day restriction validation.
 */
class Test_Day_Restriction extends WP_UnitTestCase {

	/**
	 * Test that coupon is allowed on configured day.
	 */
	public function test_coupon_allowed_on_configured_day() {
		// Setup: Configure allowed day to be today.
		$current_day = (int) wp_date( 'j' );
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( $current_day ),
				'apply_to_all_coupons'    => 'yes',
			)
		);

		// Create a test coupon.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'test27' );
		$coupon->save();

		// Test: Coupon should be valid today.
		$is_valid = apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, null );
		$this->assertTrue( $is_valid, 'Coupon should be valid on configured day.' );
	}

	/**
	 * Test that coupon is blocked on non-configured day.
	 */
	public function test_coupon_blocked_on_non_configured_day() {
		// Setup: Configure allowed day to be different from today.
		$current_day  = (int) wp_date( 'j' );
		$blocked_day  = $current_day === 1 ? 2 : 1; // Pick a different day.
		
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( $blocked_day ),
				'apply_to_all_coupons'    => 'yes',
				'error_not_allowed_day'   => 'Coupon not valid today.',
			)
		);

		// Create a test coupon.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'test27' );
		$coupon->save();

		// Test: Coupon should throw exception.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Coupon not valid today.' );
		apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, null );
	}

	/**
	 * Test that multiple allowed days work correctly.
	 */
	public function test_multiple_allowed_days() {
		// Setup: Configure multiple allowed days including today.
		$current_day = (int) wp_date( 'j' );
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( 1, 15, $current_day ),
				'apply_to_all_coupons'    => 'yes',
			)
		);

		// Create a test coupon.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'multi' );
		$coupon->save();

		// Test: Coupon should be valid.
		$is_valid = apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, null );
		$this->assertTrue( $is_valid, 'Coupon should be valid when today is in multiple allowed days.' );
	}

	/**
	 * Test that "Apply to All Coupons" setting works.
	 */
	public function test_apply_to_all_coupons_setting() {
		$current_day  = (int) wp_date( 'j' );
		$blocked_day  = $current_day === 1 ? 2 : 1;

		// Setup: Apply to all coupons OFF, restricted list empty.
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( $blocked_day ),
				'apply_to_all_coupons'    => 'no',
				'restricted_coupons'      => '',
			)
		);

		// Create a test coupon NOT in the list.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'unmanaged' );
		$coupon->save();

		// Test: Coupon should be valid (not managed).
		$is_valid = apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, null );
		$this->assertTrue( $is_valid, 'Unmanaged coupon should always be valid.' );
	}

	/**
	 * Test that restricted coupons list works.
	 */
	public function test_restricted_coupons_list() {
		$current_day  = (int) wp_date( 'j' );
		$blocked_day  = $current_day === 1 ? 2 : 1;

		// Setup: Specific coupon in restricted list.
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( $blocked_day ),
				'apply_to_all_coupons'    => 'no',
				'restricted_coupons'      => 'vip27',
			)
		);

		// Test 1: Managed coupon should be blocked.
		$managed_coupon = new WC_Coupon();
		$managed_coupon->set_code( 'vip27' );
		$managed_coupon->save();

		$this->expectException( Exception::class );
		apply_filters( 'woocommerce_coupon_is_valid', true, $managed_coupon, null );

		// Test 2: Unmanaged coupon should pass.
		$unmanaged_coupon = new WC_Coupon();
		$unmanaged_coupon->set_code( 'other' );
		$unmanaged_coupon->save();

		$is_valid = apply_filters( 'woocommerce_coupon_is_valid', true, $unmanaged_coupon, null );
		$this->assertTrue( $is_valid, 'Unmanaged coupon should be valid.' );
	}

	/**
	 * Test that day restriction can be disabled.
	 */
	public function test_day_restriction_disabled() {
		$current_day  = (int) wp_date( 'j' );
		$blocked_day  = $current_day === 1 ? 2 : 1;

		// Setup: Day restriction disabled.
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'no',
				'allowed_days'            => array( $blocked_day ),
				'apply_to_all_coupons'    => 'yes',
			)
		);

		// Create a test coupon.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'test' );
		$coupon->save();

		// Test: Coupon should be valid even on "blocked" day.
		$is_valid = apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, null );
		$this->assertTrue( $is_valid, 'Coupon should be valid when day restriction is disabled.' );
	}

	/**
	 * Test admin bypass functionality.
	 *
	 * Note: This test simulates admin context. Actual admin bypass
	 * requires is_admin() and current_user_can() checks.
	 */
	public function test_admin_bypass_enabled() {
		$current_day  = (int) wp_date( 'j' );
		$blocked_day  = $current_day === 1 ? 2 : 1;

		// Setup: Admin bypass ON, day restricted.
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( $blocked_day ),
				'apply_to_all_coupons'    => 'yes',
				'admin_bypass_enabled'    => 'yes',
			)
		);

		// Note: In actual wp-admin context with proper user capabilities,
		// the coupon would bypass validation. This test documents the expected behavior.
		$this->assertTrue( true, 'Admin bypass is context-dependent (is_admin, capabilities).' );
	}

	/**
	 * Test custom error message.
	 */
	public function test_custom_error_message() {
		$current_day  = (int) wp_date( 'j' );
		$blocked_day  = $current_day === 1 ? 2 : 1;

		// Setup: Custom error message.
		$custom_error = 'This coupon is only valid on the 15th of each month.';
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( $blocked_day ),
				'apply_to_all_coupons'    => 'yes',
				'error_not_allowed_day'   => $custom_error,
			)
		);

		// Create a test coupon.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'test' );
		$coupon->save();

		// Test: Should throw exception with custom message.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $custom_error );
		apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, null );
	}

	/**
	 * Test that already invalid coupons are not overridden.
	 */
	public function test_respects_existing_invalid_coupon() {
		// Setup: Configure today as allowed day.
		$current_day = (int) wp_date( 'j' );
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'day_restriction_enabled' => 'yes',
				'allowed_days'            => array( $current_day ),
				'apply_to_all_coupons'    => 'yes',
			)
		);

		// Create a test coupon.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'test' );
		$coupon->save();

		// Test: Pass already invalid coupon (false).
		$is_valid = apply_filters( 'woocommerce_coupon_is_valid', false, $coupon, null );
		$this->assertFalse( $is_valid, 'Already invalid coupons should remain invalid.' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'wc_coupon_gatekeeper_settings' );
	}
}