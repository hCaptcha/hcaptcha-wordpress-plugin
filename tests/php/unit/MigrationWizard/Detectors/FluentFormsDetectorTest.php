<?php
/**
 * FluentFormsDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\FluentFormsDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test FluentFormsDetector class.
 *
 * @group migration-wizard
 */
class FluentFormsDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new FluentFormsDetector();

		self::assertSame( 'fluentform/fluentform.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new FluentFormsDetector();

		self::assertSame( 'Fluent Forms', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the free plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_free_plugin(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'fluentform/fluentform.php' ] );

		$detector = new FluentFormsDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable when the pro plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_pro_plugin(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'fluentformpro/fluentformpro.php' ] );

		$detector = new FluentFormsDetector();

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

		$detector = new FluentFormsDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured reCAPTCHA keys.
	 *
	 * @return void
	 */
	public function test_detect_with_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( '_fluentform_reCaptcha_details', [] )
			->andReturn(
				[
					'siteKey'   => 'test-site-key',
					'secretKey' => 'test-secret-key',
				]
			);

		$detector = new FluentFormsDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'fluent_form', $result['surface'] );
		self::assertSame( 'fluent_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
	}

	/**
	 * Test detect returns no results when keys are empty.
	 *
	 * @return void
	 */
	public function test_detect_empty_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( '_fluentform_reCaptcha_details', [] )
			->andReturn(
				[
					'siteKey'   => '',
					'secretKey' => '',
				]
			);

		$detector = new FluentFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results when the option is not an array.
	 *
	 * @return void
	 */
	public function test_detect_non_array_option(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( '_fluentform_reCaptcha_details', [] )
			->andReturn( false );

		$detector = new FluentFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results with incomplete keys.
	 *
	 * @return void
	 */
	public function test_detect_incomplete_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( '_fluentform_reCaptcha_details', [] )
			->andReturn(
				[
					'siteKey'   => 'test-site-key',
					'secretKey' => '',
				]
			);

		$detector = new FluentFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
