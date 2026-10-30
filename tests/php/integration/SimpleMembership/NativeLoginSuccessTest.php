<?php
/**
 * NativeLoginSuccessTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\SimpleMembership;

use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\SimpleMembership\Login;
use HCaptcha\SimpleMembership\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionClass;
use RuntimeException;
use SimpleWpMembership;
use SwpmAuth;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the installed Simple Membership authentication callback.
 *
 * @group simple-membership
 */
class NativeLoginSuccessTest extends HCaptchaPluginWPTestCase {
	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'simple-membership/simple-wp-membership.php';

	/**
	 * A signed-in native member clears the captured failure count.
	 */
	public function test_authenticated_member_resets_attempts(): void {
		$auth     = SwpmAuth::get_instance();
		$original = $this->get_protected_property( $auth, 'isLoggedIn' );
		$ip       = '2001:db8::' . bin2hex( random_bytes( 8 ) );
		$attempts = new LoginAttempts();
		$attempts->increment( $ip, time(), 15 * MINUTE_IN_SECONDS );
		$token   = $attempts->get_reset_token( $ip, time() );
		$subject = new Login();
		$this->set_protected_property( $subject, 'ip', $ip );
		$this->set_protected_property( $subject, 'login_attempts_reset_token', $token );
		$this->set_protected_property( $auth, 'isLoggedIn', true );
		$_POST['swpm_user_name'] = 'member';

		try {
			$subject->native_login_success_after_auth();
			self::assertSame( 0, $attempts->read( $ip, time() ) );
		} finally {
			$this->set_protected_property( $auth, 'isLoggedIn', $original );
			$attempts->reset( $ip, $attempts->get_reset_token( $ip, time() ) );
		}
	}

	/**
	 * The native reset handler receives an error instead of changing the password.
	 */
	public function test_invalid_final_reset_sets_native_error(): void {
		global $simple_membership;

		$original = $simple_membership;
		// The plugin loader includes its entry file in a local scope; bind its handler to the global used by hCaptcha.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$simple_membership = ( new ReflectionClass( SimpleWpMembership::class ) )->newInstanceWithoutConstructor();
		add_action( 'wp', [ $simple_membership, 'handle_wp_tasks' ] );
		self::assertSame( 10, has_action( 'wp', [ $simple_membership, 'handle_wp_tasks' ] ) );
		FunctionMocker::replace( 'filter_input', 'submit' );
		$this->prepare_verify_post( 'hcaptcha_simple_membership_register_nonce', 'hcaptcha_simple_membership_register', null );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]              = HCaptcha::widget_id_value(
			[
				'source'  => [ static::$plugin ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['hcaptcha_simple_membership_register_nonce'] = wp_create_nonce( 'hcaptcha_simple_membership_register' );

		try {
			( new LostPassword() )->verify_link_reset();

			self::assertSame( 'Please complete the hCaptcha.', get_transient( 'swpm-passsword-reset-error' ) );
			self::assertFalse( has_action( 'wp', [ $simple_membership, 'handle_wp_tasks' ] ) );
		} finally {
			delete_transient( 'swpm-passsword-reset-error' );
			remove_action( 'wp', [ $simple_membership, 'handle_wp_tasks' ] );
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$simple_membership = $original;
		}
	}

	/**
	 * Without a native reset handler, reject the request with an HTTP error.
	 */
	public function test_invalid_final_reset_without_native_handler(): void {
		global $simple_membership;

		$original = $simple_membership;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$simple_membership = null;
		FunctionMocker::replace( 'filter_input', 'submit' );
		$this->prepare_verify_post( 'hcaptcha_simple_membership_register_nonce', 'hcaptcha_simple_membership_register', null );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]              = HCaptcha::widget_id_value(
			[
				'source'  => [ static::$plugin ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['hcaptcha_simple_membership_register_nonce'] = wp_create_nonce( 'hcaptcha_simple_membership_register' );
		$die_handler                                        = static function () {
			return static function ( $message, $title, $args ): void {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Capture the native response in the test exception.
				throw new RuntimeException( $message . '|' . $args['response'] );
			};
		};
		add_filter( 'wp_die_handler', $die_handler );

		try {
			( new LostPassword() )->verify_link_reset();
			self::fail( 'The reset request should be rejected.' );
		} catch ( RuntimeException $e ) {
			self::assertSame( 'Please complete the hCaptcha.|403', $e->getMessage() );
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$simple_membership = $original;
		}
	}
}
