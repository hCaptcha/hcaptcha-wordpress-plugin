<?php
/**
 * MetFormDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\MetFormDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test MetFormDetector class.
 *
 * @group migration-wizard
 */
class MetFormDetectorTest extends HCaptchaTestCase {

	/**
	 * MetForm posts query arguments.
	 */
	private const POSTS_QUERY_ARGS = [
		'post_type'   => 'metform-form',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	];

	/**
	 * Test get_source_plugin().
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new MetFormDetector();

		self::assertSame( 'metform/metform.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name().
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new MetFormDetector();

		self::assertSame( 'MetForm', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable() when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'metform/metform.php' ] );

		$detector = new MetFormDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable() when the plugin is inactive.
	 *
	 * @return void
	 */
	public function test_is_applicable_false(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new MetFormDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detection with global reCAPTCHA v2 keys.
	 *
	 * @return void
	 */
	public function test_detect_global_v2_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'metform_option__settings', [] )
			->andReturn(
				[
					'mf_recaptcha_site_key'   => 'v2-site-key',
					'mf_recaptcha_secret_key' => 'v2-secret-key',
				]
			);

		$detector = new MetFormDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'recaptcha', $result['provider'] );
		self::assertSame( 'metform_form', $result['surface'] );
		self::assertSame( 'metform_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
	}

	/**
	 * Test detection with global reCAPTCHA v3 keys.
	 *
	 * @return void
	 */
	public function test_detect_global_v3_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'metform_option__settings', [] )
			->andReturn(
				[
					'mf_recaptcha_site_key_v3'   => 'v3-site-key',
					'mf_recaptcha_secret_key_v3' => 'v3-secret-key',
				]
			);

		$detector = new MetFormDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'metform_form', $results[0]->get_surface() );
	}

	/**
	 * Test detection with keys stored in form settings.
	 *
	 * @return void
	 */
	public function test_detect_form_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'metform_option__settings', [] )
			->andReturn( [] );
		WP_Mock::userFunction( 'get_posts' )
			->with( self::POSTS_QUERY_ARGS )
			->andReturn( [ 10, 20 ] );
		WP_Mock::userFunction( 'get_post_meta' )
			->with( 10, 'metform_form__form_setting', true )
			->andReturn( 'invalid-settings' );
		WP_Mock::userFunction( 'get_post_meta' )
			->with( 20, 'metform_form__form_setting', true )
			->andReturn(
				[
					'mf_recaptcha_site_key'   => 'form-site-key',
					'mf_recaptcha_secret_key' => 'form-secret-key',
				]
			);

		$detector = new MetFormDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'metform_form', $results[0]->get_surface() );
	}

	/**
	 * Test global settings override form settings as MetForm does at runtime.
	 *
	 * @return void
	 */
	public function test_detect_global_settings_override_form_settings(): void {
		$global_settings = [
			'mf_recaptcha_site_key'   => '',
			'mf_recaptcha_secret_key' => '',
		];

		WP_Mock::userFunction( 'get_option' )
			->with( 'metform_option__settings', [] )
			->andReturn( $global_settings );
		WP_Mock::userFunction( 'get_posts' )
			->with( self::POSTS_QUERY_ARGS )
			->andReturn( [ 10 ] );
		WP_Mock::userFunction( 'get_post_meta' )
			->with( 10, 'metform_form__form_setting', true )
			->andReturn(
				[
					'mf_recaptcha_site_key'   => 'form-site-key',
					'mf_recaptcha_secret_key' => 'form-secret-key',
				]
			);

		$detector = new MetFormDetector();

		self::assertSame( [], $detector->detect() );
	}

	/**
	 * Test detection without complete reCAPTCHA keys.
	 *
	 * @return void
	 */
	public function test_detect_no_complete_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'metform_option__settings', [] )
			->andReturn(
				[
					'mf_recaptcha_site_key' => 'site-key-only',
				]
			);
		WP_Mock::userFunction( 'get_posts' )
			->with( self::POSTS_QUERY_ARGS )
			->andReturn( [] );

		$detector = new MetFormDetector();

		self::assertSame( [], $detector->detect() );
	}
}
