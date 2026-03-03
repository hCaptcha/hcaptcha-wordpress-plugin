<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\PaidMembershipsPro;

use HCaptcha\PaidMembershipsPro\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Login class.
 *
 * @group paid-memberships-pro
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset(
			$_POST['log'],
			$_POST['pwd'],
			$_SERVER['REMOTE_ADDR'],
			$_GET['action']
		);

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Login();

		self::assertSame(
			10,
			has_filter( 'pmpro_pages_custom_template_path', [ $subject, 'filter_custom_template_path' ] )
		);
		self::assertSame(
			10,
			has_filter( 'pmpro_pages_shortcode_login', [ $subject, 'add_pmpro_captcha' ] )
		);

		// LoginBase hooks.
		self::assertSame( 10, has_action( 'hcap_signature', [ $subject, 'display_signature' ] ) );
		self::assertSame( 10, has_action( 'wp_login', [ $subject, 'login' ] ) );
		self::assertSame( 10, has_action( 'wp_login_failed', [ $subject, 'login_failed' ] ) );
	}

	/**
	 * Test filter_custom_template_path() with the login page.
	 *
	 * @return void
	 */
	public function test_filter_custom_template_path_login(): void {
		$default_templates = [ '/some/path' ];

		$subject = new Login();

		$result = $subject->filter_custom_template_path( $default_templates, 'login' );

		self::assertSame( $default_templates, $result );
	}

	/**
	 * Test filter_custom_template_path() with a non-login page.
	 *
	 * @return void
	 */
	public function test_filter_custom_template_path_not_login(): void {
		$default_templates = [ '/some/path' ];

		$subject = new Login();

		$result = $subject->filter_custom_template_path( $default_templates, 'checkout' );

		self::assertSame( $default_templates, $result );
	}

	/**
	 * Test add_pmpro_captcha() when the login limit is not exceeded.
	 *
	 * @return void
	 */
	public function test_add_pmpro_captcha_not_exceeded(): void {
		$content = '<div class="pmpro_card pmpro_login_wrap"><p class="login-submit">Submit</p></div>';

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$subject = new Login();

		$result = $subject->add_pmpro_captcha( $content );

		self::assertSame( $content, $result );
	}

	/**
	 * Test add_pmpro_captcha() with the login limit exceeded.
	 *
	 * @return void
	 */
	public function test_add_pmpro_captcha(): void {
		$content = '<div class="pmpro_card pmpro_login_wrap"><p class="login-submit">Submit</p></div>';

		update_option( 'hcaptcha_settings', [ 'paid_memberships_pro_status' => [ 'login' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Login();

		$result = $subject->add_pmpro_captcha( $content );

		self::assertStringContainsString( 'h-captcha', $result );
		self::assertStringContainsString( '<p class="login-submit">', $result );
	}

	/**
	 * Test add_pmpro_captcha() with an error message.
	 *
	 * @return void
	 */
	public function test_add_pmpro_captcha_with_error(): void {
		$content = '<div class="pmpro_card pmpro_login_wrap"><p class="login-submit">Submit</p></div>';

		$_GET['action'] = 'fail';

		update_option( 'hcaptcha_settings', [ 'paid_memberships_pro_status' => [ 'login' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Login();

		$result = $subject->add_pmpro_captcha( $content );

		self::assertStringContainsString( 'pmpro_message pmpro_error', $result );
		self::assertStringContainsString( '<div class="pmpro_card pmpro_login_wrap">', $result );
	}

	/**
	 * Test add_pmpro_captcha() when paid_memberships_pro_status is not login.
	 *
	 * @return void
	 */
	public function test_add_pmpro_captcha_status_not_login(): void {
		$content = '<div class="pmpro_card pmpro_login_wrap"><p class="login-submit">Submit</p></div>';

		update_option( 'hcaptcha_settings', [ 'paid_memberships_pro_status' => [ 'checkout' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Login();

		$result = $subject->add_pmpro_captcha( $content );

		// No hcaptcha but signatures are still added.
		self::assertStringNotContainsString( 'h-captcha', $result );
		self::assertStringContainsString( '<p class="login-submit">', $result );
	}
}
