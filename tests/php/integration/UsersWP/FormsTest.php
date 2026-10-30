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
	 * Ignore unrelated templates and actions across UsersWP forms.
	 */
	public function test_other_templates_and_actions_are_ignored(): void {
		$original = [ 'existing' ];
		$level    = ob_get_level();

		foreach ( [ new ForgotPassword(), new Login(), new Register() ] as $subject ) {
			$subject->uwp_template_before( 'other' );
			$subject->uwp_template_after( 'other' );

			self::assertSame( $level, ob_get_level() );
			self::assertSame( $original, $subject->verify( $original, 'other', [] ) );
		}

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		try {
			self::assertSame( $original, ( new Login() )->verify( $original, 'login', [] ) );
		} finally {
			remove_filter( 'hcap_login_limit_exceeded', '__return_false' );
		}
	}

	/**
	 * A valid native UsersWP login proof preserves the original result.
	 */
	public function test_valid_login_proof_is_accepted(): void {
		$this->prepare_verify_post( 'hcaptcha_users_wp_login_nonce', 'hcaptcha_users_wp_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'userswp/userswp.php' ],
				'form_id' => 'login',
			]
		);
		$original                              = [ 'existing' ];

		self::assertSame( $original, ( new Login() )->verify( $original, 'login', [] ) );
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
		$this->prepare_verify_post( 'hcaptcha_users_wp_register_nonce', 'hcaptcha_users_wp_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'users_wp_status', [ 'forgot', 'login', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'userswp/userswp.php' ],
				'form_id' => 'register',
			]
		);

		$native = [ 'email' => $email ];
		$result = ( new Register() )->verify( $native, 'register', [ 'email' => 'safe@example.com' ] );

		if ( $blocked ) {
			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( '<strong>hCaptcha error:</strong> ' . hcap_get_error_messages()['disposable-email'], $result->get_error_message() );
		} else {
			self::assertSame( $native, $result );
		}
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
