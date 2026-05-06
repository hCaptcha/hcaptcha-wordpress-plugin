<?php
/**
 * GiveWPDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\GiveWPDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test GiveWPDetector class.
 *
 * @group migration-wizard
 */
class GiveWPDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new GiveWPDetector();

		self::assertSame( 'give/give.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new GiveWPDetector();

		self::assertSame( 'GiveWP', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'give/give.php' ] );

		$detector = new GiveWPDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable when the plugin is not active.
	 *
	 * @return void
	 */
	public function test_is_applicable_false(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new GiveWPDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured reCAPTCHA keys.
	 *
	 * @return void
	 */
	public function test_detect_with_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_settings', [] )
			->andReturn(
				[
					'recaptcha_key'    => 'site-key',
					'recaptcha_secret' => 'secret-key',
				]
			);

		$detector = new GiveWPDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		$result = $results[0]->to_array();

		self::assertSame( 'give_wp_form', $result['surface'] );
		self::assertSame( 'give_wp_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
		self::assertSame( 'recaptcha', $result['provider'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
	}

	/**
	 * Test detect with no keys.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_settings', [] )
			->andReturn( [] );

		$detector = new GiveWPDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only site key.
	 *
	 * @return void
	 */
	public function test_detect_only_site_key(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_settings', [] )
			->andReturn( [ 'recaptcha_key' => 'site-key' ] );

		$detector = new GiveWPDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only secret key.
	 *
	 * @return void
	 */
	public function test_detect_only_secret_key(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_settings', [] )
			->andReturn( [ 'recaptcha_secret' => 'secret-key' ] );

		$detector = new GiveWPDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with whitespace keys.
	 *
	 * @return void
	 */
	public function test_detect_whitespace_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_settings', [] )
			->andReturn(
				[
					'recaptcha_key'    => '  ',
					'recaptcha_secret' => '  ',
				]
			);

		$detector = new GiveWPDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with invalid (non-array) option.
	 *
	 * @return void
	 */
	public function test_detect_invalid_option(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_settings', [] )
			->andReturn( false );

		$detector = new GiveWPDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
