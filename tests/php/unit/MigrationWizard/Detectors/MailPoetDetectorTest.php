<?php
/**
 * MailPoetDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\MailPoetDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test MailPoetDetector class.
 *
 * @group migration-wizard
 */
class MailPoetDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new MailPoetDetector();

		self::assertSame( 'mailpoet/mailpoet.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new MailPoetDetector();

		self::assertSame( 'MailPoet', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'mailpoet/mailpoet.php' ] );

		$detector = new MailPoetDetector();

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

		$detector = new MailPoetDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Set up wpdb mock.
	 *
	 * @param string|null $value Value to return from get_var, or null for no row.
	 *
	 * @return void
	 */
	private function setup_wpdb( ?string $value ): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb         = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'prepare' )->once()->andReturnUsing(
			function () {
				return 'prepared_query';
			}
		);

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->with( 'prepared_query' )
			->andReturn( $value );

		WP_Mock::userFunction( 'maybe_unserialize' )
			->andReturnUsing(
				function ( $data ) {
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
					$unserialized = @unserialize( $data );

					return false !== $unserialized ? $unserialized : $data;
				}
			);
	}

	/**
	 * Test detect with reCAPTCHA v2 keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_v2_keys(): void {
		$captcha = [
			'recaptcha_site_token'   => 'test-site-key',
			'recaptcha_secret_token' => 'test-secret-key',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->setup_wpdb( serialize( $captcha ) );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'mailpoet_form', $result['surface'] );
		self::assertSame( 'mailpoet_status', $result['hcaptcha_option_key'] );
		self::assertSame( 'form', $result['hcaptcha_option_value'] );
		self::assertSame( 'recaptcha', $result['provider'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result['support_status'] );
	}

	/**
	 * Test detect with invisible reCAPTCHA keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_invisible_keys(): void {
		$captcha = [
			'recaptcha_site_token'             => '',
			'recaptcha_secret_token'           => '',
			'recaptcha_invisible_site_token'   => 'inv-site-key',
			'recaptcha_invisible_secret_token' => 'inv-secret-key',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->setup_wpdb( serialize( $captcha ) );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'mailpoet_form', $result['surface'] );
		self::assertSame( 'recaptcha', $result['provider'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
	}

	/**
	 * Test detect prefers v2 keys when both are configured.
	 *
	 * @return void
	 */
	public function test_detect_v2_takes_priority(): void {
		$captcha = [
			'recaptcha_site_token'             => 'v2-site-key',
			'recaptcha_secret_token'           => 'v2-secret-key',
			'recaptcha_invisible_site_token'   => 'inv-site-key',
			'recaptcha_invisible_secret_token' => 'inv-secret-key',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->setup_wpdb( serialize( $captcha ) );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertStringContainsString( 'v2', $results[0]->to_array()['notes'] );
	}

	/**
	 * Test detect returns no results when keys are empty.
	 *
	 * @return void
	 */
	public function test_detect_empty_keys(): void {
		$captcha = [
			'recaptcha_site_token'             => '',
			'recaptcha_secret_token'           => '',
			'recaptcha_invisible_site_token'   => '',
			'recaptcha_invisible_secret_token' => '',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->setup_wpdb( serialize( $captcha ) );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results when DB returns null.
	 *
	 * @return void
	 */
	public function test_detect_no_row(): void {
		$this->setup_wpdb( null );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results when captcha value is not an array.
	 *
	 * @return void
	 */
	public function test_detect_non_array_captcha(): void {
		$this->setup_wpdb( 'invalid-string' );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results with incomplete v2 keys.
	 *
	 * @return void
	 */
	public function test_detect_incomplete_v2_keys(): void {
		$captcha = [
			'recaptcha_site_token'   => 'test-site-key',
			'recaptcha_secret_token' => '',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->setup_wpdb( serialize( $captcha ) );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns no results with whitespace-only keys.
	 *
	 * @return void
	 */
	public function test_detect_whitespace_keys(): void {
		$captcha = [
			'recaptcha_site_token'             => '  ',
			'recaptcha_secret_token'           => '  ',
			'recaptcha_invisible_site_token'   => "\t",
			'recaptcha_invisible_secret_token' => "\t",
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->setup_wpdb( serialize( $captcha ) );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with real serialized data from MailPoet database.
	 *
	 * @return void
	 */
	public function test_detect_with_real_serialized_data(): void {
		// Real serialized data as stored in mailpoet_settings table.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$value = 'a:6:{s:17:"on_register_forms";a:1:{s:7:"enabled";s:0:"";}s:32:"recaptcha_invisible_secret_token";s:0:"";s:30:"recaptcha_invisible_site_token";s:0:"";s:22:"recaptcha_secret_token";s:40:"6LffKF0cAAAAAMaaWuufNJwR4Usu7OV7LF5hBb95";s:20:"recaptcha_site_token";s:40:"6LffKF0cAAAAALse6pcgfITj_awyCt6YhUdU8Cbr";s:4:"type";s:9:"recaptcha";}';

		$this->setup_wpdb( $value );

		$detector = new MailPoetDetector();
		$results  = $detector->detect();
		$result   = $results[0]->to_array();

		self::assertCount( 1, $results );
		self::assertSame( 'mailpoet_form', $result['surface'] );
		self::assertSame( 'recaptcha', $result['provider'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result['confidence'] );
	}
}
