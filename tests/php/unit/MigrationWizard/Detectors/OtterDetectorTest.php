<?php
/**
 * OtterDetectorTest class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\Detectors\OtterDetector;
use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Class OtterDetectorTest.
 *
 * @group migration-wizard
 */
class OtterDetectorTest extends HCaptchaTestCase {

	/**
	 * Otter plugin slug.
	 */
	private const PLUGIN_SLUG = 'otter-blocks/otter-blocks.php';

	/**
	 * Test get_source_plugin.
	 */
	public function test_get_source_plugin(): void {
		$detector = new OtterDetector();
		self::assertSame( self::PLUGIN_SLUG, $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 */
	public function test_get_source_name(): void {
		$detector = new OtterDetector();
		self::assertSame( 'Otter Blocks', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable true.
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG ] );

		$detector = new OtterDetector();
		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable false.
	 */
	public function test_is_applicable_false(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new OtterDetector();
		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with reCAPTCHA keys.
	 */
	public function test_detect_with_recaptcha_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_site_key', '' )
			->andReturn( 'site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_secret_key', '' )
			->andReturn( 'secret-key' );

		$detector = new OtterDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		$result = $results[0]->to_array();

		self::assertSame( 'recaptcha', $result['provider'] );
		self::assertSame( 'otter_form', $result['surface'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
		self::assertSame( 'otter_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
	}

	/**
	 * Test detect with empty keys.
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_secret_key', '' )
			->andReturn( '' );

		$detector = new OtterDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only site key.
	 */
	public function test_detect_only_site_key(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_site_key', '' )
			->andReturn( 'site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_secret_key', '' )
			->andReturn( '' );

		$detector = new OtterDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with whitespace keys.
	 */
	public function test_detect_whitespace_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_site_key', '' )
			->andReturn( ' ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'themeisle_google_captcha_api_secret_key', '' )
			->andReturn( ' ' );

		$detector = new OtterDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
