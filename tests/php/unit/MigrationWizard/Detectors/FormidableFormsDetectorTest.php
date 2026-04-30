<?php
/**
 * FormidableFormsDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\FormidableFormsDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;
use stdClass;

/**
 * Class FormidableFormsDetectorTest.
 *
 * @group migration-wizard
 */
class FormidableFormsDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_name().
	 */
	public function test_get_source_name() {
		$detector = new FormidableFormsDetector();
		self::assertSame( 'Formidable Forms', $detector->get_source_name() );
	}

	/**
	 * Test get_source_plugin().
	 */
	public function test_get_source_plugin() {
		$detector = new FormidableFormsDetector();
		self::assertSame( 'formidable/formidable.php', $detector->get_source_plugin() );
	}

	/**
	 * Test is_applicable() when plugin is active.
	 */
	public function test_is_applicable_true() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'formidable/formidable.php' ] );

		$detector = new FormidableFormsDetector();
		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable() when plugin is inactive.
	 */
	public function test_is_applicable_false() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new FormidableFormsDetector();
		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect() with reCAPTCHA keys.
	 */
	public function test_detect_recaptcha() {
		$settings          = new stdClass();
		$settings->pubkey  = 'site-key';
		$settings->privkey = 'secret-key';

		WP_Mock::userFunction( 'get_option' )
			->with( 'frm_options' )
			->andReturn( $settings );

		$detector = new FormidableFormsDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'formidable_form', $results[0]->get_surface() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect() with Turnstile keys.
	 */
	public function test_detect_turnstile() {
		$settings                    = new stdClass();
		$settings->turnstile_pubkey  = 'site-key';
		$settings->turnstile_privkey = 'secret-key';

		WP_Mock::userFunction( 'get_option' )
			->with( 'frm_options' )
			->andReturn( $settings );

		$detector = new FormidableFormsDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'turnstile', $results[0]->get_provider() );
		self::assertSame( 'formidable_form', $results[0]->get_surface() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect() with both providers.
	 */
	public function test_detect_both() {
		$settings                    = new stdClass();
		$settings->pubkey            = 're-site-key';
		$settings->privkey           = 're-secret-key';
		$settings->turnstile_pubkey  = 'ts-site-key';
		$settings->turnstile_privkey = 'ts-secret-key';

		WP_Mock::userFunction( 'get_option' )
			->with( 'frm_options' )
			->andReturn( $settings );

		$detector = new FormidableFormsDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'turnstile', $results[1]->get_provider() );
	}

	/**
	 * Test detect() with no keys.
	 */
	public function test_detect_no_keys() {
		$settings = new stdClass();

		WP_Mock::userFunction( 'get_option' )
			->with( 'frm_options' )
			->andReturn( $settings );

		$detector = new FormidableFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect() with incomplete reCAPTCHA keys.
	 */
	public function test_detect_incomplete_recaptcha() {
		$settings         = new stdClass();
		$settings->pubkey = 'site-key';

		WP_Mock::userFunction( 'get_option' )
			->with( 'frm_options' )
			->andReturn( $settings );

		$detector = new FormidableFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect() with whitespace keys.
	 */
	public function test_detect_whitespace_keys() {
		$settings          = new stdClass();
		$settings->pubkey  = '   ';
		$settings->privkey = '   ';

		WP_Mock::userFunction( 'get_option' )
			->with( 'frm_options' )
			->andReturn( $settings );

		$detector = new FormidableFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect() when option is not an object.
	 */
	public function test_detect_not_object() {
		WP_Mock::userFunction( 'get_option' )
			->with( 'frm_options' )
			->andReturn( 'not-an-object' );

		$detector = new FormidableFormsDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
