<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Register;
use WPS\WPS_Hide_Login\Plugin;
use WP_Error;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class RegisterTest.
 *
 * @group wp-register
 * @group wp
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_SERVER['REQUEST_URI'], $_GET['action'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
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
	public function test_add_captcha(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'register';

		$args     = [
			'action' => 'hcaptcha_registration',
			'name'   => 'hcaptcha_registration_nonce',
			'id'     => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'register',
			],
		];
		$expected = $this->get_hcap_form( $args );

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'perfmatters_login_url' !== $function_name;
			}
		);
		FunctionMocker::replace(
			'class_exists',
			static function ( $function_name ) {
				return Plugin::class !== $function_name;
			}
		);

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not a WP login url.
	 */
	public function test_add_captcha_when_NOT_login_url(): void {
		$_SERVER['REQUEST_URI'] = '';
		$_GET['action']         = 'register';

		$expected = '';

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not register action.
	 */
	public function test_add_captcha_when_NOT_register_action(): void {
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
	public function test_verify(): void {
		$_GET['action'] = 'register';

		$errors = new WP_Error( 'some error' );

		$this->prepare_verify_post_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$_GET['action'] = 'register';

		$errors = new WP_Error( 'some error' );

		$errors->add( 'invalid_captcha', '<strong>Error</strong>: The Captcha is invalid.' );

		$this->prepare_verify_post_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration', false );

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Test verify() not register action.
	 */
	public function test_verify_when_NOT_register_action(): void {
		$_GET['action'] = 'some';

		$errors = new WP_Error( 'some error' );

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}
}
