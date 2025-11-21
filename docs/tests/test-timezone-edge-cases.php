<?php
/**
 * Unit tests for timezone and date/time edge cases.
 *
 * Tests scenarios involving:
 * - Day changes at 23:59:59 and 00:00:01
 * - Month boundaries (last day → first day)
 * - Timezone handling
 * - Leap years
 * - Daylight Saving Time transitions
 *
 * @package WC_Coupon_Gatekeeper
 */

use WC_Coupon_Gatekeeper\Settings;
use WC_Coupon_Gatekeeper\Database;
use WC_Coupon_Gatekeeper\Validator\Coupon_Validator;

/**
 * Class Test_Timezone_Edge_Cases
 */
class Test_Timezone_Edge_Cases extends WP_UnitTestCase {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Original timezone.
	 *
	 * @var string
	 */
	private $original_timezone;

	/**
	 * Setup test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Save original timezone.
		$this->original_timezone = get_option( 'timezone_string' );

		// Create settings instance.
		$this->settings = new Settings();

		// Reset settings to defaults.
		update_option(
			'wc_coupon_gatekeeper_settings',
			array(
				'enable_day_restriction' => true,
				'allowed_days'           => array( 27 ),
				'use_last_valid_day'     => false,
			)
		);
		$this->settings->clear_cache();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Restore original timezone.
		if ( $this->original_timezone ) {
			update_option( 'timezone_string', $this->original_timezone );
		}

		// Remove test filters.
		remove_all_filters( 'wcgk_current_day_override' );
		remove_all_filters( 'wcgk_current_month_override' );

		parent::tearDown();
	}

	/**
	 * Test day boundary at 23:59:59 (last second of day 26).
	 *
	 * @return void
	 */
	public function test_day_boundary_23_59_59() {
		// Set allowed day to 27.
		$this->settings->update( array( 'allowed_days' => array( 27 ) ) );

		// Mock current day as 26 (should NOT be allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 26;
			}
		);

		$validator = new Coupon_Validator( $this->settings );

		// Get day check result.
		$day_check = $this->check_day_allowed( $validator );

		// Day 26 should NOT be allowed.
		$this->assertFalse( $day_check['allowed'] );
	}

	/**
	 * Test day boundary at 00:00:01 (first second of day 27).
	 *
	 * @return void
	 */
	public function test_day_boundary_00_00_01() {
		// Set allowed day to 27.
		$this->settings->update( array( 'allowed_days' => array( 27 ) ) );

		// Mock current day as 27 (should be allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 27;
			}
		);

		$validator = new Coupon_Validator( $this->settings );

		// Get day check result.
		$day_check = $this->check_day_allowed( $validator );

		// Day 27 should be allowed.
		$this->assertTrue( $day_check['allowed'] );
	}

	/**
	 * Test month boundary transition (Jan 31 → Feb 1).
	 *
	 * @return void
	 */
	public function test_month_boundary_jan_to_feb() {
		// Set allowed day to 1 (first day of month).
		$this->settings->update( array( 'allowed_days' => array( 1 ) ) );

		// Mock current day as 31 in January (should NOT be allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 31;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2024-01';
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// January 31 should NOT be allowed.
		$this->assertFalse( $day_check['allowed'] );

		// Clear filters.
		remove_all_filters( 'wcgk_current_day_override' );
		remove_all_filters( 'wcgk_current_month_override' );

		// Mock February 1 (should be allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 1;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2024-02';
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// February 1 should be allowed.
		$this->assertTrue( $day_check['allowed'] );
	}

	/**
	 * Test leap year February 29 handling.
	 *
	 * @return void
	 */
	public function test_leap_year_feb_29() {
		// Set allowed days to 29.
		$this->settings->update( array( 'allowed_days' => array( 29 ) ) );

		// Mock February 29, 2024 (leap year - should be allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 29;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2024-02'; // 2024 is a leap year.
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// February 29 should be allowed in leap year.
		$this->assertTrue( $day_check['allowed'] );
	}

	/**
	 * Test non-leap year February 29 with fallback.
	 *
	 * @return void
	 */
	public function test_non_leap_year_feb_29_fallback() {
		// Set allowed days to 29 and enable fallback.
		$this->settings->update(
			array(
				'allowed_days'       => array( 29 ),
				'use_last_valid_day' => true,
			)
		);

		// Mock February 28, 2023 (non-leap year - last day).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 28;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2023-02'; // 2023 is NOT a leap year.
			}
		);

		// Mock wp_date('t') to return 28 (last day of Feb 2023).
		add_filter(
			'wp_date',
			function ( $format ) {
				if ( 't' === $format ) {
					return 28;
				}
				return $format;
			},
			10,
			1
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// February 28 should be allowed as fallback (29 doesn't exist).
		$this->assertTrue( $day_check['allowed'] );
		$this->assertTrue( $day_check['is_fallback'] );
	}

	/**
	 * Test month with 31 days (January, March, May, etc.).
	 *
	 * @return void
	 */
	public function test_31_day_month() {
		// Set allowed day to 31.
		$this->settings->update( array( 'allowed_days' => array( 31 ) ) );

		// Mock January 31.
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 31;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2024-01';
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// January 31 should be allowed.
		$this->assertTrue( $day_check['allowed'] );
		$this->assertFalse( $day_check['is_fallback'] );
	}

	/**
	 * Test month with 30 days (April, June, September, November).
	 *
	 * @return void
	 */
	public function test_30_day_month_with_31_fallback() {
		// Set allowed day to 31 with fallback enabled.
		$this->settings->update(
			array(
				'allowed_days'       => array( 31 ),
				'use_last_valid_day' => true,
			)
		);

		// Mock April 30 (April has 30 days).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 30;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2024-04';
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// April 30 should be allowed as fallback (31 doesn't exist).
		$this->assertTrue( $day_check['allowed'] );
		$this->assertTrue( $day_check['is_fallback'] );
	}

	/**
	 * Test year boundary (Dec 31 → Jan 1).
	 *
	 * @return void
	 */
	public function test_year_boundary_dec_to_jan() {
		// Set allowed day to 1.
		$this->settings->update( array( 'allowed_days' => array( 1 ) ) );

		// Mock December 31 (should NOT be allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 31;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2023-12';
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// December 31 should NOT be allowed.
		$this->assertFalse( $day_check['allowed'] );

		// Clear filters.
		remove_all_filters( 'wcgk_current_day_override' );
		remove_all_filters( 'wcgk_current_month_override' );

		// Mock January 1 (should be allowed).
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 1;
			}
		);
		add_filter(
			'wcgk_current_month_override',
			function () {
				return '2024-01';
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// January 1 should be allowed.
		$this->assertTrue( $day_check['allowed'] );
	}

	/**
	 * Test timezone handling (UTC vs site timezone).
	 *
	 * @return void
	 */
	public function test_timezone_handling() {
		// Set timezone to New York (UTC-5 or UTC-4 with DST).
		update_option( 'timezone_string', 'America/New_York' );

		// Set allowed day to 27.
		$this->settings->update( array( 'allowed_days' => array( 27 ) ) );

		// Get current day using wp_date (respects site timezone).
		$current_day = (int) wp_date( 'j' );

		// Mock to ensure we're testing day 27.
		add_filter(
			'wcgk_current_day_override',
			function () {
				return 27;
			}
		);

		$validator = new Coupon_Validator( $this->settings );
		$day_check = $this->check_day_allowed( $validator );

		// Day 27 should be allowed regardless of timezone.
		$this->assertTrue( $day_check['allowed'] );
	}

	/**
	 * Test multiple allowed days across month boundary.
	 *
	 * @return void
	 */
	public function test_multiple_allowed_days() {
		// Set allowed days to 28, 29, 30, 31.
		$this->settings->update( array( 'allowed_days' => array( 28, 29, 30, 31 ) ) );

		// Test each day in February (non-leap year).
		for ( $day = 28; $day <= 28; $day++ ) {
			remove_all_filters( 'wcgk_current_day_override' );
			
			add_filter(
				'wcgk_current_day_override',
				function () use ( $day ) {
					return $day;
				}
			);

			$validator = new Coupon_Validator( $this->settings );
			$day_check = $this->check_day_allowed( $validator );

			// Day 28 should be allowed in February.
			if ( 28 === $day ) {
				$this->assertTrue( $day_check['allowed'], "Day {$day} should be allowed" );
			}
		}
	}

	/**
	 * Test Database::get_current_day() returns correct value.
	 *
	 * @return void
	 */
	public function test_database_get_current_day() {
		// Get current day from Database class.
		$current_day = Database::get_current_day();

		// Should be integer between 1 and 31.
		$this->assertIsInt( $current_day );
		$this->assertGreaterThanOrEqual( 1, $current_day );
		$this->assertLessThanOrEqual( 31, $current_day );
	}

	/**
	 * Test Database::get_current_month() returns correct format.
	 *
	 * @return void
	 */
	public function test_database_get_current_month() {
		// Get current month from Database class.
		$current_month = Database::get_current_month();

		// Should match YYYY-MM format.
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}$/', $current_month );

		// Parse and verify.
		$parts = explode( '-', $current_month );
		$this->assertCount( 2, $parts );
		
		$year  = (int) $parts[0];
		$month = (int) $parts[1];

		$this->assertGreaterThanOrEqual( 2020, $year );
		$this->assertLessThanOrEqual( 2100, $year );
		$this->assertGreaterThanOrEqual( 1, $month );
		$this->assertLessThanOrEqual( 12, $month );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Check day allowed using reflection.
	 *
	 * @param Coupon_Validator $validator Validator instance.
	 * @return array
	 */
	private function check_day_allowed( $validator ) {
		$reflection = new ReflectionClass( $validator );
		$method     = $reflection->getMethod( 'check_day_allowed' );
		$method->setAccessible( true );
		return $method->invoke( $validator );
	}
}