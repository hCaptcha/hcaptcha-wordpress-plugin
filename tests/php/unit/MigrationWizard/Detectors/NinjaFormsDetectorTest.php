<?php
/**
 * NinjaFormsDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\NinjaFormsDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test NinjaFormsDetector class.
 *
 * @group migration-wizard
 */
class NinjaFormsDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new NinjaFormsDetector();

		self::assertSame( 'ninja-forms/ninja-forms.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new NinjaFormsDetector();

		self::assertSame( 'Ninja Forms', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'ninja-forms/ninja-forms.php' ] );

		$detector = new NinjaFormsDetector();

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

		$detector = new NinjaFormsDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured v2 keys.
	 *
	 * @return void
	 */
	public function test_detect_v2_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'ninja_forms_settings', [] )
			->andReturn(
				[
					'recaptcha_site_key'   => 'site-v2',
					'recaptcha_secret_key' => 'secret-v2',
				]
			);

		$detector = new NinjaFormsDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'ninja_form', $result['surface'] );
		self::assertSame( 'ninja_status', $result['hcaptcha_option_key'] );
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
			->with( 'ninja_forms_settings', [] )
			->andReturn(
				[
					'recaptcha_site_key_3'   => 'site-v3',
					'recaptcha_secret_key_3' => 'secret-v3',
				]
			);

		$detector = new NinjaFormsDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'ninja_form', $result['surface'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
	}

	/**
	 * Test detect with both v2 and v3 keys returns only one result.
	 *
	 * @return void
	 */
	public function test_detect_both_v2_and_v3_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'ninja_forms_settings', [] )
			->andReturn(
				[
					'recaptcha_site_key'     => 'site-v2',
					'recaptcha_secret_key'   => 'secret-v2',
					'recaptcha_site_key_3'   => 'site-v3',
					'recaptcha_secret_key_3' => 'secret-v3',
				]
			);

		$detector = new NinjaFormsDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
	}

	/**
	 * Test detect returns no results when no keys are configured.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'ninja_forms_settings', [] )
			->andReturn( [] );

		$detector = new NinjaFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results with incomplete v2 keys.
	 *
	 * @return void
	 */
	public function test_detect_incomplete_v2_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'ninja_forms_settings', [] )
			->andReturn(
				[
					'recaptcha_site_key' => 'site-v2',
				]
			);

		$detector = new NinjaFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results with incomplete v3 keys.
	 *
	 * @return void
	 */
	public function test_detect_incomplete_v3_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'ninja_forms_settings', [] )
			->andReturn(
				[
					'recaptcha_site_key_3' => 'site-v3',
				]
			);

		$detector = new NinjaFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results when the option is not an array.
	 *
	 * @return void
	 */
	public function test_detect_invalid_option(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'ninja_forms_settings', [] )
			->andReturn( 'invalid' );

		$detector = new NinjaFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
