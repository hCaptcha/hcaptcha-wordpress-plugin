<?php
/**
 * SpectraDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\SpectraDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test SpectraDetector class.
 *
 * @group migration-wizard
 */
class SpectraDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new SpectraDetector();

		self::assertSame( 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new SpectraDetector();

		self::assertSame( 'Spectra', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php' ] );

		$detector = new SpectraDetector();

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

		$detector = new SpectraDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured v2 keys.
	 *
	 * @return void
	 */
	public function test_detect_v2_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_site_key_v2', '' )
			->andReturn( 'site-v2' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_site_key_v3', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_secret_key_v2', '' )
			->andReturn( 'secret-v2' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_secret_key_v3', '' )
			->andReturn( '' );

		$detector = new SpectraDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'spectra_form', $result['surface'] );
		self::assertSame( 'spectra_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
	}

	/**
	 * Test detect with configured v3 keys.
	 *
	 * @return void
	 */
	public function test_detect_v3_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_site_key_v2', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_site_key_v3', '' )
			->andReturn( 'site-v3' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_secret_key_v2', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_secret_key_v3', '' )
			->andReturn( 'secret-v3' );

		$detector = new SpectraDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'spectra_form', $result['surface'] );
	}

	/**
	 * Test detect returns no results with incomplete keys.
	 *
	 * @return void
	 */
	public function test_detect_incomplete_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_site_key_v2', '' )
			->andReturn( 'site-v2' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_site_key_v3', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_secret_key_v2', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'uag_recaptcha_secret_key_v3', '' )
			->andReturn( '' );

		$detector = new SpectraDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
