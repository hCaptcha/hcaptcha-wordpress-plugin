<?php
/**
 * ForminatorDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\ForminatorDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test ForminatorDetector class.
 *
 * @group migration-wizard
 */
class ForminatorDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new ForminatorDetector();

		self::assertSame( 'forminator/forminator.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new ForminatorDetector();

		self::assertSame( 'Forminator', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'forminator/forminator.php' ] );

		$detector = new ForminatorDetector();

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

		$detector = new ForminatorDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured v2 checkbox keys.
	 *
	 * @return void
	 */
	public function test_detect_v2_checkbox_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_key', '' )
			->andReturn( 'site-v2' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_secret', '' )
			->andReturn( 'secret-v2' );

		$detector = new ForminatorDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'forminator_form', $result['surface'] );
		self::assertSame( 'forminator_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
	}

	/**
	 * Test detect with configured v2 invisible keys.
	 *
	 * @return void
	 */
	public function test_detect_v2_invisible_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_secret', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_key', '' )
			->andReturn( 'site-v2-inv' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_secret', '' )
			->andReturn( 'secret-v2-inv' );

		$detector = new ForminatorDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'forminator_form', $result['surface'] );
	}

	/**
	 * Test detect with configured v3 keys.
	 *
	 * @return void
	 */
	public function test_detect_v3_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_secret', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_secret', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v3_captcha_key', '' )
			->andReturn( 'site-v3' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v3_captcha_secret', '' )
			->andReturn( 'secret-v3' );

		$detector = new ForminatorDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'forminator_form', $result['surface'] );
	}

	/**
	 * Test detect returns no results when no keys are configured.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_secret', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_secret', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v3_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v3_captcha_secret', '' )
			->andReturn( '' );

		$detector = new ForminatorDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results with incomplete keys (site key only).
	 *
	 * @return void
	 */
	public function test_detect_incomplete_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_key', '' )
			->andReturn( 'site-v2' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_captcha_secret', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v2_invisible_captcha_secret', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v3_captcha_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'forminator_v3_captcha_secret', '' )
			->andReturn( '' );

		$detector = new ForminatorDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
