<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\SimpleMembership;

use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\SimpleMembership\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test native Simple Membership adaptive login events.
 *
 * @group simple-membership
 * @group simple-membership-login
 */
class LoginTest extends HCaptchaWPTestCase {
	/**
	 * Leave unrelated and below-limit login forms unchanged.
	 */
	public function test_unrelated_and_below_limit_forms(): void {
		$subject = $this->subject();
		$form    = '<div class="swpm-login-submit"></div>';

		self::assertSame( $form, $subject->add_hcaptcha( $form, 'other', [], [] ) );
		self::assertSame( $form, $subject->add_hcaptcha( $form, 'swpm_login_form', [], [] ) );
		self::assertSame( 'native error', $subject->verify( 'native error' ) );
		ob_start();
		$subject->print_inline_styles();
		self::assertStringContainsString( '#swpm-login-form .h-captcha', ob_get_clean() );
	}


	/**
	 * Isolated failed-login address.
	 *
	 * @var string
	 */
	private string $ip;

	/**
	 * Saved post data.
	 *
	 * @var array
	 */
	private array $original_post;

	/**
	 * Prepare a native login request with an isolated counter.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ip = '2001:db8::' . bin2hex( random_bytes( 8 ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve synthetic test data.
		$this->original_post = $_POST;

		$_POST = [
			'swpm_user_name' => 'sec024-unknown-member',
			'swpm_password'  => 'invalid-test-password',
		];
		hcaptcha()->settings()->set( 'login_limit', 1 );
		hcaptcha()->settings()->set( 'login_interval', 15 );
	}

	/**
	 * Restore request data and remove the isolated counter.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$attempts = new LoginAttempts();
		$attempts->reset( $this->ip, $attempts->get_reset_token( $this->ip, time() ) );
		$_POST = $this->original_post;

		parent::tearDown();
	}

	/**
	 * Native failures without the submit button must activate protection.
	 *
	 * @return void
	 */
	public function test_markerless_native_failure_activates_captcha(): void {
		$subject = $this->subject();

		self::assertSame( 10, has_action( 'swpm_authenticate_failed', [ $subject, 'native_login_failed' ] ) );
		self::assertSame( 10, has_action( 'swpm_login_failed', [ $subject, 'native_login_failed' ] ) );
		self::assertSame( 10, has_action( 'swpm_after_login', [ $subject, 'native_login_success' ] ) );
		self::assertSame( 20, has_action( 'swpm_after_login_authentication', [ $subject, 'native_login_success_after_auth' ] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Inspect synthetic test data.
		self::assertArrayNotHasKey( 'swpm-login', $_POST );
		self::assertSame( 0, $this->attempt_count() );

		$subject->native_login_failed();

		self::assertSame( 1, $this->attempt_count() );
		self::assertTrue( $this->set_method_accessibility( $subject, 'is_login_limit_exceeded' )->invoke( $subject ) );
		self::assertStringContainsString(
			'h-captcha',
			$subject->add_hcaptcha( '<div class="swpm-login-submit"></div>', 'swpm_login_form', [], [] )
		);
		self::assertNotSame( '', $subject->verify( '' ) );
	}

	/**
	 * Paired native hooks must count only once, including a general-only failure.
	 *
	 * @param bool $specific Whether the specific failure hook fires first.
	 *
	 * @return void
	 * @dataProvider dp_native_failure_hooks
	 */
	public function test_native_failure_hooks_count_once( bool $specific ): void {
		$subject = $this->subject();

		$_POST['swpm-login'] = 'Login';

		if ( $specific ) {
			$subject->native_login_failed();
		}

		$subject->native_login_failed();

		self::assertSame( 1, $this->attempt_count() );
	}

	/**
	 * Native failure event combinations.
	 *
	 * @return array
	 */
	public function dp_native_failure_hooks(): array {
		return [
			'paired specific and general' => [ true ],
			'general only'                => [ false ],
		];
	}

	/**
	 * WordPress authentication may invoke SWPM internally; only WP counts it.
	 *
	 * @return void
	 */
	public function test_wp_authentication_does_not_count_native_failure(): void {
		$subject = $this->subject();
		$hook    = $GLOBALS['wp_filter']['wp_authenticate'] ?? null;
		remove_all_actions( 'wp_authenticate' );
		add_action( 'wp_authenticate', [ $subject, 'native_login_failed' ] );

		try {
			do_action( 'wp_authenticate', 'sec024-unknown-member', 'invalid-test-password' );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['wp_filter']['wp_authenticate'] = $hook;
		}

		self::assertSame( 0, $this->attempt_count() );
		$subject->login_failed( 'sec024-unknown-member' );
		self::assertSame( 1, $this->attempt_count() );
	}

	/**
	 * Native success clears captured attempts, while external login does not.
	 *
	 * @return void
	 */
	public function test_native_success_resets_captured_attempts(): void {
		$attempts = new LoginAttempts();
		$attempts->increment( $this->ip, time(), 15 * MINUTE_IN_SECONDS );
		$subject = $this->subject();
		$this->set_protected_property(
			$subject,
			'login_attempts_reset_token',
			$attempts->get_reset_token( $this->ip, time() )
		);

		$_POST = [];
		do_action( 'swpm_after_login_authentication', 'sec024-member', 'test-password', false );
		self::assertSame( 1, $this->attempt_count() );
		$subject->native_login_success();
		self::assertSame( 1, $this->attempt_count() );

		$_POST['swpm_user_name'] = 'sec024-member';
		do_action( 'swpm_after_login', 'sec024-member' );
		self::assertSame( 0, $this->attempt_count() );
	}

	/**
	 * A nested wp_signon failure after member success must not add an attempt.
	 *
	 * @return void
	 */
	public function test_nested_wp_failure_after_native_success_is_ignored(): void {
		$subject = $this->subject();
		$hook    = $GLOBALS['wp_filter']['swpm_after_login_authentication'] ?? null;
		remove_all_actions( 'swpm_after_login_authentication' );
		add_action(
			'swpm_after_login_authentication',
			static function (): void {
				do_action( 'wp_login_failed', 'sec024-member' );
			}
		);

		try {
			do_action( 'swpm_after_login_authentication', 'sec024-member', 'test-password', false );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['wp_filter']['swpm_after_login_authentication'] = $hook;
		}

		self::assertSame( 0, $this->attempt_count() );
		$subject->login_failed( 'sec024-other-wp-user' );
		self::assertSame( 1, $this->attempt_count() );
	}

	/**
	 * Create an adapter bound to the isolated failed-login slot.
	 *
	 * @return Login
	 */
	private function subject(): Login {
		$subject = new Login();
		$this->set_protected_property( $subject, 'ip', $this->ip );

		return $subject;
	}

	/**
	 * Read the isolated failed-login count.
	 *
	 * @return int
	 */
	private function attempt_count(): int {
		return ( new LoginAttempts() )->read( $this->ip, time() );
	}
}
