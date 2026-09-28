<?php
/**
 * FormsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UsersWP;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\UsersWP\ForgotPassword;
use HCaptcha\UsersWP\Login;
use HCaptcha\UsersWP\Register;
use WP_Error;

/**
 * Test the UsersWP integrations.
 *
 * @group users-wp
 */
class FormsTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'users_wp_status', [ 'forgot', 'login', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that rendered forms contain honeypot fields.
	 *
	 * @param string $integration_class Integration class.
	 * @param string $action            UsersWP action.
	 *
	 * @dataProvider dp_test_forms
	 * @return void
	 */
	public function test_form_contains_honeypot( string $integration_class, string $action ): void {
		$subject = new $integration_class();

		ob_start();
		$subject->uwp_template_before( $action );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form><button type="submit">Submit</button></form>';

		$subject->uwp_template_after( $action );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks UsersWP requests.
	 *
	 * @param string $integration_class Integration class.
	 * @param string $action            UsersWP action.
	 * @param string $nonce             hCaptcha nonce name.
	 * @param string $nonce_action      hCaptcha nonce action.
	 *
	 * @dataProvider dp_test_forms
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(
		string $integration_class,
		string $action,
		string $nonce,
		string $nonce_action
	): void {
		$this->prepare_verify_post( $nonce, $nonce_action );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'userswp/userswp.php' ],
				'form_id' => $action,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$result = ( new $integration_class() )->verify( [], $action, [] );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame(
			'<strong>hCaptcha error:</strong> Anti-spam check failed.',
			$result->get_error_message()
		);
	}

	/**
	 * Provide UsersWP forms.
	 *
	 * @return array<string, array{string, string, string, string}>
	 */
	public function dp_test_forms(): array {
		return [
			'forgot password' => [
				ForgotPassword::class,
				'forgot',
				'hcaptcha_users_wp_forgot_password_nonce',
				'hcaptcha_users_wp_forgot_password',
			],
			'login'           => [
				Login::class,
				'login',
				'hcaptcha_users_wp_login_nonce',
				'hcaptcha_users_wp_login',
			],
			'register'        => [
				Register::class,
				'register',
				'hcaptcha_users_wp_register_nonce',
				'hcaptcha_users_wp_register',
			],
		];
	}
}
