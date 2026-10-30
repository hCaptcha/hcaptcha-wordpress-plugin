<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\SimpleMembership;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\SimpleMembership\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Simple Membership registration integration.
 *
 * @group simple-membership
 * @group simple-membership-register
 */
class RegisterTest extends HCaptchaWPTestCase {
	/**
	 * Leave unrelated shortcodes alone and print registration spacing.
	 */
	public function test_other_shortcode_and_inline_styles(): void {
		$subject = new Register();

		self::assertSame( 'other', $subject->add_hcaptcha( 'other', 'other', [], [] ) );

		ob_start();
		$subject->print_inline_styles();

		self::assertStringContainsString( '#swpm-registration-form .h-captcha', ob_get_clean() );
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
		hcaptcha()->settings()->set( 'simple_membership_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the registration form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$output = ( new Register() )->add_hcaptcha(
			'<form><div class="swpm-form-row swpm-submit-section swpm-registration-submit-section"></div></form>',
			'swpm_registration_form',
			[],
			[]
		);
		$id     = [
			'source'  => [ 'simple-membership/simple-wp-membership.php' ],
			'form_id' => 'register',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test a filled honeypot blocks registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_simple_membership_register_nonce',
			'hcaptcha_simple_membership_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'simple-membership/simple-wp-membership.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		self::assertSame( 'Anti-spam check failed.', ( new Register() )->verify() );
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
		$_POST['email']      = $email;
		$_POST['user_email'] = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_simple_membership_register_nonce', 'hcaptcha_simple_membership_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'simple_membership_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'simple-membership/simple-wp-membership.php' ],
				'form_id' => 'register',
			]
		);

		$result = ( new Register() )->verify();

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : '', $result );
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
