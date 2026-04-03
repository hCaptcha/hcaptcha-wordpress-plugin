<?php
/**
 * DownloadManagerDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\DownloadManagerDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test DownloadManagerDetector class.
 *
 * @group migration-wizard
 */
class DownloadManagerDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new DownloadManagerDetector();

		self::assertSame( 'download-manager/download-manager.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new DownloadManagerDetector();

		self::assertSame( 'Download Manager', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'download-manager/download-manager.php' ] );

		$detector = new DownloadManagerDetector();

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

		$detector = new DownloadManagerDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with all three reCAPTCHA Enterprise keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_all_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_site_key', '' )
			->andReturn( 'site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_secret_key', '' )
			->andReturn( 'api-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_project_id', '' )
			->andReturn( 'my-project' );

		$detector = new DownloadManagerDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		$result = $results[0]->to_array();

		self::assertSame( 'download_manager_button', $result['surface'] );
		self::assertSame( 'download_manager_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'button', $result['hcaptcha_option_value'] );
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
			->with( '_wpdm_recaptcha_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_secret_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_project_id', '' )
			->andReturn( '' );

		$detector = new DownloadManagerDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with only site key — missing secret key and project ID.
	 *
	 * @return void
	 */
	public function test_detect_only_site_key(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_site_key', '' )
			->andReturn( 'site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_secret_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_project_id', '' )
			->andReturn( '' );

		$detector = new DownloadManagerDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with site key and secret key but no project ID.
	 *
	 * @return void
	 */
	public function test_detect_missing_project_id(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_site_key', '' )
			->andReturn( 'site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_secret_key', '' )
			->andReturn( 'api-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_project_id', '' )
			->andReturn( '' );

		$detector = new DownloadManagerDetector();
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
			->with( '_wpdm_recaptcha_site_key', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_secret_key', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( '_wpdm_recaptcha_project_id', '' )
			->andReturn( '   ' );

		$detector = new DownloadManagerDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
