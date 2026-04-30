<?php
/**
 * PaidMembershipProDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\PaidMembershipProDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test PaidMembershipProDetector class.
 *
 * @group migration-wizard
 */
class PaidMembershipProDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new PaidMembershipProDetector();

		self::assertSame( 'paid-memberships-pro/paid-memberships-pro.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new PaidMembershipProDetector();

		self::assertSame( 'Paid Memberships Pro', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'paid-memberships-pro/paid-memberships-pro.php' ] );

		$detector = new PaidMembershipProDetector();

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

		$detector = new PaidMembershipProDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test detect with configured reCAPTCHA keys only.
	 *
	 * @return void
	 */
	public function test_detect_with_recaptcha_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_publickey', '' )
			->andReturn( 'test-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_privatekey', '' )
			->andReturn( 'test-secret-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new PaidMembershipProDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );

		$checkout = $results[0]->to_array();

		self::assertSame( 'recaptcha', $checkout['provider'] );
		self::assertSame( 'pmp_checkout', $checkout['surface'] );
		self::assertSame( 'paid_memberships_pro_status', $checkout['hcaptcha_option_key'] );
		self::assertSame( 'checkout', $checkout['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $checkout['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $checkout['support_status'] );

		$login = $results[1]->to_array();

		self::assertSame( 'recaptcha', $login['provider'] );
		self::assertSame( 'pmp_login', $login['surface'] );
		self::assertSame( 'paid_memberships_pro_status', $login['hcaptcha_option_key'] );
		self::assertSame( 'login', $login['hcaptcha_option_value'] );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $login['confidence'] );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $login['support_status'] );
	}

	/**
	 * Test detect with configured Turnstile keys only.
	 *
	 * @return void
	 */
	public function test_detect_with_turnstile_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_publickey', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_privatekey', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_site_key', '' )
			->andReturn( 'ts-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_secret_key', '' )
			->andReturn( 'ts-secret-key' );

		$detector = new PaidMembershipProDetector();
		$results  = $detector->detect();

		self::assertCount( 2, $results );

		$checkout = $results[0]->to_array();

		self::assertSame( 'turnstile', $checkout['provider'] );
		self::assertSame( 'pmp_checkout', $checkout['surface'] );

		$login = $results[1]->to_array();

		self::assertSame( 'turnstile', $login['provider'] );
		self::assertSame( 'pmp_login', $login['surface'] );
	}

	/**
	 * Test detect with both reCAPTCHA and Turnstile keys.
	 *
	 * @return void
	 */
	public function test_detect_with_both_providers(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_publickey', '' )
			->andReturn( 'rc-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_privatekey', '' )
			->andReturn( 'rc-secret-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_site_key', '' )
			->andReturn( 'ts-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_secret_key', '' )
			->andReturn( 'ts-secret-key' );

		$detector = new PaidMembershipProDetector();
		$results  = $detector->detect();

		self::assertCount( 4, $results );

		self::assertSame( 'recaptcha', $results[0]->to_array()['provider'] );
		self::assertSame( 'pmp_checkout', $results[0]->to_array()['surface'] );
		self::assertSame( 'recaptcha', $results[1]->to_array()['provider'] );
		self::assertSame( 'pmp_login', $results[1]->to_array()['surface'] );
		self::assertSame( 'turnstile', $results[2]->to_array()['provider'] );
		self::assertSame( 'pmp_checkout', $results[2]->to_array()['surface'] );
		self::assertSame( 'turnstile', $results[3]->to_array()['provider'] );
		self::assertSame( 'pmp_login', $results[3]->to_array()['surface'] );
	}

	/**
	 * Test detect with no keys configured.
	 *
	 * @return void
	 */
	public function test_detect_no_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_publickey', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_privatekey', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new PaidMembershipProDetector();
		$results  = $detector->detect();

		self::assertCount( 0, $results );
	}

	/**
	 * Test detect with only reCAPTCHA site key (no secret).
	 *
	 * @return void
	 */
	public function test_detect_with_only_recaptcha_site_key(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_publickey', '' )
			->andReturn( 'test-site-key' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_privatekey', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_site_key', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_secret_key', '' )
			->andReturn( '' );

		$detector = new PaidMembershipProDetector();
		$results  = $detector->detect();

		self::assertCount( 0, $results );
	}

	/**
	 * Test detect with whitespace-only keys.
	 *
	 * @return void
	 */
	public function test_detect_with_whitespace_keys(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_publickey', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_recaptcha_privatekey', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_site_key', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'pmpro_cloudflare_turnstile_secret_key', '' )
			->andReturn( '   ' );

		$detector = new PaidMembershipProDetector();
		$results  = $detector->detect();

		self::assertCount( 0, $results );
	}
}
