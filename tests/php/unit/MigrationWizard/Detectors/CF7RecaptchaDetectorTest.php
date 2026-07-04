<?php
/**
 * Test CF7RecaptchaDetector.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\CF7\Base;
use HCaptcha\Helpers\Utils;
use HCaptcha\MigrationWizard\Detectors\CF7RecaptchaDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use WP_Mock;

/**
 * Test CF7RecaptchaDetector.
 *
 * @group migration-wizard
 */
class CF7RecaptchaDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 */
	public function test_get_source_plugin(): void {
		$detector = new CF7RecaptchaDetector();

		self::assertSame( 'contact-form-7/wp-contact-form-7.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 */
	public function test_get_source_name(): void {
		$detector = new CF7RecaptchaDetector();

		self::assertSame( 'Contact Form 7 (native reCAPTCHA)', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable returns true when the plugin is active.
	 */
	public function test_is_applicable_when_plugin_active(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'contact-form-7/wp-contact-form-7.php' ] );

		$detector = new CF7RecaptchaDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable returns false when the plugin is inactive.
	 */
	public function test_is_applicable_when_plugin_inactive(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new CF7RecaptchaDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect returns CF7 surfaces when keys are set.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_detect_with_keys(): void {
		$this->mock_utils_remove_action();

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcf7', [] )
			->andReturn(
				[
					'recaptcha' => [
						'site-key' => 'site-key',
						'secret'   => 'secret-key',
					],
				]
			);

		$detector = new CF7RecaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'cf7_form', $results[0]->get_surface() );
		self::assertSame( 'cf7_embed', $results[1]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'high', $results[0]->get_confidence() );
	}

	/**
	 * Test detect returns an empty array when keys are incomplete.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_detect_with_incomplete_keys(): void {
		$this->mock_utils_remove_action();

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcf7', [] )
			->andReturn(
				[
					'recaptcha' => [
						'site-key' => '',
					],
				]
			);

		$detector = new CF7RecaptchaDetector();

		self::assertSame( [], $detector->detect() );
	}

	/**
	 * Test detect returns an empty array for non-array settings.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_detect_with_non_array_settings(): void {
		$this->mock_utils_remove_action();

		WP_Mock::userFunction( 'get_option' )
			->with( 'wpcf7', [] )
			->andReturn( 'broken' );

		$detector = new CF7RecaptchaDetector();

		self::assertSame( [], $detector->detect() );
	}

	/**
	 * Mock Utils::remove_action_regex().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	private function mock_utils_remove_action(): void {
		$utils = Mockery::mock( Utils::class )->makePartial();

		$utils->shouldReceive( 'remove_action_regex' )
			->once()
			->with( '#^' . preg_quote( Base::class, '#' ) . '#', 'option_wpcf7' );

		$this->set_protected_property( $utils, 'instance', $utils );
	}
}
