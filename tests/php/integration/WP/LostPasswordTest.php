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
	 */
	public function test_add_captcha(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'lostpassword';

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
	 */
	public function test_verify(): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;
		$this->prepare_lost_password_request();

		$this->prepare_verify_post( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password' );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;
		$this->prepare_lost_password_request();

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post_html( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password', false );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() when widget id is bad.
	 */
	public function test_verify_bad_widget_id(): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;
		$this->prepare_lost_password_request();

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
	 */
	public function test_auto_verify_defers_wordpress_owner(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['action']           = 'lostpassword';

		$this->prepare_lost_password_request();
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
	 */
	public function test_verify_without_submit_field(): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_lost_password_request();
		$this->prepare_verify_post_html( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password', false );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
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
	 */
	private function prepare_lost_password_request(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php';
		$_GET['action']         = 'lostpassword';
		$_POST['user_login']    = 'igor';
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
