<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\LostPassword;
use WP_Error;

/**
 * LostPasswordTest class.
 *
 * @group wp-lost-password
 * @group wp
 */
class LostPasswordTest extends HCaptchaWPTestCase {

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
		$subject = new LostPassword();

		self::assertSame(
			10,
			has_action( 'lostpassword_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'lostpassword_post', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'lostpassword';

		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_wp_lost_password', 'hcaptcha_wp_lost_password_nonce', true, false );

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not WP login url.
	 */
	public function test_add_captcha_when_NOT_wp_login_url() {
		unset( $_SERVER['REQUEST_URI'] );

		$_GET['action'] = 'lostpassword';

		$expected = '';

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not WP login action.
	 */
	public function test_add_captcha_when_NOT_wp_login_action() {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';

		$expected = '';

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$validation_error   = new WP_Error( 'some error' );
		$expected           = clone $validation_error;
		$_POST['wp-submit'] = 'some';

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password' );

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$validation_error   = new WP_Error( 'some error' );
		$expected           = clone $validation_error;
		$_POST['wp-submit'] = 'some';

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password', false );

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() when not proper post key.
	 */
	public function test_verify_when_NOT_proper_post_key() {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}
}
