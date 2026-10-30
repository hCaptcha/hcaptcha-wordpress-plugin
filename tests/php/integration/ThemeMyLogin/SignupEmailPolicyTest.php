<?php
/**
 * SignupEmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ThemeMyLogin;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\ThemeMyLogin\Signup;
use HCaptcha\ThemeMyLogin\LostPassword;
use WP_Error;

/**
 * Test the native Theme My Login signup and recovery callbacks.
 *
 * @group theme-my-login
 */
class SignupEmailPolicyTest extends HCaptchaWPTestCase {
	/**
	 * Render only native signup submit fields, preserving existing button markup.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_signup_form_render_boundaries(): void {
		$subject = new Signup();

		self::assertSame( [ 'errors' => false ], $subject->verify( [ 'errors' => false ] ) );
		self::assertSame( 'button', $subject->add_captcha( 'button', 'user_signup', 'submit', null ) );
		do_action( 'tml_render_form' );
		self::assertSame( 'button', $subject->add_captcha( 'button', 'other', 'submit', null ) );
		self::assertSame( 'button', $subject->add_captcha( 'button', 'user_signup', 'other', null ) );
		self::assertSame( 'button', $subject->add_captcha( 'button', 'blog_signup', 'submit', null ) );

		hcaptcha()->settings()->set( 'theme_my_login_status', [ 'signup' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		$output = $subject->add_captcha( 'button', 'user_signup', 'submit', null );

		self::assertStringContainsString( 'name="hcaptcha_theme_my_login_signup_nonce"', $output );
		self::assertStringEndsWith( 'button', $output );
	}

	/**
	 * Validate the user signup stage before the optional blog stage.
	 *
	 * @param bool $valid Whether the submitted proof is accepted.
	 *
	 * @dataProvider dp_signup_proof
	 */
	public function test_user_signup_stage( bool $valid ): void {
		$previous_modules                                   = hcaptcha()->modules;
		hcaptcha()->modules['Theme My Login Native Signup'] = [
			[ 'theme_my_login_status', 'signup' ],
			'theme-my-login/theme-my-login.php',
			Signup::class,
		];
		$this->prepare_verify_post( 'hcaptcha_theme_my_login_signup_nonce', 'hcaptcha_theme_my_login_signup', $valid );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['stage']                        = 'validate-user-signup';
		// Mark the native entrypoint without executing its redirecting HTTP handler.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['tml_action_ajax_signup'] = 1;
		$subject = new Signup();

		try {
			$result = apply_filters(
				'wpmu_validate_user_signup',
				[
					'user_name'  => 'nativeuser',
					'user_email' => 'native@example.com',
					'errors'     => false,
				]
			);
			self::assertInstanceOf( WP_Error::class, $result['errors'] );
			self::assertSame( ! $valid, $result['errors']->has_errors() );
			self::assertSame( ! $valid, '' !== $subject->get_error_html() );
		} finally {
			remove_filter( 'wpmu_validate_user_signup', [ $subject, 'verify' ], 0 );
			remove_filter( 'wpmu_validate_blog_signup', [ $subject, 'verify' ], 0 );
			unset( $GLOBALS['wp_actions']['tml_action_ajax_signup'] );
			hcaptcha()->modules = $previous_modules;
		}
	}

	/**
	 * Valid and invalid native proof results.
	 *
	 * @return array
	 */
	public function dp_signup_proof(): array {
		return [
			'valid'   => [ true ],
			'invalid' => [ false ],
		];
	}

	/**
	 * Combined signup carries validated native email to the final blog callback.
	 *
	 * @param bool $blocked Whether the native validated email is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 */
	public function test_combined_signup_email_policy( bool $blocked ): void {
		$previous_modules                                   = hcaptcha()->modules;
		hcaptcha()->modules['Theme My Login Native Signup'] = [
			[ 'theme_my_login_status', 'signup' ],
			'theme-my-login/theme-my-login.php',
			Signup::class,
		];
		$this->prepare_verify_post( 'hcaptcha_theme_my_login_signup_nonce', 'hcaptcha_theme_my_login_signup' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['stage']                        = 'validate-blog-signup';
		$_POST['user_email']                   = 'forged@allowed.test';
		$email                                 = $blocked ? 'native@blocked.test' : 'native@allowed.test';
		$checked                               = [];
		// Mark the native entrypoint without executing its redirecting HTTP handler.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['tml_action_signup'] = 1;
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'native@blocked.test' === $address;
			},
			10,
			2
		);
		$subject = new Signup();

		try {
			$user_result = [
				'user_name'  => 'nativeuser',
				'user_email' => $email,
				'errors'     => new WP_Error(),
			];
			self::assertSame( $user_result, apply_filters( 'wpmu_validate_user_signup', $user_result ) );
			$result = apply_filters(
				'wpmu_validate_blog_signup',
				[
					'domain' => 'native.test',
					'errors' => new WP_Error(),
				]
			);
			self::assertSame( $blocked, $result['errors']->has_errors() );
			self::assertSame( [ $email ], $checked );
			self::assertSame( $blocked, '' !== $subject->get_error_html() );
		} finally {
			remove_filter( 'wpmu_validate_user_signup', [ $subject, 'verify' ], 0 );
			remove_filter( 'wpmu_validate_blog_signup', [ $subject, 'verify' ], 0 );
			unset( $GLOBALS['wp_actions']['tml_action_signup'] );
			hcaptcha()->modules = $previous_modules;
		}
	}

	/**
	 * The shared recovery hook accepts the resolved native recipient.
	 *
	 * @param bool $blocked Whether the stored recipient is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 */
	public function test_recovery_callback_email_policy( bool $blocked ): void {
		$email = $blocked ? 'native@blocked.test' : 'native@allowed.test';
		$user  = get_userdata( $this->factory()->user->create( [ 'user_email' => $email ] ) );
		$this->prepare_verify_post( 'hcaptcha_theme_my_login_lost_password_nonce', 'hcaptcha_theme_my_login_lost_password' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['user_login']                   = $user->user_login;
		$_POST['email']                        = 'forged@allowed.test';
		$_SERVER['REQUEST_METHOD']             = 'POST';
		$checked                               = [];
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'native@blocked.test' === $address;
			},
			10,
			2
		);
		new LostPassword();
		$errors = new WP_Error();
		// The native TML handler invokes this WordPress hook inside its action.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'][] = 'tml_action_lostpassword';

		try {
			do_action( 'lostpassword_post', $errors, $user );
		} finally {
			array_pop( $GLOBALS['wp_current_filter'] );
			unset( $_SERVER['REQUEST_METHOD'] );
		}

		self::assertSame( $blocked, $errors->has_errors() );
		self::assertSame( [ $email ], $checked );
	}

	/**
	 * Native recipient policy outcomes.
	 *
	 * @return array
	 */
	public function dp_email_policy(): array {
		return [
			'blocked recipient' => [ true ],
			'allowed recipient' => [ false ],
		];
	}
}
