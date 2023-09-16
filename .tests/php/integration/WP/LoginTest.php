<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Login;
use WP_Error;
use WP_User;

/**
 * Class LoginTest.
 *
 * @group wp-login
 * @group wp
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset(
			$_POST['log'],
			$_POST['pwd'],
			$GLOBALS['wp_action']['login_init'],
			$GLOBALS['wp_action']['login_form_login'],
			$GLOBALS['wp_filters']['login_link_separator']
		);

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Login();

		self::assertSame(
			10,
			has_action( 'login_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'wp_authenticate_user', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce', true, false );

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$user = new WP_User( 1 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, '' ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$user     = new WP_User( 1 );
		$expected = new WP_Error( 'invalid_hcaptcha', '<strong>hCaptcha error:</strong> The hCaptcha is invalid.', 400 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $expected, $subject->verify( $user, '' ) );
	}
}
