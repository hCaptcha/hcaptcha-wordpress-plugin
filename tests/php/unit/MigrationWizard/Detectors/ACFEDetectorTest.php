<?php
/**
 * ACFEDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\ACFEDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Class ACFEDetectorTest.
 *
 * @group migration-wizard
 */
class ACFEDetectorTest extends HCaptchaTestCase {

	/**
	 * ACF Extended Pro plugin slug.
	 */
	private const PLUGIN_SLUG_PRO = 'acf-extended-pro/acf-extended.php';

	/**
	 * ACF Extended Lite plugin slug.
	 */
	private const PLUGIN_SLUG_LITE = 'acf-extended/acf-extended.php';

	/**
	 * Test detect when reCAPTCHA keys are present in acfe_settings option.
	 */
	public function test_detect_with_keys_in_option() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		$settings = [
			'field' => [
				'recaptcha' => [
					'site_key'   => 'test-site-key',
					'secret_key' => 'test-secret-key',
				],
			],
		];

		WP_Mock::userFunction( 'get_option' )
			->with( 'acfe_settings', [] )
			->andReturn( $settings );

		$detector = new ACFEDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'acfe_form', $results[0]->get_surface() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect when reCAPTCHA keys are present via acf_get_setting.
	 */
	public function test_detect_with_keys_in_acf_get_setting() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		WP_Mock::userFunction( 'acf_get_setting' )
			->with( 'acfe/field/recaptcha/site_key' )
			->andReturn( 'site-key-from-acf' );

		WP_Mock::userFunction( 'acf_get_setting' )
			->with( 'acfe/field/recaptcha/secret_key' )
			->andReturn( 'secret-key-from-acf' );

		$detector = new ACFEDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
	}

	/**
	 * Test detect when no keys are present.
	 */
	public function test_detect_no_keys() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		WP_Mock::userFunction( 'get_option' )
			->with( 'acfe_settings', [] )
			->andReturn( [] );

		$detector = new ACFEDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect when only site key is present.
	 */
	public function test_detect_only_site_key() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		$settings = [
			'field' => [
				'recaptcha' => [
					'site_key' => 'test-site-key',
				],
			],
		];

		WP_Mock::userFunction( 'get_option' )
			->with( 'acfe_settings', [] )
			->andReturn( $settings );

		$detector = new ACFEDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with whitespace keys.
	 */
	public function test_detect_with_whitespace_keys() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		$settings = [
			'field' => [
				'recaptcha' => [
					'site_key'   => '   ',
					'secret_key' => '   ',
				],
			],
		];

		WP_Mock::userFunction( 'get_option' )
			->with( 'acfe_settings', [] )
			->andReturn( $settings );

		$detector = new ACFEDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test is_applicable.
	 */
	public function test_is_applicable() {
		// Lite active.
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_LITE ] );

		$detector = new ACFEDetector();
		self::assertTrue( $detector->is_applicable(), 'Lite should be applicable' );

		// Pro active.
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_PRO ] );

		self::assertTrue( $detector->is_applicable(), 'Pro should be applicable' );
	}

	/**
	 * Test get_source_plugin.
	 */
	public function test_get_source_plugin() {
		// Case 1: Pro is active.
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ self::PLUGIN_SLUG_PRO ] );

		$detector = new ACFEDetector();
		self::assertSame( self::PLUGIN_SLUG_PRO, $detector->get_source_plugin(), 'Should return Pro when Pro is active' );
	}
}
