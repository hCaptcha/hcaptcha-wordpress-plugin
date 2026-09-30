<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\MemberPress;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\MemberPress\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Register class.
 *
 * @group memberpress
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'memberpress_status', [ 'login', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'mepr-checkout-before-submit', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'mepr-validate-signup', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$subject = new Register();

		$expected = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_memberpress_register',
				'name'   => 'hcaptcha_memberpress_register_nonce',
				'id'     => [
					'source'  => [ 'memberpress/memberpress.php' ],
					'form_id' => 'register',
				],
			]
		);

		ob_start();
		$subject->add_captcha();
		$output = (string) ob_get_clean();

		self::assertSame( $expected, $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$subject = new Register();

		$errors = [ 'some errors' ];

		$this->prepare_verify_post(
			'hcaptcha_memberpress_register_nonce',
			'hcaptcha_memberpress_register'
		);

		self::assertSame( $errors, $subject->verify( $errors ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify_no_success(): void {
		$subject = new Register();

		$errors        = [ 'some errors' ];
		$error_message = array_merge( $errors, [ 'The hCaptcha is invalid.' ] );

		$this->prepare_verify_post(
			'hcaptcha_memberpress_register_nonce',
			'hcaptcha_memberpress_register',
			false
		);

		self::assertSame( $error_message, $subject->verify( $errors ) );
	}

	/**
	 * Test verify() with a filled honeypot.
	 *
	 * @return void
	 */
	public function test_verify_filled_honeypot(): void {
		$this->prepare_verify_post(
			'hcaptcha_memberpress_register_nonce',
			'hcaptcha_memberpress_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'memberpress/memberpress.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		self::assertSame(
			[ 'Anti-spam check failed.' ],
			( new Register() )->verify( [] )
		);
	}
}
