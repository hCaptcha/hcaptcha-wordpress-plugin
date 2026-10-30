<?php
/**
 * RegisterEmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ThemeMyLogin;

use HCaptcha\ThemeMyLogin\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Exercise the native email policy through the production API.
 */
class RegisterEmailPolicyTest extends HCaptchaWPTestCase {
	/**
	 * Do not render a registration challenge outside the native form action.
	 */
	public function test_add_captcha_outside_form_render(): void {
		ob_start();
		( new Register() )->add_captcha();

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Create a WordPress error object when the native handler passes a scalar result.
	 */
	public function test_verify_with_scalar_error_result(): void {
		$this->prepare_verify_post( 'hcaptcha_theme_my_login_register_nonce', 'hcaptcha_theme_my_login_register', false );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'register',
			]
		);

		$subject = new Register();
		$result  = null;
		add_action(
			'tml_action_register',
			static function () use ( $subject, &$result ): void {
				$result = $subject->verify( false, 'guest', 'guest@example.com' );
			}
		);
		do_action( 'tml_action_register' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_captcha', $result->get_error_code() );
	}

	/**
	 * Prepare deterministic form protection.
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'theme_my_login_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
	}

	/**
	 * Reject only the authoritative native address when disposable.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native address.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		$_POST['email'] = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_theme_my_login_register_nonce', 'hcaptcha_theme_my_login_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'theme_my_login_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'register',
			]
		);

		$subject = new Register();
		$result  = new WP_Error();
		add_action(
			'tml_action_register',
			static function () use ( $subject, $email, &$result ): void {
				$result = $subject->verify( $result, 'guest', $email );
			}
		);
		do_action( 'tml_action_register' );

		self::assertSame( $blocked ? '<strong>hCaptcha error:</strong> ' . hcap_get_error_messages()['disposable-email'] : '', $result->get_error_message() );
	}

	/**
	 * Native address controls.
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

	/**
	 * Reusing AJAX proof must recheck native email policy and signed identity.
	 *
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_ajax_cached_proof_retains_email_and_identity_checks(): void {
		$this->prepare_verify_post( 'hcaptcha_theme_my_login_register_nonce', 'hcaptcha_theme_my_login_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'theme_my_login_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'register',
			]
		);
		$remote_calls                          = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) use ( &$remote_calls ) {
				if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
					++$remote_calls;
				}

				return $preempt;
			},
			5,
			3
		);

		$subject = new Register();
		$results = [];
		remove_all_actions( 'tml_action_ajax_register' );
		add_action(
			'tml_action_ajax_register',
			static function () use ( $subject, &$results ): void {
				foreach ( [ 'safe@example.com', 'native@disposable.example', 'safe@example.com' ] as $email ) {
					$results[] = $subject->verify( new WP_Error(), 'guest', $email );
				}

				$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
					[
						'source'  => [ 'theme-my-login/theme-my-login.php' ],
						'form_id' => 'other-form',
					]
				);
				$results[]                             = $subject->verify( new WP_Error(), 'guest', 'safe@example.com' );
			}
		);
		do_action( 'tml_action_ajax_register' );

		self::assertSame( [], $results[0]->get_error_codes() );
		self::assertSame( [ 'invalid_captcha' ], $results[1]->get_error_codes() );
		self::assertSame( '<strong>hCaptcha error:</strong> ' . hcap_get_error_messages()['disposable-email'], $results[1]->get_error_message() );
		self::assertSame( [], $results[2]->get_error_codes() );
		self::assertSame( '<strong>hCaptcha error:</strong> ' . hcap_get_error_messages()['bad-signature'], $results[3]->get_error_message() );
		self::assertSame( 1, $remote_calls );
	}
}
