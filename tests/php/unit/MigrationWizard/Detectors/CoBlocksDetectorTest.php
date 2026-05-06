<?php
/**
 * CoBlocksDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\CoBlocksDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test CoBlocksDetector class.
 *
 * @group migration-wizard
 */
class CoBlocksDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new CoBlocksDetector();

		self::assertSame( 'coblocks/class-coblocks.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new CoBlocksDetector();

		self::assertSame( 'CoBlocks', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'coblocks/class-coblocks.php' ] );

		$detector = new CoBlocksDetector();

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

		$detector = new CoBlocksDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured keys.
	 *
	 * @return void
	 */
	public function test_detect_with_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_site_key', '' )
			->andReturn( 'site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_secret_key', '' )
			->andReturn( 'secret-key' );

		$detector = new CoBlocksDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		$result = $results[0]->to_array();

		self::assertSame( 'coblocks_form', $result['surface'] );
		self::assertSame( 'coblocks_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
		self::assertSame( 'recaptcha', $result['provider'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
	}

	/**
	 * Test detect with no keys.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_secret_key', '' )
			->andReturn( '' );

		$detector = new CoBlocksDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only site key.
	 *
	 * @return void
	 */
	public function test_detect_only_site_key(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_site_key', '' )
			->andReturn( 'site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_secret_key', '' )
			->andReturn( '' );

		$detector = new CoBlocksDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with whitespace keys.
	 *
	 * @return void
	 */
	public function test_detect_whitespace_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_site_key', '' )
			->andReturn( ' ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'coblocks_google_recaptcha_secret_key', '' )
			->andReturn( ' ' );

		$detector = new CoBlocksDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
