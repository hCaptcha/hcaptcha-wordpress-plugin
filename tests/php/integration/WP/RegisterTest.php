<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\BBPress\Register as BBPressRegister;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Register;
use WPS\WPS_Hide_Login\Plugin;
use WP_Error;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class RegisterTest.
 *
 * @group wp-register
 * @group wp
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_GET['action'], $_POST['action'] );
		delete_transient( AutoVerify::TRANSIENT );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'register_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'registration_errors', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_filter( 'hcap_registration_request_owner', [ $subject, 'claim_request_owner' ] )
		);
		self::assertSame(
			10,
			has_filter( 'hcap_auto_verify_unmatched_form', [ $subject, 'defer_auto_verification' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'register';

		$args     = [
			'action' => 'hcaptcha_registration',
			'name'   => 'hcaptcha_registration_nonce',
			'id'     => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'register',
			],
		];
		$expected = $this->get_hcap_form( $args );

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'perfmatters_login_url' !== $function_name;
			}
		);
		FunctionMocker::replace(
			'class_exists',
			static function ( $function_name ) {
				return Plugin::class !== $function_name;
			}
		);

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not a WP login url.
	 */
	public function test_add_captcha_when_NOT_login_url(): void {
		$_SERVER['REQUEST_URI'] = '';
		$_GET['action']         = 'register';

		$expected = '';

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not register action.
	 */
	public function test_add_captcha_when_NOT_register_action(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'some';

		$expected = '';

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$_GET['action'] = 'register';

		$errors = new WP_Error( 'some error' );

		$this->prepare_verify_post_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );
		$this->prepare_widget_id();

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Test verify() when the register action is submitted in POST.
	 */
	public function test_verify_with_post_action(): void {
		$_POST['action'] = 'register';
		$this->prepare_widget_id();

		$verify_called = false;
		$errors        = new WP_Error( 'some error' );

		FunctionMocker::replace(
			'HCaptcha\Helpers\API::verify',
			static function () use ( &$verify_called ) {
				$verify_called = true;

				return null;
			}
		);

		$subject = new Register();

		self::assertSame( $errors, $subject->verify( $errors, '', '' ) );
		self::assertTrue( $verify_called );
	}

	/**
	 * Test verify() skips a request owned by the bbPress verifier.
	 *
	 * @return void
	 */
	public function test_verify_skips_bbpress_owner(): void {
		$_POST['action'] = 'register';
		$errors          = new WP_Error( 'some error' );
		$verify_called   = false;

		$this->prepare_widget_id(
			[
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'register',
			]
		);

		FunctionMocker::replace(
			'HCaptcha\Helpers\API::verify',
			static function () use ( &$verify_called ) {
				$verify_called = true;

				return null;
			}
		);

		new BBPressRegister();
		$subject = new Register();

		self::assertSame( $errors, $subject->verify( $errors, '', '' ) );
		self::assertFalse( $verify_called );
	}

	/**
	 * Test AutoVerify defers a request to its registered form verifier.
	 *
	 * @param array $id Submitted widget ID data.
	 *
	 * @dataProvider dp_test_auto_verify_defers_registered_owner
	 */
	public function test_auto_verify_defers_registered_owner( array $id ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-login.php?action=register';
		$_POST['action']           = 'register';
		$_POST['user_login']       = 'some login';
		$_POST['user_email']       = 'some@example.com';

		$this->prepare_widget_id( $id );
		$this->register_colliding_auto_form();

		new Register();
		new BBPressRegister();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$expected = $_POST;
		$die_arr  = [];

		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		( new AutoVerify() )->verify();

		self::assertSame( [], $die_arr );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( $expected, $_POST );
	}

	/**
	 * Data provider for test_auto_verify_defers_registered_owner().
	 *
	 * @return array
	 */
	public function dp_test_auto_verify_defers_registered_owner(): array {
		return [
			'WordPress register' =>
				[
					[
						'source'  => [ 'WordPress' ],
						'form_id' => 'register',
					],
				],
			'bbPress register'   =>
				[
					[
						'source'  => [ 'bbpress/bbpress.php' ],
						'form_id' => 'register',
					],
				],
		];
	}

	/**
	 * Test AutoVerify keeps an unknown registration widget fail-closed.
	 *
	 * @return void
	 */
	public function test_auto_verify_rejects_unknown_register_owner(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-login.php?action=register';
		$_POST['action']           = 'register';

		$this->prepare_widget_id(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'register',
			]
		);
		$this->register_colliding_auto_form();

		new Register();
		new BBPressRegister();

		$die_arr  = [];
		$expected = [
			'Bad hCaptcha signature!',
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 403,
			],
		];

		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		( new AutoVerify() )->verify();

		self::assertSame( $expected, $die_arr );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [], $_POST );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$_GET['action'] = 'register';

		$errors = new WP_Error( 'some error' );

		$errors->add( 'invalid_captcha', '<strong>Error</strong>: The Captcha is invalid.' );

		$this->prepare_verify_post_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration', false );
		$this->prepare_widget_id();

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Test verify() when widget id is bad.
	 */
	public function test_verify_bad_widget_id(): void {
		$_GET['action'] = 'register';

		$errors = new WP_Error( 'some error' );

		$errors->add( 'bad-signature', 'Bad hCaptcha signature!' );

		$this->prepare_verify_post_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'register',
			]
		);

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Test verify() not register action.
	 */
	public function test_verify_when_NOT_register_action(): void {
		$_GET['action'] = 'some';

		$errors = new WP_Error( 'some error' );

		$subject = new Register();

		self::assertEquals( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Prepare widget id.
	 *
	 * @param array $id The hCaptcha widget id.
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'WordPress' ],
			'form_id' => 'register',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Register an auto-verified form colliding with the shared login endpoint.
	 *
	 * @return void
	 */
	private function register_colliding_auto_form(): void {
		$id         = [
			'source'  => [ 'bbpress/bbpress.php' ],
			'form_id' => 'lost_password',
		];
		$login_path = untrailingslashit( (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) );

		set_transient(
			AutoVerify::TRANSIENT,
			[
				$login_path => [
					[
						'inputs'    => [ 'user_login' ],
						'args'      => [ 'id' => $id ],
						'widget_id' => HCaptcha::widget_id_value( $id ),
					],
				],
			]
		);
	}
}
