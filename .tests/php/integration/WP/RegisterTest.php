<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Register;
use WP_Error;

/**
 * Class RegisterTest.
 *
 * @group wp-register
 * @group wp
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_SERVER['REQUEST_URI'], $_GET['action'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'register_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'registration_errors', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'register';

		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_registration', 'hcaptcha_registration_nonce', true, false );

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not WP login url.
	 */
	public function test_add_captcha_when_NOT_login_url() {
		unset( $_SERVER['REQUEST_URI'] );

		$_GET['action'] = 'register';

		$expected = '';

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not register action.
	 */
	public function test_add_captcha_when_NOT_register_action() {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'some';

		$expected = '';

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$_GET['action'] = 'register';

		$errors = new WP_Error( 'some error' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$_GET['action'] = 'register';

		$errors = new WP_Error( 'some error' );

		$errors->add( 'invalid_captcha', '<strong>Error</strong>: The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration', false );

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Test verify() not register action.
	 */
	public function test_verify_when_NOT_register_action() {
		$_GET['action'] = 'some';

		$errors = new WP_Error( 'some error' );

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}
}
