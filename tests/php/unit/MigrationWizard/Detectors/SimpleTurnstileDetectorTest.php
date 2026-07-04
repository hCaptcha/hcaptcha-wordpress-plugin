<?php
/**
 * Test SimpleTurnstileDetector.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\Detectors\SimpleTurnstileDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test SimpleTurnstileDetector.
 *
 * @group migration-wizard
 */
class SimpleTurnstileDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 */
	public function test_get_source_plugin(): void {
		$detector = new SimpleTurnstileDetector();

		self::assertSame( 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 */
	public function test_get_source_name(): void {
		$detector = new SimpleTurnstileDetector();

		self::assertSame( 'Simple Cloudflare Turnstile', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable returns true when the plugin is active.
	 */
	public function test_is_applicable_when_plugin_active(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' ] );

		$detector = new SimpleTurnstileDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable returns false when the plugin is inactive.
	 */
	public function test_is_applicable_when_plugin_inactive(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new SimpleTurnstileDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect returns every enabled unique surface.
	 */
	public function test_detect_with_enabled_options(): void {
		$enabled = [
			'cfturnstile_login'            => '1',
			'cfturnstile_register'         => '1',
			'cfturnstile_reset'            => '1',
			'cfturnstile_comment'          => '1',
			'cfturnstile_bbpress_create'   => '1',
			'cfturnstile_bbpress_reply'    => '1',
			'cfturnstile_bp_register'      => '1',
			'cfturnstile_cf7_all'          => '1',
			'cfturnstile_edd_checkout'     => '1',
			'cfturnstile_edd_login'        => '1',
			'cfturnstile_edd_register'     => '1',
			'cfturnstile_elementor'        => '1',
			'cfturnstile_fluent'           => '1',
			'cfturnstile_formidable'       => '1',
			'cfturnstile_forminator'       => '1',
			'cfturnstile_gravity'          => '1',
			'cfturnstile_jetpack'          => '1',
			'cfturnstile_kadence'          => '1',
			'cfturnstile_mailpoet'         => '1',
			'cfturnstile_mepr_login'       => '1',
			'cfturnstile_mepr_register'    => '1',
			'cfturnstile_pmp_checkout'     => '1',
			'cfturnstile_pmp_login'        => '1',
			'cfturnstile_um_login'         => '1',
			'cfturnstile_um_password'      => '1',
			'cfturnstile_um_register'      => '1',
			'cfturnstile_woo_login'        => '1',
			'cfturnstile_woo_register'     => '1',
			'cfturnstile_woo_checkout'     => '1',
			'cfturnstile_woo_checkout_pay' => '1',
			'cfturnstile_woo_reset'        => '1',
			'cfturnstile_wpforms'          => '1',
		];

		WP_Mock::userFunction( 'get_option' )
			->andReturnUsing(
				static function ( $option, $default_value = '' ) use ( $enabled ) {
					return $enabled[ $option ] ?? $default_value;
				}
			);

		$detector = new SimpleTurnstileDetector();
		$results  = $detector->detect();
		$surfaces = array_map(
			static function ( $result ) {
				return $result->get_surface();
			},
			$results
		);

		self::assertCount( 35, $results );
		self::assertSame( $surfaces, array_values( array_unique( $surfaces ) ) );
		self::assertContains( 'cf7_form', $surfaces );
		self::assertContains( 'gravity_embed', $surfaces );
		self::assertContains( 'wpforms_embed', $surfaces );
		self::assertContains( 'wc_checkout', $surfaces );
		self::assertSame( 'turnstile', $results[0]->get_provider() );
		self::assertSame( 'medium', $results[0]->get_confidence() );
	}

	/**
	 * Test detect returns an empty array when options are disabled.
	 */
	public function test_detect_with_disabled_options(): void {
		WP_Mock::userFunction( 'get_option' )
			->andReturnUsing(
				static function ( $option, $default_value = '' ) {
					return $default_value;
				}
			);

		$detector = new SimpleTurnstileDetector();

		self::assertSame( [], $detector->detect() );
	}
}
