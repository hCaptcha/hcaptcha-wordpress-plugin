<?php
/**
 * GoogleCaptchaDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\GoogleCaptchaDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test GoogleCaptchaDetector class.
 *
 * @group migration-wizard
 */
class GoogleCaptchaDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new GoogleCaptchaDetector();

		self::assertSame( 'google-captcha/google-captcha.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new GoogleCaptchaDetector();

		self::assertSame( 'reCaptcha by BestWebSoft', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'google-captcha/google-captcha.php' ] );

		$detector = new GoogleCaptchaDetector();

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

		$detector = new GoogleCaptchaDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with enabled surfaces.
	 *
	 * @return void
	 */
	public function test_detect_with_surfaces(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'gglcptch_options', [] )
			->andReturn(
				[
					'login_form'        => 1,
					'registration_form' => 1,
					'reset_pwd_form'    => 0,
					'comments_form'     => 1,
				]
			);

		$detector = new GoogleCaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 3, $results );
		self::assertSame( 'wp_login', $results[0]->get_surface() );
		self::assertSame( 'wp_register', $results[1]->get_surface() );
		self::assertSame( 'wp_comment', $results[2]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect with empty options.
	 *
	 * @return void
	 */
	public function test_detect_empty_options(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'gglcptch_options', [] )
			->andReturn( [] );

		$detector = new GoogleCaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
