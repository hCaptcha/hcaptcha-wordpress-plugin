<?php
/**
 * KadenceDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\KadenceDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test KadenceDetector class.
 *
 * @group migration-wizard
 */
class KadenceDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new KadenceDetector();

		self::assertSame( 'kadence-blocks/kadence-blocks.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new KadenceDetector();

		self::assertSame( 'Kadence Blocks', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'kadence-blocks/kadence-blocks.php' ] );

		$detector = new KadenceDetector();

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

		$detector = new KadenceDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured reCAPTCHA keys only.
	 *
	 * @return void
	 */
	public function test_detect_with_recaptcha_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( 'test-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( 'test-secret-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );

		$form_result = $results[0]->to_array();

		self::assertSame( 'recaptcha', $form_result['provider'] );
		self::assertSame( 'kadence_form', $form_result['surface'] );
		self::assertSame( 'kadence_status', $form_result['hcaptcha_option_key'] );
		self::assertSame( 'form', $form_result['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $form_result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $form_result['support_status'] );

		$advanced_result = $results[1]->to_array();

		self::assertSame( 'recaptcha', $advanced_result['provider'] );
		self::assertSame( 'kadence_advanced', $advanced_result['surface'] );
		self::assertSame( 'kadence_status', $advanced_result['hcaptcha_option_key'] );
		self::assertSame( 'advanced_form', $advanced_result['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $advanced_result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $advanced_result['support_status'] );
	}

	/**
	 * Test detect with configured Turnstile keys only.
	 *
	 * @return void
	 */
	public function test_detect_with_turnstile_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( 'turnstile-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( 'turnstile-secret-key' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );

		$form_result = $results[0]->to_array();

		self::assertSame( 'turnstile', $form_result['provider'] );
		self::assertSame( 'kadence_form', $form_result['surface'] );
		self::assertSame( 'kadence_status', $form_result['hcaptcha_option_key'] );
		self::assertSame( 'form', $form_result['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $form_result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $form_result['support_status'] );

		$advanced_result = $results[1]->to_array();

		self::assertSame( 'turnstile', $advanced_result['provider'] );
		self::assertSame( 'kadence_advanced', $advanced_result['surface'] );
		self::assertSame( 'kadence_status', $advanced_result['hcaptcha_option_key'] );
		self::assertSame( 'advanced_form', $advanced_result['hcaptcha_option_value'] );
	}

	/**
	 * Test detect with both reCAPTCHA and Turnstile keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_both_recaptcha_and_turnstile(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( 'recaptcha-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( 'recaptcha-secret-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( 'turnstile-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( 'turnstile-secret-key' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		// Both reCAPTCHA and Turnstile detected for each surface.
		self::assertCount( 4, $results );

		self::assertSame( 'recaptcha', $results[0]->to_array()['provider'] );
		self::assertSame( 'kadence_form', $results[0]->to_array()['surface'] );
		self::assertSame( 'recaptcha', $results[1]->to_array()['provider'] );
		self::assertSame( 'kadence_advanced', $results[1]->to_array()['surface'] );
		self::assertSame( 'turnstile', $results[2]->to_array()['provider'] );
		self::assertSame( 'kadence_form', $results[2]->to_array()['surface'] );
		self::assertSame( 'turnstile', $results[3]->to_array()['provider'] );
		self::assertSame( 'kadence_advanced', $results[3]->to_array()['surface'] );
	}

	/**
	 * Test detect with no keys configured.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only reCAPTCHA site key (no secret).
	 *
	 * @return void
	 */
	public function test_detect_recaptcha_site_key_only(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( 'test-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only reCAPTCHA secret key (no site key).
	 *
	 * @return void
	 */
	public function test_detect_recaptcha_secret_key_only(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( 'test-secret-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with whitespace-only reCAPTCHA keys.
	 *
	 * @return void
	 */
	public function test_detect_whitespace_recaptcha_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only Turnstile site key (no secret).
	 *
	 * @return void
	 */
	public function test_detect_turnstile_site_key_only(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( 'turnstile-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with whitespace-only Turnstile keys.
	 *
	 * @return void
	 */
	public function test_detect_whitespace_turnstile_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_recaptcha_secret_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_site_key', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'kadence_blocks_turnstile_secret_key', '' )
			->andReturn( '   ' );

		$detector = new KadenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
