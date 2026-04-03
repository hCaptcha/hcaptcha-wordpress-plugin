<?php
/**
 * BrevoDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\BrevoDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test BrevoDetector class.
 *
 * @group migration-wizard
 */
class BrevoDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new BrevoDetector();

		self::assertSame( 'mailin/sendinblue.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new BrevoDetector();

		self::assertSame( 'Brevo', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'mailin/sendinblue.php' ] );

		$detector = new BrevoDetector();

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

		$detector = new BrevoDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Set up wpdb mock for two queries (reCAPTCHA and Turnstile).
	 *
	 * @param string|null $recaptcha_count Count for reCAPTCHA query, or null for 0.
	 * @param string|null $turnstile_count Count for Turnstile query, or null for 0.
	 *
	 * @return void
	 */
	private function setup_wpdb( ?string $recaptcha_count, ?string $turnstile_count ): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb         = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturn( 'prepared_query' );

		$wpdb->shouldReceive( 'get_var' )
			->with( 'prepared_query' )
			->twice()
			->andReturn( $recaptcha_count, $turnstile_count );
	}

	/**
	 * Test detect with reCAPTCHA keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_recaptcha(): void {
		$this->setup_wpdb( '1', '0' );

		$detector = new BrevoDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'sendinblue_form', $results[0]->get_surface() );
	}

	/**
	 * Test detect with Turnstile keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_turnstile(): void {
		$this->setup_wpdb( '0', '1' );

		$detector = new BrevoDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'turnstile', $results[0]->get_provider() );
		self::assertSame( 'sendinblue_form', $results[0]->get_surface() );
	}

	/**
	 * Test detect with both reCAPTCHA and Turnstile configured.
	 *
	 * @return void
	 */
	public function test_detect_with_both_providers(): void {
		$this->setup_wpdb( '2', '1' );

		$detector = new BrevoDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'turnstile', $results[1]->get_provider() );
	}

	/**
	 * Test detect when no captcha is configured.
	 *
	 * @return void
	 */
	public function test_detect_no_captcha(): void {
		$this->setup_wpdb( '0', '0' );

		$detector = new BrevoDetector();
		$results  = $detector->detect();

		self::assertCount( 0, $results );
	}

	/**
	 * Test detect when get_var returns null (table missing).
	 *
	 * @return void
	 */
	public function test_detect_null_count(): void {
		$this->setup_wpdb( null, null );

		$detector = new BrevoDetector();
		$results  = $detector->detect();

		self::assertCount( 0, $results );
	}

	/**
	 * Test detect returns CONFIDENCE_HIGH.
	 *
	 * @return void
	 */
	public function test_detect_confidence_high(): void {
		$this->setup_wpdb( '1', '0' );

		$detector = new BrevoDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}
}
