<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\LearnDash;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\LearnDash\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Register as WPRegister;
use WP_Error;

/**
 * Test Register class.
 *
 * @group learn-dash
 * @group learn-dash-register
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset(
			$_POST['action'],
			$_POST['learndash-registration-form'],
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]
		);

		parent::tearDown();
	}

	/**
	 * Test init hooks.
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Register();

		self::assertSame( 10, has_action( 'learndash_registration_form', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_filter( 'registration_errors', [ $subject, 'verify' ] ) );
		self::assertSame( 10, has_filter( 'hcap_registration_request_owner', [ $subject, 'claim_request_owner' ] ) );
		self::assertSame(
			10,
			has_filter( 'hcap_auto_verify_unmatched_form', [ $subject, 'defer_auto_verification' ] )
		);
	}

	/**
	 * Test add captcha.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$subject = new Register();

		ob_start();
		$subject->add_captcha();
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'sfwd-lms/sfwd_lms.php' ],
			'form_id' => 'register',
		];

		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
	}

	/**
	 * Test that native WordPress registration defers to LearnDash.
	 *
	 * @return void
	 */
	public function test_wordpress_register_defers_to_learndash_owner(): void {
		new Register();
		$wp_register = new WPRegister();
		$errors      = new WP_Error( 'some-code', 'some-message' );

		$_POST['action'] = 'register';
		$this->prepare_widget_id();

		self::assertSame( $errors, $wp_register->verify( $errors, '', '' ) );
	}

	/**
	 * Test verification of the LearnDash owner.
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$subject = new Register();
		$errors  = new WP_Error( 'some-code', 'some-message' );

		$_POST['learndash-registration-form'] = '1';
		$this->prepare_widget_id();
		$this->prepare_verify_post( 'hcaptcha_learn_dash_register_nonce', 'hcaptcha_learn_dash_register' );

		self::assertSame( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Prepare the LearnDash widget ID.
	 *
	 * @return void
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'sfwd-lms/sfwd_lms.php' ],
				'form_id' => 'register',
			]
		);
	}
}
