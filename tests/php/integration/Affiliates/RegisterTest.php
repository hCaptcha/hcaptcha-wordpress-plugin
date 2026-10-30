<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Affiliates;

use HCaptcha\Affiliates\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Register class.
 *
 * @group affiliates
 * @group affiliates-register
 */
class RegisterTest extends HCaptchaWPTestCase {
	/**
	 * Ignore unrelated dashboard sections and requests without a registration submit.
	 */
	public function test_ignores_other_sections_and_requests(): void {
		$subject = new Register();
		$level   = ob_get_level();

		$subject->before_section( 'other' );
		$subject->after_section( 'other' );

		self::assertSame( $level, ob_get_level() );
		self::assertTrue( $subject->verify( true ) );
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
		hcaptcha()->settings()->set( 'affiliates_status', [ 'login', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test initial registration form rendering.
	 *
	 * @return void
	 */
	public function test_initial_render(): void {
		$subject = new Register();

		ob_start();
		$subject->before_section( 'registration' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form><input type="submit" name="affiliates-registration-submit"></form>';
		$subject->after_section( 'registration' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringNotContainsString( '<div class="error">', $output );
	}

	/**
	 * Test that a filled honeypot blocks registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]   = HCaptcha::widget_id_value(
			[
				'source'  => [ 'affiliates/affiliates.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                   = 'bot';
		$_POST['affiliates-registration-submit'] = 'Register';

		$subject = new Register();

		self::assertTrue( $subject->verify( false ) );

		ob_start();
		$subject->before_section( 'registration' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form><input type="submit" name="affiliates-registration-submit"></form>';
		$subject->after_section( 'registration' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Anti-spam check failed.', $output );
	}

	/**
	 * The native registration email is used for email policy checks.
	 */
	public function test_native_registration_email_is_checked(): void {
		$settings                     = (array) get_option( 'hcaptcha_settings', [] );
		$settings['disposable_email'] = [ 'on' ];
		update_option( 'hcaptcha_settings', $settings );

		$this->prepare_verify_post( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]   = HCaptcha::widget_id_value(
			[
				'source'  => [ 'affiliates/affiliates.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['affiliates-registration-submit'] = 'Register';
		$_POST['user_email']                     = 'person@example.com';
		add_filter( 'hcap_is_disposable_email', '__return_true' );

		self::assertTrue( ( new Register() )->verify( false ) );
	}

	/**
	 * A logged-in affiliate is checked using the account address.
	 */
	public function test_logged_in_affiliate_uses_account_email(): void {
		$user_id = $this->factory()->user->create( [ 'user_email' => 'account@example.com' ] );
		wp_set_current_user( $user_id );

		$settings                     = (array) get_option( 'hcaptcha_settings', [] );
		$settings['disposable_email'] = [ 'on' ];
		update_option( 'hcaptcha_settings', $settings );

		$this->prepare_verify_post( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]   = HCaptcha::widget_id_value(
			[
				'source'  => [ 'affiliates/affiliates.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['affiliates-registration-submit'] = 'Register';
		$_POST['user_email']                     = 'forged@example.com';

		$checked = [];
		add_filter(
			'hcap_is_disposable_email',
			static function ( bool $disposable, string $email ) use ( &$checked ): bool {
				$checked[] = $email;

				return true;
			},
			10,
			2
		);

		self::assertTrue( ( new Register() )->verify( false ) );
		self::assertSame( [ 'account@example.com' ], $checked );
	}
}
