<?php
/**
 * ElementorProDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\ElementorProDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test ElementorProDetector class.
 *
 * @group migration-wizard
 */
class ElementorProDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new ElementorProDetector();

		self::assertSame( 'elementor-pro/elementor-pro.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new ElementorProDetector();

		self::assertSame( 'Elementor Pro', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'elementor-pro/elementor-pro.php' ] );

		$detector = new ElementorProDetector();

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

		$detector = new ElementorProDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with reCAPTCHA v2 keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_v2_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_site_key', '' )
			->andReturn( 'v2-site-key' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_secret_key', '' )
			->andReturn( 'v2-secret-key' );

		$detector = new ElementorProDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'elementor_form', $results[0]->get_surface() );
		self::assertSame( 'elementor_login', $results[1]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect with reCAPTCHA v3 keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_v3_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_site_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_secret_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_v3_site_key', '' )
			->andReturn( 'v3-site-key' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_v3_secret_key', '' )
			->andReturn( 'v3-secret-key' );

		$detector = new ElementorProDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'elementor_form', $results[0]->get_surface() );
		self::assertSame( 'elementor_login', $results[1]->get_surface() );
	}

	/**
	 * Test detect with no keys configured.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_site_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_secret_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_v3_site_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_v3_secret_key', '' )
			->andReturn( '' );

		$detector = new ElementorProDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with partial v2 keys (only site key).
	 *
	 * @return void
	 */
	public function test_detect_partial_v2_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_site_key', '' )
			->andReturn( 'v2-site-key' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_secret_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_v3_site_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_v3_secret_key', '' )
			->andReturn( '' );

		$detector = new ElementorProDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect prefers v2 when both key pairs are configured.
	 *
	 * @return void
	 */
	public function test_detect_v2_takes_priority(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_site_key', '' )
			->andReturn( 'v2-site-key' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'elementor_pro_recaptcha_secret_key', '' )
			->andReturn( 'v2-secret-key' );

		$detector = new ElementorProDetector();
		$results  = $detector->detect();

		// Should return results from v2 and not check v3.
		self::assertCount( 2, $results );
		self::assertSame( 'elementor_form', $results[0]->get_surface() );
		self::assertSame( 'elementor_login', $results[1]->get_surface() );
	}
}
