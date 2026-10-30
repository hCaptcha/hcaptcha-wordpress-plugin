<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\BBPress\LostPassword as BBPressLostPassword;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\LostPassword as WCLostPassword;
use HCaptcha\WP\LostPassword;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WPS\WPS_Hide_Login\Plugin;
use WP_Error;

/**
 * LostPasswordTest class.
 *
 * @group wp-lost-password
 * @group wp
 */
class LostPasswordTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset(
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI'],
			$_GET['action'],
			$_POST['action'],
			$GLOBALS['mockery'][ 'alias:' . Plugin::class ]
		);
		delete_transient( AutoVerify::TRANSIENT );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new LostPassword();

		self::assertSame(
			10,
			has_action( 'lostpassword_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'lostpassword_post', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_filter( 'hcap_lost_password_request_owner', [ $subject, 'claim_request_owner' ] )
		);
		self::assertSame(
			10,
			has_filter( 'hcap_auto_verify_unmatched_form', [ $subject, 'defer_auto_verification' ] )
		);
	}

	/**
	 * Test add_captcha().
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_add_captcha( string $action, bool $post_action ): void {
		$this->prepare_lost_password_request( $action, $post_action );

		$args     = [
			'action' => 'hcaptcha_wp_lost_password',
			'name'   => 'hcaptcha_wp_lost_password_nonce',
			'id'     => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'lost_password',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not WP login url.
	 */
	public function test_add_captcha_when_NOT_wp_login_url(): void {
		unset( $_SERVER['REQUEST_URI'] );

		$_GET['action'] = 'lostpassword';

		$expected = '';

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
		$errors = new WP_Error();
		$subject->verify( $errors );
		self::assertFalse( $errors->has_errors() );
	}

	/**
	 * Test add_captcha() when not WP login action.
	 */
	public function test_add_captcha_when_NOT_wp_login_action(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';

		$expected = '';

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_verify( string $action, bool $post_action ): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;
		$this->prepare_lost_password_request( $action, $post_action );

		$this->prepare_verify_post( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password' );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_verify_not_verified( string $action, bool $post_action ): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;
		$this->prepare_lost_password_request( $action, $post_action );

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post_html( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password', false );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() when widget id is bad.
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_verify_bad_widget_id( string $action, bool $post_action ): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;
		$this->prepare_lost_password_request( $action, $post_action );

		$expected->add( 'bad-signature', 'Bad hCaptcha signature!' );

		$this->prepare_verify_post( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'lost_password',
			]
		);

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() skips a request owned by bbPress AutoVerify.
	 *
	 * @return void
	 */
	public function test_verify_skips_bbpress_owner(): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;

		$this->prepare_lost_password_request();
		$this->prepare_widget_id(
			[
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'lost_password',
			]
		);

		new BBPressLostPassword();
		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test AutoVerify defers a native request to the WordPress verifier.
	 *
	 * @return void
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_auto_verify_defers_wordpress_owner( string $action, bool $post_action ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->prepare_lost_password_request( $action, $post_action );
		$this->prepare_widget_id();
		$this->register_colliding_auto_form();

		new LostPassword();
		new BBPressLostPassword();

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
	 * Test AutoVerify does not defer to an owner that cannot handle the request.
	 *
	 * @return void
	 */
	public function test_auto_verify_rejects_owner_without_its_post_marker(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['action']           = 'lostpassword';

		$this->prepare_lost_password_request();
		$this->prepare_widget_id(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'lost_password',
			]
		);
		$this->register_colliding_auto_form();

		new LostPassword();
		new WCLostPassword();

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
	 * Test a WooCommerce signature cannot suppress recovery without its marker.
	 */
	public function test_foreign_owner_without_its_post_marker(): void {
		$this->prepare_lost_password_request();
		$this->prepare_widget_id(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'lost_password',
			]
		);

		new WCLostPassword();
		new LostPassword();
		$errors = new WP_Error();

		do_action( 'lostpassword_post', $errors );

		self::assertSame( 'Bad hCaptcha signature!', $errors->get_error_message( 'bad-signature' ) );
	}

	/**
	 * Test a valid WooCommerce recovery owner requires its challenge.
	 */
	public function test_foreign_owner_with_its_post_marker(): void {
		$this->prepare_lost_password_request();
		$_POST['wc_reset_password'] = '1';
		$this->prepare_widget_id(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'lost_password',
			]
		);
		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password' );
		$_POST['h-captcha-response'] = '';

		new LostPassword();
		new WCLostPassword();
		$errors = new WP_Error();

		do_action( 'lostpassword_post', $errors );

		self::assertSame( 'Please complete the hCaptcha.', $errors->get_error_message( 'empty' ) );
		self::assertSame( '', $errors->get_error_message( 'bad-signature' ) );
	}

	/**
	 * Test verify() when not proper post key.
	 */
	public function test_verify_when_NOT_proper_post_key(): void {
		$validation_error       = new WP_Error( 'some error' );
		$expected               = clone $validation_error;
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'lostpassword';

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() without the optional submit field.
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_verify_without_submit_field( string $action, bool $post_action ): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_lost_password_request( $action, $post_action );
		$this->prepare_verify_post_html( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password', false );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test native recovery stops before changing the key or sending an email.
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_native_recovery_without_captcha( string $action, bool $post_action ): void {
		$user_id = $this->factory()->user->create();
		$user    = get_userdata( $user_id );
		$mailed  = false;

		wp_set_current_user( 0 );
		$this->prepare_lost_password_request( $action, $post_action );
		$_POST['user_login'] = $user->user_login;

		add_filter(
			'pre_wp_mail',
			static function () use ( &$mailed ) {
				$mailed = true;

				return true;
			}
		);

		new LostPassword();
		$result = retrieve_password( $user->user_login );

		$this->assertWPError( $result );
		self::assertContains( 'bad-signature', $result->get_error_codes() );
		self::assertFalse( $mailed );
		self::assertSame( '', get_userdata( $user_id )->user_activation_key );
	}

	/**
	 * Native recovery checks the resolved account email before sending a reset link.
	 *
	 * @param bool $blocked Whether the account email is disposable.
	 *
	 * @return void
	 * @dataProvider dp_recipient_email_policy
	 */
	public function test_native_recovery_checks_resolved_email( bool $blocked ): void {
		$email   = $blocked ? 'recipient@blocked.test' : 'recipient@allowed.test';
		$user_id = $this->factory()->user->create( [ 'user_email' => $email ] );
		$user    = get_userdata( $user_id );
		$mailed  = false;
		$checked = [];

		wp_set_current_user( 0 );
		$this->prepare_lost_password_request();
		$this->prepare_verify_post( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password' );
		$this->prepare_widget_id();
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST['user_login'] = $user->user_login;
		$_POST['email']      = 'forged@allowed.test';

		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'recipient@blocked.test' === $address;
			},
			10,
			2
		);
		add_filter(
			'pre_wp_mail',
			static function () use ( &$mailed ): bool {
				$mailed = true;

				return true;
			}
		);
		$remote_addr_exists = array_key_exists( 'REMOTE_ADDR', $_SERVER );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Preserve the prior native request value for exact restoration.
		$remote_addr            = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		try {
			new LostPassword();
			$result = retrieve_password( $user->user_login );
		} finally {
			if ( $remote_addr_exists ) {
				$_SERVER['REMOTE_ADDR'] = $remote_addr;
			} else {
				unset( $_SERVER['REMOTE_ADDR'] );
			}
		}

		if ( $blocked ) {
			$this->assertWPError( $result );
			self::assertSame( 'Please use a permanent email address.', $result->get_error_message( 'disposable-email' ) );
			self::assertFalse( $mailed );
			self::assertSame( '', get_userdata( $user_id )->user_activation_key );
		} else {
			self::assertTrue( $result );
			self::assertTrue( $mailed );
		}

		self::assertSame( [ $email ], $checked );
	}

	/**
	 * Provide native recipient policy outcomes.
	 *
	 * @return array
	 */
	public function dp_recipient_email_policy(): array {
		return [
			'blocked recipient' => [ true ],
			'allowed recipient' => [ false ],
		];
	}

	/**
	 * Test recovery keeps a server-authorized form exemption.
	 *
	 * @dataProvider dp_test_recovery_actions
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	public function test_recovery_form_exemption( string $action, bool $post_action ): void {
		wp_set_current_user( 0 );
		$this->prepare_lost_password_request( $action, $post_action );
		$this->prepare_widget_id();
		add_filter( 'hcap_protect_form', '__return_false' );

		$errors = new WP_Error();
		( new LostPassword() )->verify( $errors );

		self::assertSame( [], $errors->get_error_codes() );
	}

	/**
	 * Data provider for native recovery actions and action precedence.
	 *
	 * @return array
	 */
	public function dp_test_recovery_actions(): array {
		return [
			'lostpassword GET'      => [ 'lostpassword', false ],
			'retrievepassword GET'  => [ 'retrievepassword', false ],
			'lostpassword POST'     => [ 'lostpassword', true ],
			'retrievepassword POST' => [ 'retrievepassword', true ],
		];
	}

	/**
	 * Test get_login_url().
	 *
	 * @return void
	 */
	public function test_get_login_url(): void {
		$subject = Mockery::mock( LostPassword::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Standard WP login URL.
		self::assertSame( '/wp-login.php', $subject->get_login_url() );
	}

	/**
	 * Test get_login_url() with the Perfmatters plugin.
	 *
	 * @return void
	 */
	public function test_get_login_url_with_perfmatters(): void {
		$subject = Mockery::mock( LostPassword::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$login_url_path = '/perfmatters-login';
		$login_url      = 'https://test.test' . $login_url_path;

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'perfmatters_login_url' === $function_name;
			}
		);
		FunctionMocker::replace( 'perfmatters_login_url', $login_url );

		self::assertSame( $login_url_path, $subject->get_login_url() );
	}

	/**
	 * Test get_login_url() with the WPS Hide Login plugin.
	 *
	 * @return void
	 */
	public function test_get_login_url_with_wps(): void {
		$subject = Mockery::mock( LostPassword::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$login_url_path = '/wps-login';
		$login_url      = 'https://test.test' . $login_url_path;
		$plugin         = Mockery::mock( 'alias:' . Plugin::class );

		$plugin->shouldReceive( 'get_instance' )->once()->andReturn( $plugin );
		$plugin->shouldReceive( 'new_login_url' )->once()->andReturn( $login_url );

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'perfmatters_login_url' !== $function_name;
			}
		);

		self::assertSame( $login_url_path, $subject->get_login_url() );
	}

	/**
	 * Prepare widget id.
	 *
	 * @param array $id The hCaptcha widget id.
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'WordPress' ],
			'form_id' => 'lost_password',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Prepare a WordPress lost password request without the optional submit field.
	 *
	 * @param string $action      Recovery action.
	 * @param bool   $post_action Whether to submit the action in POST.
	 */
	private function prepare_lost_password_request( string $action = 'lostpassword', bool $post_action = false ): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = $post_action ? 'login' : $action;
		$_POST['user_login']    = 'igor';

		if ( $post_action ) {
			$_POST['action'] = $action;
		}
	}

	/**
	 * Register a bbPress lost-password form colliding with the shared login endpoint.
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
