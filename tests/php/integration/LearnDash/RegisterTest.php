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
	 * Reject an unknown owner and defer a widget owned by another verifier.
	 */
	public function test_verify_request_ownership(): void {
		$subject = new Register();
		$errors  = new WP_Error();

		$_POST['learndash-registration-form']  = '1';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = 'invalid';

		self::assertSame( 'Bad hCaptcha signature!', $subject->verify( $errors, '', '' )->get_error_message( 'bad-signature' ) );

		$this->prepare_widget_id();
		$errors      = new WP_Error();
		$other_owner = static function (): string {
			return WPRegister::class;
		};
		add_filter( 'hcap_registration_request_owner', $other_owner );

		try {
			self::assertSame( $errors, $subject->verify( $errors, '', '' ) );
		} finally {
			remove_filter( 'hcap_registration_request_owner', $other_owner );
		}
	}

	/**
	 * Publish native registration errors and the form spacing rule.
	 */
	public function test_registration_errors_and_inline_styles(): void {
		$subject = new Register();

		self::assertArrayHasKey( 'bad-signature', $subject->add_registration_errors( [] ) );

		ob_start();
		$subject->print_inline_styles();

		self::assertStringContainsString( '#learndash_registerform .h-captcha', ob_get_clean() );
	}

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'learn_dash_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

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
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
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

		$_POST['action']                      = 'register';
		$_POST['learndash-registration-form'] = '1';
		$this->prepare_widget_id();

		self::assertSame( $errors, $wp_register->verify( $errors, '', '' ) );
		self::assertSame( [ 'some-code' ], $errors->get_error_codes() );
	}

	/**
	 * Test a foreign signature cannot suppress native registration without its marker.
	 *
	 * @dataProvider dp_test_callback_orders
	 *
	 * @param bool $native_first Whether the native verifier is registered first.
	 */
	public function test_foreign_owner_without_marker( bool $native_first ): void {
		if ( $native_first ) {
			new WPRegister();
			new Register();
		} else {
			new Register();
			new WPRegister();
		}

		$_POST['action'] = 'register';
		$this->prepare_widget_id();

		$errors = apply_filters( 'registration_errors', new WP_Error(), 'guest', 'guest@example.com' );

		self::assertSame( 'Bad hCaptcha signature!', $errors->get_error_message( 'bad-signature' ) );
	}

	/**
	 * Test a registered LearnDash owner still rejects a missing challenge.
	 *
	 * @dataProvider dp_test_callback_orders
	 *
	 * @param bool $native_first Whether the native verifier is registered first.
	 */
	public function test_owner_with_marker_requires_challenge( bool $native_first ): void {
		if ( $native_first ) {
			new WPRegister();
			new Register();
		} else {
			new Register();
			new WPRegister();
		}

		$_POST['action']                      = 'register';
		$_POST['learndash-registration-form'] = '1';
		$this->prepare_widget_id();
		$this->prepare_verify_post( 'hcaptcha_learn_dash_register_nonce', 'hcaptcha_learn_dash_register' );
		$_POST['h-captcha-response'] = '';

		$errors = apply_filters( 'registration_errors', new WP_Error(), 'guest', 'guest@example.com' );

		self::assertSame( 'Please complete the hCaptcha.', $errors->get_error_message( 'empty' ) );
		self::assertSame( '', $errors->get_error_message( 'bad-signature' ) );
	}

	/**
	 * Test unmatched AutoVerify cannot defer to LearnDash without its marker.
	 */
	public function test_auto_verify_requires_owner_marker(): void {
		$_POST['action']                       = 'register';
		$widget_id                             = HCaptcha::widget_id_value(
			[
				'source'  => [ 'sfwd-lms/sfwd_lms.php' ],
				'form_id' => 'register',
			]
		);
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $widget_id;
		$subject                               = new Register();
		$form                                  = [ 'widget_id' => 'different' ];
		$path                                  = untrailingslashit( (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) );

		self::assertSame(
			$form,
			$subject->defer_auto_verification( $form, $path, $widget_id )
		);
	}

	/**
	 * Callback order variants.
	 *
	 * @return array
	 */
	public function dp_test_callback_orders(): array {
		return [
			'native first' => [ true ],
			'owner first'  => [ false ],
		];
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
	 * Test a filled honeypot blocks LearnDash registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$subject = new Register();
		$errors  = new WP_Error();

		$_POST['learndash-registration-form'] = '1';
		$this->prepare_widget_id();
		$this->prepare_verify_post( 'hcaptcha_learn_dash_register_nonce', 'hcaptcha_learn_dash_register' );
		$_POST['hcap_hp_test'] = 'bot';

		$result = $subject->verify( $errors, '', '' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Anti-spam check failed.', $result->get_error_message( 'spam' ) );
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

	/**
	 * Check the native callback email instead of arbitrary submitted aliases.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native email.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['learndash-registration-form'] = '1';
		$_POST['email']                       = 'safe@example.com';
		$this->prepare_widget_id();
		$this->prepare_verify_post( 'hcaptcha_learn_dash_register_nonce', 'hcaptcha_learn_dash_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'learn_dash_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );

		$result = ( new Register() )->verify( new WP_Error(), 'guest', $email );

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : '', $result->get_error_message( 'disposable-email' ) );
	}

	/**
	 * Native email policy controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable email' => [ 'user@disposable.example', true ],
			'permanent email'  => [ 'safe@example.com', false ],
			'email-free data'  => [ '', false ],
		];
	}
}
