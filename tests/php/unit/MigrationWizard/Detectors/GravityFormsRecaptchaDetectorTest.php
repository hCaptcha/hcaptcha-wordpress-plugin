<?php
/**
 * Test GravityFormsRecaptchaDetector.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\Detectors\GravityFormsRecaptchaDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test GravityFormsRecaptchaDetector.
 *
 * @group migration-wizard
 */
class GravityFormsRecaptchaDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 */
	public function test_get_source_plugin(): void {
		$detector = new GravityFormsRecaptchaDetector();

		self::assertSame( 'gravityforms/gravityforms.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 */
	public function test_get_source_name(): void {
		$detector = new GravityFormsRecaptchaDetector();

		self::assertSame( 'Gravity Forms', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable returns true when the plugin is active.
	 */
	public function test_is_applicable_when_plugin_active(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'gravityforms/gravityforms.php' ] );

		$detector = new GravityFormsRecaptchaDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable returns false when the plugin is inactive.
	 */
	public function test_is_applicable_when_plugin_inactive(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new GravityFormsRecaptchaDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect returns Gravity Forms surfaces when keys are set.
	 */
	public function test_detect_with_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'rg_gforms_captcha_public_key', '' )
			->andReturn( 'site-key' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'rg_gforms_captcha_private_key', '' )
			->andReturn( 'secret-key' );

		$detector = new GravityFormsRecaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'gravity_form', $results[0]->get_surface() );
		self::assertSame( 'gravity_embed', $results[1]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'medium', $results[0]->get_confidence() );
	}

	/**
	 * Test detect returns an empty array when keys are incomplete.
	 */
	public function test_detect_with_incomplete_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'rg_gforms_captcha_public_key', '' )
			->andReturn( 'site-key' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'rg_gforms_captcha_private_key', '' )
			->andReturn( '' );

		$detector = new GravityFormsRecaptchaDetector();

		self::assertSame( [], $detector->detect() );
	}
}
