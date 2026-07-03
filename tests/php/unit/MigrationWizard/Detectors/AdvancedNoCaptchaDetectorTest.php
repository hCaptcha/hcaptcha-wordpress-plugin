<?php
/**
 * Test AdvancedNoCaptchaDetector.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\AdvancedNoCaptchaDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test AdvancedNoCaptchaDetector.
 *
 * @group migration-wizard
 */
class AdvancedNoCaptchaDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 */
	public function test_get_source_plugin(): void {
		$detector = new AdvancedNoCaptchaDetector();

		self::assertSame(
			'advanced-nocaptcha-recaptcha/advanced-nocaptcha-recaptcha.php',
			$detector->get_source_plugin()
		);
	}

	/**
	 * Test get_source_name.
	 */
	public function test_get_source_name(): void {
		$detector = new AdvancedNoCaptchaDetector();

		self::assertSame( 'Advanced noCaptcha & invisible Captcha', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable returns true when the plugin is active.
	 */
	public function test_is_applicable_when_plugin_active(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'advanced-nocaptcha-recaptcha/advanced-nocaptcha-recaptcha.php' ] );

		$detector = new AdvancedNoCaptchaDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable returns false when the plugin is inactive.
	 */
	public function test_is_applicable_when_plugin_inactive(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new AdvancedNoCaptchaDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect returns configured surfaces.
	 */
	public function test_detect_with_enabled_forms(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'c4wp_admin_options', [] )
			->andReturn(
				[
					'enabled_forms' => [
						'login',
						'registration',
						'lost_password',
						'comment',
						'wc_login',
						'wc_registration',
						'wc_checkout',
						'wc_lost_password',
						'bp_register',
						'bbp_topic',
						'bbp_reply',
					],
				]
			);

		$detector = new AdvancedNoCaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 11, $results );
		self::assertSame( 'wp_login', $results[0]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertTrue( $results[0]->is_migratable() );
		self::assertSame( 'buddypress_register', $results[8]->get_surface() );
		self::assertSame( DetectionResult::STATUS_UNSUPPORTED, $results[8]->get_support_status() );
	}

	/**
	 * Test detect returns an empty array for empty settings.
	 */
	public function test_detect_with_empty_settings(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'c4wp_admin_options', [] )
			->andReturn( [] );

		$detector = new AdvancedNoCaptchaDetector();

		self::assertSame( [], $detector->detect() );
	}

	/**
	 * Test detect returns an empty array for non-array settings.
	 */
	public function test_detect_with_non_array_settings(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'c4wp_admin_options', [] )
			->andReturn( 'broken' );

		$detector = new AdvancedNoCaptchaDetector();

		self::assertSame( [], $detector->detect() );
	}
}
