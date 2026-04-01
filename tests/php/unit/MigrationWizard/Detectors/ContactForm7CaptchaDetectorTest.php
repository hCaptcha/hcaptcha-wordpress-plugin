<?php
/**
 * ContactForm7CaptchaDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\ContactForm7CaptchaDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test ContactForm7CaptchaDetector class.
 *
 * @group migration-wizard
 */
class ContactForm7CaptchaDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new ContactForm7CaptchaDetector();

		self::assertSame( 'contact-form-7-simple-recaptcha/contact-form-7-simple-recaptcha.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new ContactForm7CaptchaDetector();

		self::assertSame( 'Contact Form 7 Captcha', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'contact-form-7-simple-recaptcha/contact-form-7-simple-recaptcha.php' ] );

		$detector = new ContactForm7CaptchaDetector();

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

		$detector = new ContactForm7CaptchaDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with reCAPTCHA v2 keys configured.
	 *
	 * @return void
	 */
	public function test_detect_with_recaptcha_v2(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key', '' )
			->andReturn( 'site-key-v2' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret', '' )
			->andReturn( 'secret-key-v2' );

		$detector = new ContactForm7CaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'cf7_form', $results[0]->get_surface() );
		self::assertSame( 'cf7_embed', $results[1]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect with reCAPTCHA v3 keys configured (no v2).
	 *
	 * @return void
	 */
	public function test_detect_with_recaptcha_v3(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key_v3', '' )
			->andReturn( 'site-key-v3' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret_v3', '' )
			->andReturn( 'secret-key-v3' );

		$detector = new ContactForm7CaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'cf7_form', $results[0]->get_surface() );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
	}

	/**
	 * Test detect with Turnstile keys configured (no reCAPTCHA).
	 *
	 * @return void
	 */
	public function test_detect_with_turnstile(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key_v3', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret_v3', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_ts_key', '' )
			->andReturn( 'ts-site-key' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_ts_secret', '' )
			->andReturn( 'ts-secret-key' );

		$detector = new ContactForm7CaptchaDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );
		self::assertSame( 'cf7_form', $results[0]->get_surface() );
		self::assertSame( 'turnstile', $results[0]->get_provider() );
	}

	/**
	 * Test detect with no keys configured.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key_v3', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret_v3', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_ts_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_ts_secret', '' )
			->andReturn( '' );

		$detector = new ContactForm7CaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect with partial keys (key without secret).
	 *
	 * @return void
	 */
	public function test_detect_partial_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key', '' )
			->andReturn( 'site-key-v2' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key_v3', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret_v3', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_ts_key', '' )
			->andReturn( '' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_ts_secret', '' )
			->andReturn( '' );

		$detector = new ContactForm7CaptchaDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns first configured provider only (v2 wins over v3).
	 *
	 * @return void
	 */
	public function test_detect_first_provider_wins(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_key', '' )
			->andReturn( 'site-key-v2' );

		WP_Mock::userFunction( 'get_option' )
			->with( 'cf7sr_secret', '' )
			->andReturn( 'secret-key-v2' );

		$detector = new ContactForm7CaptchaDetector();
		$results  = $detector->detect();

		// Should return only 2 results (cf7_form + cf7_embed), not 4.
		self::assertCount( 2, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
	}
}
