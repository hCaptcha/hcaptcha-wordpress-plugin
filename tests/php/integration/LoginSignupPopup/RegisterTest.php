<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\LoginSignupPopup;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\LoginSignupPopup\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test the Login/Signup Popup registration integration.
 *
 * @group login-signup-popup
 * @group login-signup-popup-register
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
		hcaptcha()->settings()->set( 'login_signup_popup_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the registration form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$subject = new Register();

		ob_start();
		$subject->form_start( 'register', [] );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		echo '<form><button type="submit">Register</button></form>';

		$subject->add_login_signup_popup_hcaptcha( 'register', [] );
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'easy-login-woocommerce/xoo-el-main.php' ],
			'form_id' => 'register',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Login forms do not start a registration buffer or render registration hCaptcha.
	 */
	public function test_other_form_is_ignored(): void {
		$subject = new Register();
		$level   = ob_get_level();
		$subject->form_start( 'login', [] );
		$subject->add_login_signup_popup_hcaptcha( 'login', [] );

		self::assertSame( $level, ob_get_level() );
	}

	/**
	 * Registration verification wraps a missing native error container.
	 */
	public function test_verify_without_native_error(): void {
		$this->prepare_verify_post( 'hcaptcha_login_signup_popup_register_nonce', 'hcaptcha_login_signup_popup_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-login-woocommerce/xoo-el-main.php' ],
				'form_id' => 'register',
			]
		);

		$result = ( new Register() )->verify( null, 'user', 'password', 'person@example.com' );
		self::assertFalse( $result->has_errors() );
	}

	/**
	 * Registration frontend assets are available to the native popup form.
	 */
	public function test_frontend_assets(): void {
		$subject = new Register();
		ob_start();
		$subject->print_inline_styles();
		self::assertStringContainsString( 'div[data-section="register"] .h-captcha', ob_get_clean() );

		try {
			$subject->enqueue_scripts();
			$script = wp_scripts()->registered['hcaptcha-login-signup-popup'];
			self::assertStringContainsString( 'hcaptcha-login-signup-popup', $script->src );
			self::assertSame( HCAPTCHA_VERSION, $script->ver );
		} finally {
			wp_dequeue_script( 'hcaptcha-login-signup-popup' );
			wp_deregister_script( 'hcaptcha-login-signup-popup' );
		}
	}

	/**
	 * Test a filled honeypot blocks registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_login_signup_popup_register_nonce',
			'hcaptcha_login_signup_popup_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-login-woocommerce/xoo-el-main.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$result = ( new Register() )->verify( new WP_Error(), 'user', 'password', 'person@example.com' );

		self::assertSame( 'Anti-spam check failed.', $result->get_error_message( 'spam' ) );
	}

	/**
	 * Check the authoritative native email through the production API.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native address.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['email'] = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_login_signup_popup_register_nonce', 'hcaptcha_login_signup_popup_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'login_signup_popup_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-login-woocommerce/xoo-el-main.php' ],
				'form_id' => 'register',
			]
		);

		$result = ( new Register() )->verify( new WP_Error(), 'guest', '', $email );

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : '', $result->get_error_message() );
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
