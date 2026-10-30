<?php
/**
 * RegisterEmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\LearnPress;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\LearnPress\Register;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use LP_Request;
use LP_Session_Handler;

/**
 * Check the native registration email before account creation.
 */
class RegisterEmailPolicyTest extends HCaptchaPluginWPTestCase {
	/**
	 * Do not add a challenge outside LearnPress registration rendering.
	 */
	public function test_add_hcaptcha_outside_registration_form(): void {
		ob_start();
		( new Register() )->add_hcaptcha();

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Native plugin dependency.
	 *
	 * @var string
	 */
	protected static $plugin = 'learnpress/learnpress.php';

	/**
	 * Native lifecycle hooks.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'init' ];

	/**
	 * Restore the native lifecycle after the WordPress test case resets hooks.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Restore request globals after each native registration route.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset(
			$_GET['learn-press-register-nonce'],
			$_REQUEST['learn-press-register-nonce']
		);

		parent::tearDown();
	}

	/**
	 * A nonce accepted by native registration must also run hCaptcha verification.
	 *
	 * @dataProvider dp_test_native_nonce_location
	 *
	 * @param string $location Native nonce location.
	 */
	public function test_native_nonce_without_hcaptcha_blocks_registration( string $location ): void {
		wp_set_current_user( 0 );
		$nonce    = wp_create_nonce( 'learn-press-register' );
		$password = 'A-strong-test-password-123!';

		if ( 'post' === $location ) {
			$_POST['learn-press-register-nonce'] = $nonce;
		} else {
			$_GET['learn-press-register-nonce'] = $nonce;
		}

		$_REQUEST['learn-press-register-nonce'] = $nonce;
		$_POST['reg_username']                  = 'sec037-registration';
		$_POST['reg_email']                     = 'sec037-registration@example.com';
		$_POST['reg_password']                  = $password;
		$_POST['reg_password2']                 = $password;

		$this->prepare_verify_post( 'hcaptcha_learn_press_register_nonce', 'hcaptcha_learn_press_register', null );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'learnpress/learnpress.php' ],
				'form_id' => 'register',
			]
		);
		unset( $_POST['h-captcha-response'] );
		delete_option( 'lp-customer-message' );
		$customer_id = LP_Session_Handler::instance()->get_customer_id();

		self::assertNotFalse( LP_Request::verify_nonce( 'learn-press-register' ) );
		self::assertNotFalse( has_action( 'wp_loaded', [ 'LP_Forms_Handler', 'init' ] ) );

		( new Register() )->verify();

		self::assertFalse( has_action( 'wp_loaded', [ 'LP_Forms_Handler', 'init' ] ) );
		self::assertSame(
			[
				$customer_id => [
					'status'  => 'error',
					'content' => hcap_get_error_messages()['empty'],
				],
			],
			get_option( 'lp-customer-message', [] )
		);
		self::assertFalse( email_exists( 'sec037-registration@example.com' ) );
	}

	/**
	 * Native nonce locations.
	 *
	 * @return array
	 */
	public function dp_test_native_nonce_location(): array {
		return [
			'POST nonce'  => [ 'post' ],
			'query nonce' => [ 'query' ],
		];
	}

	/**
	 * Invalid or malformed native nonces must leave unrelated requests alone.
	 *
	 * @dataProvider dp_test_invalid_native_nonce
	 *
	 * @param mixed $nonce Native nonce value.
	 */
	public function test_invalid_native_nonce_is_ignored( $nonce ): void {
		if ( null !== $nonce ) {
			$_REQUEST['learn-press-register-nonce'] = $nonce;
		}

		$_POST['reg_email'] = 'sec037-registration@example.com';
		delete_option( 'lp-customer-message' );
		self::assertNotFalse( has_action( 'wp_loaded', [ 'LP_Forms_Handler', 'init' ] ) );

		( new Register() )->verify();

		self::assertNotFalse( has_action( 'wp_loaded', [ 'LP_Forms_Handler', 'init' ] ) );
		self::assertSame( [], get_option( 'lp-customer-message', [] ) );
	}

	/**
	 * Invalid native nonce values.
	 *
	 * @return array
	 */
	public function dp_test_invalid_native_nonce(): array {
		return [
			'absent nonce'  => [ null ],
			'invalid nonce' => [ 'not-a-valid-nonce' ],
			'array nonce'   => [ [ 'not-a-valid-nonce' ] ],
		];
	}

	/**
	 * Validate the native address instead of a generic alias.
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
		$nonce                                  = wp_create_nonce( 'learn-press-register' );
		$_POST['learn-press-register-nonce']    = $nonce;
		$_REQUEST['learn-press-register-nonce'] = $nonce;
		$_POST['reg_email']                     = $email;
		$_POST['email']                         = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_learn_press_register_nonce', 'hcaptcha_learn_press_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'learn_press_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'learnpress/learnpress.php' ],
				'form_id' => 'register',
			]
		);
		delete_option( 'lp-customer-message' );
		$customer_id = LP_Session_Handler::instance()->get_customer_id();

		( new Register() )->verify();

		self::assertSame(
			$blocked ? [
				$customer_id => [
					'status'  => 'error',
					'content' => hcap_get_error_messages()['disposable-email'],
				],
			] : [],
			get_option( 'lp-customer-message', [] )
		);
	}

	/**
	 * Native email controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable native email' => [ 'user@disposable.example', true ],
			'permanent native email'  => [ 'safe@example.com', false ],
			'email-free data'         => [ '', false ],
		];
	}
}
