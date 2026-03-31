<?php
/**
 * AdvancedGoogleRecaptchaDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\AdvancedGoogleRecaptchaDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test AdvancedGoogleRecaptchaDetector class.
 *
 * @group migration-wizard
 */
class AdvancedGoogleRecaptchaDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new AdvancedGoogleRecaptchaDetector();

		self::assertSame( 'advanced-google-recaptcha/advanced-google-recaptcha.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new AdvancedGoogleRecaptchaDetector();

		self::assertSame( 'Advanced Google reCAPTCHA', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'advanced-google-recaptcha/advanced-google-recaptcha.php' ] );

		$detector = new AdvancedGoogleRecaptchaDetector();

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

		$detector = new AdvancedGoogleRecaptchaDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with enabled surfaces and reCAPTCHA v2.
	 *
	 * @return void
	 */
	public function test_detect_with_surfaces(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcaptcha_options', [] )
			->andReturn(
				[
					'captcha'                       => 'recaptchav2',
					'captcha_show_login'            => 1,
					'captcha_show_wp_registration'  => 1,
					'captcha_show_wp_lost_password' => 0,
					'captcha_show_wp_comment'       => 1,
					'captcha_show_woo_registration' => 0,
					'captcha_show_woo_checkout'     => 1,
				]
			);

		$detector = new AdvancedGoogleRecaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 4, $results );
		self::assertSame( 'wp_comment', $results[0]->get_surface() );
		self::assertSame( 'wp_login', $results[1]->get_surface() );
		self::assertSame( 'wp_register', $results[2]->get_surface() );
		self::assertSame( 'wc_checkout', $results[3]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect with reCAPTCHA v3.
	 *
	 * @return void
	 */
	public function test_detect_with_v3(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcaptcha_options', [] )
			->andReturn(
				[
					'captcha'            => 'recaptchav3',
					'captcha_show_login' => 1,
				]
			);

		$detector = new AdvancedGoogleRecaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'wp_login', $results[0]->get_surface() );
	}

	/**
	 * Test detect with captcha disabled.
	 *
	 * @return void
	 */
	public function test_detect_captcha_disabled(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcaptcha_options', [] )
			->andReturn(
				[
					'captcha'            => 'disabled',
					'captcha_show_login' => 1,
				]
			);

		$detector = new AdvancedGoogleRecaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with builtin captcha.
	 *
	 * @return void
	 */
	public function test_detect_captcha_builtin(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcaptcha_options', [] )
			->andReturn(
				[
					'captcha'            => 'builtin',
					'captcha_show_login' => 1,
				]
			);

		$detector = new AdvancedGoogleRecaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with empty options.
	 *
	 * @return void
	 */
	public function test_detect_empty_options(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcaptcha_options', [] )
			->andReturn( [] );

		$detector = new AdvancedGoogleRecaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
