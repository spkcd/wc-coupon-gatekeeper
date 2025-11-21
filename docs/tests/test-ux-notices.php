<?php
/**
 * Tests for UX notices in WC Coupon Gatekeeper.
 *
 * @package WC_Coupon_Gatekeeper
 */

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Validator\Coupon_Validator;
use WC_Coupon_Gatekeeper\Database;

/**
 * Class Test_UX_Notices
 *
 * Tests for checkout/cart notices and UX messaging.
 */
class Test_UX_Notices extends WP_UnitTestCase {

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
	 * Test coupon code.
	 *
	 * @var string
	 */
	private $test_coupon = 'test27';

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize settings.
		$this->settings = new Settings();

		// Configure test settings.
		$this->settings->update(
			array(
				'enable_day_restriction' => true,
				'enable_monthly_limit'   => true,
				'apply_to_all_coupons'   => false,
				'restricted_coupons'     => array( $this->test_coupon ),
				'allowed_days'           => array( 27 ),
				'use_last_valid_day'     => false,
				'default_monthly_limit'  => 1,
				'enable_success_message' => false,
				'success_message'        => 'Nice timing! This coupon is valid today.',
			)
		);

		$this->settings->clear_cache();

		// Initialize validator.
		$this->validator = new Coupon_Validator( $this->settings );

