<?php
/**
 * PasswordProtectedTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\PasswordProtected;
use WP_Post;

/**
 * Class PasswordProtectedTest.
 *
 * @group wp-password-protected
 * @group wp
 */
class PasswordProtectedTest extends HCaptchaWPTestCase {

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
		$subject = new PasswordProtected();

		self::assertSame(
			PHP_INT_MAX,
			has_filter( 'the_password_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'login_form_postpass', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'register';

		$output = '<p class="post-password-message">This content is password protected. Please enter a password to view.</p>
	<form action="https://test.test/wp-login.php?action=postpass" class="post-password-form" method="post">
	<label class="post-password-form__label" for="pwbox-2478">Password</label><input class="post-password-form__input" name="post_password" id="pwbox-2478" type="password" spellcheck="false" size="20" /><input type="submit" class="post-password-form__submit" name="Submit" value="Enter" /></form>
	';

		$post = new WP_Post( (object) [] );

		$search    = '</form>';
		$args      = [
			'action' => 'hcaptcha_password_protected',
			'name'   => 'hcaptcha_password_protected_nonce',
			'id'     => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'password_protected',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$replace   = $hcap_form . $search;
		$expected  = str_replace( $search, $replace, $output );

		$subject = new PasswordProtected();

		self::assertSame( $expected, $subject->add_captcha( $output, $post ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_hcaptcha_verify_post( 'hcaptcha_password_protected_nonce', 'hcaptcha_password_protected' );

		$subject = new PasswordProtected();

		$subject->verify();
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUnusedParameterInspection*/
	public function test_verify_not_verified(): void {
		$die_arr  = [];
		$expected = [
			'The hCaptcha is invalid.',
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 303,
			],
		];

		$this->prepare_hcaptcha_verify_post( 'hcaptcha_password_protected_nonce', 'hcaptcha_password_protected', false );

		$subject = new PasswordProtected();

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify();

		self::assertSame( $expected, $die_arr );
	}
}
