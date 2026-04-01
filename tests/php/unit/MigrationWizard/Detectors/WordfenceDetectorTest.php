<?php
/**
 * WordfenceDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\WordfenceDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test WordfenceDetector class.
 *
 * @group migration-wizard
 */
class WordfenceDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new WordfenceDetector();

		self::assertSame( 'wordfence/wordfence.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new WordfenceDetector();

		self::assertSame( 'Wordfence', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'wordfence/wordfence.php' ] );

		$detector = new WordfenceDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable when Wordfence Login Security is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_ls_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'wordfence-login-security/wordfence-login-security.php' ] );

		$detector = new WordfenceDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable when neither is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_false(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new WordfenceDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Set up wpdb mock.
	 *
	 * @param array|null $rows Rows to return from get_results, or null for failure.
	 *
	 * @return void
	 */
	private function setup_wpdb( $rows ): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb              = \Mockery::mock( 'wpdb' );
		$wpdb->base_prefix = 'wp_';

		$wpdb->shouldReceive( 'prepare' )->once()->andReturnUsing(
			function () {
				return 'prepared_query';
			}
		);

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'prepared_query' )
			->andReturn( $rows );
	}

	/**
	 * Test detect with enabled captcha and keys.
	 *
	 * @return void
	 */
	public function test_detect_enabled_with_keys(): void {
		$this->setup_wpdb(
			[
				(object) [
					'name'  => 'enable-auth-captcha',
					'value' => '1',
				],
				(object) [
					'name'  => 'recaptcha-site-key',
					'value' => 'site-key',
				],
				(object) [
					'name'  => 'recaptcha-secret',
					'value' => 'secret-key',
				],
			]
		);

		$detector = new WordfenceDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'wordfence_login', $results[0]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect with keys but captcha not enabled.
	 *
	 * @return void
	 */
	public function test_detect_keys_not_enabled(): void {
		$this->setup_wpdb(
			[
				(object) [
					'name'  => 'recaptcha-site-key',
					'value' => 'site-key',
				],
				(object) [
					'name'  => 'recaptcha-secret',
					'value' => 'secret-key',
				],
			]
		);

		$detector = new WordfenceDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( DetectionResult::CONFIDENCE_MEDIUM, $results[0]->get_confidence() );
	}

	/**
	 * Test detect with missing keys.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		$this->setup_wpdb( [] );

		$detector = new WordfenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect when DB query fails.
	 *
	 * @return void
	 */
	public function test_detect_db_failure(): void {
		$this->setup_wpdb( null );

		$detector = new WordfenceDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}
}