		// Clear WooCommerce notices.
		wc_clear_notices();
	}

	/**
	 * Tear down after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wc_clear_notices();
		parent::tearDown();
	}

	/**
	 * Test settings defaults for success message.
	 *
	 * @test
	 */
	public function test_success_message_settings_defaults() {
		$settings = new Settings();
		$all      = $settings->get_all();

		$this->assertArrayHasKey( 'enable_success_message', $all );
		$this->assertArrayHasKey( 'success_message', $all );
		$this->assertFalse( $all['enable_success_message'] );
		$this->assertNotEmpty( $all['success_message'] );
	}

	/**
	 * Test success message enabled getter.
	 *
	 * @test
	 */
	public function test_is_success_message_enabled() {
		$this->assertFalse( $this->settings->is_success_message_enabled() );

		$this->settings->update( array( 'enable_success_message' => true ) );
		$this->settings->clear_cache();

		$this->assertTrue( $this->settings->is_success_message_enabled() );
	}

	/**
	 * Test success message getter.
	 *
	 * @test
	 */
	public function test_get_success_message() {
		$message = $this->settings->get_success_message();
		$this->assertNotEmpty( $message );
		$this->assertStringContainsString( 'timing', strtolower( $message ) );
	}

	/**
	 * Test custom success message.
	 *
	 * @test
	 */
	public function test_custom_success_message() {
		$custom_message = 'Congratulations! Your coupon is active.';
		
		$this->settings->update( array( 'success_message' => $custom_message ) );
		$this->settings->clear_cache();

		$this->assertEquals( $custom_message, $this->settings->get_success_message() );
	}

	/**
	 * Test no success notice when disabled.
	 *
	 * @test
	 */
	public function test_no_success_notice_when_disabled() {
		// Mock today as the 27th.
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 27;
			}
		);

		$coupon = $this->create_test_coupon();
		
		try {
			$result = $this->validator->validate_coupon( true, $coupon, null );
			$this->assertTrue( $result );
		} catch ( Exception $e ) {
			$this->fail( 'Validation should pass on allowed day: ' . $e->getMessage() );
		}

		// Should have no success notice because it's disabled.
		$notices = wc_get_notices( 'success' );
		$this->assertEmpty( $notices, 'Should have no success notices when disabled' );

		remove_all_filters( 'wcgk_current_day_override' );
	}

	/**
	 * Test success notice shown when enabled.
	 *
	 * @test
	 */
	public function test_success_notice_shown_when_enabled() {
		// Enable success message.
		$this->settings->update( array( 'enable_success_message' => true ) );
		$this->settings->clear_cache();

		// Recreate validator with updated settings.
		$this->validator = new Coupon_Validator( $this->settings );

		// Mock today as the 27th.
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 27;
			}
		);

		$coupon = $this->create_test_coupon();
		
		try {
			$result = $this->validator->validate_coupon( true, $coupon, null );
			$this->assertTrue( $result );
		} catch ( Exception $e ) {
			$this->fail( 'Validation should pass on allowed day: ' . $e->getMessage() );
		}

		// Should have success notice.
		$notices = wc_get_notices( 'success' );
		$this->assertNotEmpty( $notices, 'Should have success notice when enabled' );
		$this->assertStringContainsString( 'timing', strtolower( $notices[0]['notice'] ) );

		remove_all_filters( 'wcgk_current_day_override' );
	}

	/**
	 * Test fallback day notice shown.
	 *
	 * @test
	 */
	public function test_fallback_day_notice_shown() {
		// Enable "Use Last Valid Day" setting.
		$this->settings->update(
			array(
				'allowed_days'       => array( 31 ), // Day that doesn't exist in February.
				'use_last_valid_day' => true,
			)
		);
		$this->settings->clear_cache();

		// Recreate validator with updated settings.
		$this->validator = new Coupon_Validator( $this->settings );

		// Mock today as Feb 28 (last day of February in non-leap year).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 28;
			}
		);

		add_filter(
			'wcgk_current_month_override',
			function () {
				return 2; // February
			}
		);

		add_filter(
			'wcgk_current_year_override',
			function () {
				return 2023; // Non-leap year
			}
		);

		$coupon = $this->create_test_coupon();
		
		try {
			$result = $this->validator->validate_coupon( true, $coupon, null );
			$this->assertTrue( $result );
		} catch ( Exception $e ) {
			$this->fail( 'Validation should pass on fallback day: ' . $e->getMessage() );
		}

		// Should have notice-type message about fallback.
		$notices = wc_get_notices( 'notice' );
		$this->assertNotEmpty( $notices, 'Should have fallback notice' );
		$this->assertStringContainsString( 'doesn\'t occur', strtolower( $notices[0]['notice'] ) );

		remove_all_filters( 'wcgk_current_day_override' );
		remove_all_filters( 'wcgk_current_month_override' );
		remove_all_filters( 'wcgk_current_year_override' );
	}

	/**
	 * Test fallback notice takes precedence over success notice.
	 *
	 * @test
	 */
	public function test_fallback_notice_takes_precedence() {
		// Enable both success message and use last valid day.
		$this->settings->update(
			array(
				'allowed_days'           => array( 31 ),
				'use_last_valid_day'     => true,
				'enable_success_message' => true,
			)
		);
		$this->settings->clear_cache();

		// Recreate validator with updated settings.
		$this->validator = new Coupon_Validator( $this->settings );

		// Mock today as Feb 28 (fallback day).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 28;
			}
		);

		add_filter(
			'wcgk_current_month_override',
			function () {
				return 2; // February
			}
		);

		add_filter(
			'wcgk_current_year_override',
			function () {
				return 2023; // Non-leap year
			}
		);

		$coupon = $this->create_test_coupon();
		
		try {
			$result = $this->validator->validate_coupon( true, $coupon, null );
			$this->assertTrue( $result );
		} catch ( Exception $e ) {
			$this->fail( 'Validation should pass on fallback day: ' . $e->getMessage() );
		}

		// Should have fallback notice (notice type), not success notice.
		$notices        = wc_get_notices( 'notice' );
		$success_notices = wc_get_notices( 'success' );

		$this->assertNotEmpty( $notices, 'Should have fallback notice' );
		$this->assertEmpty( $success_notices, 'Should NOT have success notice when fallback is shown' );

		remove_all_filters( 'wcgk_current_day_override' );
		remove_all_filters( 'wcgk_current_month_override' );
		remove_all_filters( 'wcgk_current_year_override' );
	}

	/**
	 * Test error message shown when day restriction fails.
	 *
	 * @test
	 */
	public function test_error_message_on_wrong_day() {
		// Mock today as the 15th (not allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 15;
			}
		);

		$coupon = $this->create_test_coupon();
		
		$exception_thrown = false;
		$error_message    = '';

		try {
			$this->validator->validate_coupon( true, $coupon, null );
		} catch ( Exception $e ) {
			$exception_thrown = true;
			$error_message    = $e->getMessage();
		}

		$this->assertTrue( $exception_thrown, 'Should throw exception on wrong day' );
		$this->assertStringContainsString( 'allowed day', strtolower( $error_message ) );

		remove_all_filters( 'wcgk_current_day_override' );
	}

	/**
	 * Test custom error message for not allowed day.
	 *
	 * @test
	 */
	public function test_custom_error_message_not_allowed_day() {
		$custom_error = 'Sorry, this coupon only works on the 27th!';
		
		$this->settings->update( array( 'error_not_allowed_day' => $custom_error ) );
		$this->settings->clear_cache();

		// Recreate validator with updated settings.
		$this->validator = new Coupon_Validator( $this->settings );

		// Mock today as the 15th (not allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 15;
			}
		);

		$coupon        = $this->create_test_coupon();
		$error_message = '';

		try {
			$this->validator->validate_coupon( true, $coupon, null );
		} catch ( Exception $e ) {
			$error_message = $e->getMessage();
		}

		$this->assertEquals( $custom_error, $error_message );

		remove_all_filters( 'wcgk_current_day_override' );
	}

	/**
	 * Test error message shown when monthly limit reached.
	 *
	 * @test
	 */
	public function test_error_message_on_limit_reached() {
		// Mock today as the 27th (allowed day).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 27;
			}
		);

		// Set usage to limit.
		$month        = gmdate( 'Y-m' );
		$customer_key = 'user:1';
		Database::increment_usage( $this->test_coupon, $customer_key, 1234, $month );

		// Mock customer as user 1.
		wp_set_current_user( 1 );

		$coupon           = $this->create_test_coupon();
		$exception_thrown = false;
		$error_message    = '';

		try {
			$this->validator->validate_coupon( true, $coupon, null );
		} catch ( Exception $e ) {
			$exception_thrown = true;
			$error_message    = $e->getMessage();
		}

		$this->assertTrue( $exception_thrown, 'Should throw exception when limit reached' );
		$this->assertStringContainsString( 'already used', strtolower( $error_message ) );

		// Cleanup.
		Database::reset_usage( $this->test_coupon, $customer_key, $month );
		remove_all_filters( 'wcgk_current_day_override' );
	}

	/**
	 * Test custom error message for limit reached.
	 *
	 * @test
	 */
	public function test_custom_error_message_limit_reached() {
		$custom_error = 'You can only use this coupon once per month!';
		
		$this->settings->update( array( 'error_limit_reached' => $custom_error ) );
		$this->settings->clear_cache();

		// Recreate validator with updated settings.
		$this->validator = new Coupon_Validator( $this->settings );

		// Mock today as the 27th (allowed day).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 27;
			}
		);

		// Set usage to limit.
		$month        = gmdate( 'Y-m' );
		$customer_key = 'user:1';
		Database::increment_usage( $this->test_coupon, $customer_key, 1234, $month );

		// Mock customer as user 1.
		wp_set_current_user( 1 );

		$coupon        = $this->create_test_coupon();
		$error_message = '';

		try {
			$this->validator->validate_coupon( true, $coupon, null );
		} catch ( Exception $e ) {
			$error_message = $e->getMessage();
		}

		$this->assertEquals( $custom_error, $error_message );

		// Cleanup.
		Database::reset_usage( $this->test_coupon, $customer_key, $month );
		remove_all_filters( 'wcgk_current_day_override' );
	}

	/**
	 * Test no notices in admin context (non-AJAX).
	 *
	 * @test
	 */
	public function test_no_notices_in_admin_context() {
		// Enable success message.
		$this->settings->update(
			array(
				'enable_success_message' => true,
				'admin_bypass_edit_order' => false, // Disable bypass so validation runs.
			)
		);
		$this->settings->clear_cache();

		// Recreate validator with updated settings.
		$this->validator = new Coupon_Validator( $this->settings );

		// Mock today as the 27th.
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 27;
			}
		);

		// Mock admin context (non-AJAX).
		set_current_screen( 'edit-post' );

		$coupon = $this->create_test_coupon();
		
		try {
			$result = $this->validator->validate_coupon( true, $coupon, null );
			$this->assertTrue( $result );
		} catch ( Exception $e ) {
			$this->fail( 'Validation should pass on allowed day: ' . $e->getMessage() );
		}

		// Should have NO notices in admin context.
		$notices = wc_get_notices( 'success' );
		$this->assertEmpty( $notices, 'Should have no notices in admin context' );

		remove_all_filters( 'wcgk_current_day_override' );
		set_current_screen( 'front' );
	}

	/**
	 * Helper: Create a test coupon.
	 *
	 * @return WC_Coupon
	 */
	private function create_test_coupon() {
		$coupon = new WC_Coupon();
		$coupon->set_code( $this->test_coupon );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		return $coupon;
	}
}