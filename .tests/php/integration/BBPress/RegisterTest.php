<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\Register;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use WP_Error;

/**
 * Test Register class.
 *
 * @group bbpress
 * @group bbpress-register
 */
class RegisterTest extends HCaptchaPluginWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		hcaptcha()->settings()->set( 'bbp_status', 'register' );

		$subject = new Register();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_filter( 'registration_errors', [ $subject, 'verify' ] ) );

		hcaptcha()->settings()->set( 'bbp_status', 'some' );

		$subject = new Register();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_filter( 'hcap_protect_form', [ $subject, 'hcap_protect_form' ] ) );
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$tag  = 'bbp-register';
		$attr = [];
		$m    = [];

		$placeholder = '===hcaptcha placeholder===';
		$template    = <<<HTML
<form action="https://test.test/wp-login.php">
		$placeholder<button type="submit"/>
</form>
HTML;

		$args     = [
			'action' => 'hcaptcha_bbp_register',
			'name'   => 'hcaptcha_bbp_register_nonce',
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'register',
			],
		];
		$hcaptcha = $this->get_hcap_form( $args );

		$output   = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha . "\n", $template );

		hcaptcha()->settings()->set( 'bbp_status', 'register' );
		$subject = new Register();

		// Wrong tag.
		self::assertSame( $output, $subject->add_captcha( $output, 'some', $attr, $m ) );

		// Logged in.
		wp_set_current_user( 1 );

		self::assertSame( $output, $subject->add_captcha( $output, $tag, $attr, $m ) );

		// Status is 'register'.
		wp_set_current_user( 0 );

		$subject = new Register();

		self::assertSame( $expected, $subject->add_captcha( $output, $tag, $attr, $m ) );

		// Status is wrong.
		$hcaptcha = $this->get_hcap_widget( $args['id'] );
		$expected = str_replace( $placeholder, $hcaptcha . "\n\t\t\n", $template );

		hcaptcha()->settings()->set( 'bbp_status', 'some' );
		$subject = new Register();

		self::assertSame( $expected, $subject->add_captcha( $output, $tag, $attr, $m ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$sanitized_user_login = 'login';
		$user_email           = 'email';
		$errors               = new WP_Error();
		$expected             = new WP_Error();

		$errors->add( 'some code', 'some message' );
		$expected->add( 'some code', 'some message' );

		$this->prepare_verify_post( 'hcaptcha_bbp_register_nonce', 'hcaptcha_bbp_register' );

		$subject = new Register();

		self::assertEquals( $expected, $subject->verify( $errors, $sanitized_user_login, $user_email ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_when_not_verified(): void {
		$sanitized_user_login = 'login';
		$user_email           = 'email';
		$errors               = new WP_Error();
		$expected             = new WP_Error();

		$errors->add( 'some code', 'some message' );
		$expected->add( 'some code', 'some message' );
		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$subject = new Register();

		$this->prepare_verify_post( 'hcaptcha_bbp_register_nonce', 'hcaptcha_bbp_register', false );

		self::assertEquals( $expected, $subject->verify( $errors, $sanitized_user_login, $user_email ) );
	}

	/**
	 * Test hcap_protect_form().
	 *
	 * @return void
	 */
	public function test_hcap_protect_form(): void {
		$source  = [ 'bbpress/bbpress.php' ];
		$form_id = 'register';

		$subject = new Register();

		self::assertTrue( $subject->hcap_protect_form( true, [ 'some source' ], $form_id ) );
		self::assertTrue( $subject->hcap_protect_form( true, $source, 'some form id' ) );
		self::assertFalse( $subject->hcap_protect_form( true, $source, $form_id ) );
	}
}
