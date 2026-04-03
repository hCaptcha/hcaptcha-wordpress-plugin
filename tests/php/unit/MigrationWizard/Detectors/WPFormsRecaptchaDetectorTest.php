<?php
/**
 * WPFormsRecaptchaDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\WPFormsRecaptchaDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Class WPFormsRecaptchaDetectorTest.
 *
 * @group migration-wizard
 */
class WPFormsRecaptchaDetectorTest extends HCaptchaTestCase {

	/**
	 * WPForms Lite plugin slug.
	 */
	private const PLUGIN_SLUG_LITE = 'wpforms-lite/wpforms.php';

	/**
	 * WPForms Pro plugin slug.
	 */
	private const PLUGIN_SLUG_PRO = 'wpforms/wpforms.php';

	/**
	 * Test detect with reCAPTCHA keys.
	 */
	public function test_detect_with_recaptcha() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpforms_settings', [] )
			->andReturn(
				[
					'captcha-provider'     => 'recaptcha',
					'recaptcha-site-key'   => 'test-site-key',
					'recaptcha-secret-key' => 'test-secret-key',
				]
			);

		$detector = new WPFormsRecaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'wpforms_form', $results[0]->get_surface() );
		self::assertSame( 'recaptcha', $results[1]->get_provider() );
		self::assertSame( 'wpforms_embed', $results[1]->get_surface() );
	}

	/**
	 * Test detect with Turnstile keys.
	 */
	public function test_detect_with_turnstile() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_PRO ] );

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpforms_settings', [] )
			->andReturn(
				[
					'captcha-provider'     => 'turnstile',
					'turnstile-site-key'   => 'ts-site-key',
					'turnstile-secret-key' => 'ts-secret-key',
				]
			);

		$detector = new WPFormsRecaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'turnstile', $results[0]->get_provider() );
	}

	/**
	 * Test detect with no settings.
	 */
	public function test_detect_no_settings() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpforms_settings', [] )
			->andReturn( [] );

		$detector = new WPFormsRecaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with other provider (e.g. hcaptcha).
	 */
	public function test_detect_with_hcaptcha_provider() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpforms_settings', [] )
			->andReturn(
				[
					'captcha-provider' => 'hcaptcha',
				]
			);

		$detector = new WPFormsRecaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with missing keys.
	 */
	public function test_detect_missing_keys() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpforms_settings', [] )
			->andReturn(
				[
					'captcha-provider'   => 'recaptcha',
					'recaptcha-site-key' => 'test-site-key',
					// secret key missing.
				]
			);

		$detector = new WPFormsRecaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test is_applicable.
	 */
	public function test_is_applicable_lite() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );
		$detector = new WPFormsRecaptchaDetector();
		self::assertTrue( $detector->is_applicable(), 'Lite should be applicable' );
	}

	/**
	 * Test is_applicable pro.
	 */
	public function test_is_applicable_pro() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_PRO ] );
		$detector = new WPFormsRecaptchaDetector();
		self::assertTrue( $detector->is_applicable(), 'Pro should be applicable' );
	}

	/**
	 * Test is_applicable none.
	 */
	public function test_is_applicable_none() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );
		$detector = new WPFormsRecaptchaDetector();
		self::assertFalse( $detector->is_applicable(), 'None should not be applicable' );
	}

	/**
	 * Test get_source_plugin pro.
	 */
	public function test_get_source_plugin_pro() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_PRO ] );
		$detector = new WPFormsRecaptchaDetector();
		self::assertSame( self::PLUGIN_SLUG_PRO, $detector->get_source_plugin(), 'Should return Pro when Pro is active' );
	}

	/**
	 * Test get_source_plugin lite.
	 */
	public function test_get_source_plugin_lite() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );
		$detector = new WPFormsRecaptchaDetector();
		self::assertSame( self::PLUGIN_SLUG_LITE, $detector->get_source_plugin(), 'Should return Lite when Lite is active' );
	}
}
