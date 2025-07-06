<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EssentialAddons;

use HCaptcha\EssentialAddons\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use Essential_Addons_Elementor\Classes\Bootstrap;
use tad\FunctionMocker\FunctionMocker;
use Elementor\Widget_Base;

/**
 * Class LoginTest
 *
 * @group essential-addons
 * @group essential-addons-login
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['widget_id'], $_SERVER['HTTP_REFERER'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = Mockery::mock( Login::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$subject->init_hooks();

		self::assertSame( 10, has_action( 'hcap_signature', [ $subject, 'display_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_action( 'login_form', [ $subject, 'display_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'login_form_middle', [ $subject, 'add_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'wp_authenticate_user', [ $subject, 'check_signature' ] ) );
		self::assertSame( 100, has_filter( 'authenticate', [ $subject, 'hide_login_error' ] ) );

		self::assertSame( 10, has_action( 'wp_login', [ $subject, 'login' ] ) );
		self::assertSame( 10, has_action( 'wp_login_failed', [ $subject, 'login_failed' ] ) );

		self::assertSame( 0, has_action( 'hcap_delay_api', [ $subject, 'delay_api' ] ) );

		self::assertSame(
			10,
			has_action( 'eael/login-register/before-login-footer', [ $subject, 'add_login_hcaptcha' ] )
		);
		self::assertSame( 10, has_action( 'eael/login-register/before-login', [ $subject, 'verify' ] ) );

		self::assertSame( 0, has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
	}

	/**
	 * Test add_login_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_login_hcaptcha(): void {
		$widget   = Mockery::mock( Widget_Base::class );
		$args     = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => 'essential-addons-for-elementor-lite/essential_adons_elementor.php',
				'form_id' => 'login',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Login();

		ob_start();

		$subject->add_login_hcaptcha( $widget );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$post      = [ 'some post data' ];
		$settings  = [ 'some Elementor widget settings' ];
		$bootstrap = Mockery::mock( Bootstrap::class );

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login_data' );

		$subject = new Login();

		$subject->verify( $post, $settings, $bootstrap );
	}

	/**
	 * Test verify() when login limit is not exceeded.
	 *
	 * @return void
	 */
	public function test_verify_when_login_limit_is_not_exceeded(): void {
		$post      = [ 'some post data' ];
		$settings  = [ 'some Elementor widget settings' ];
		$bootstrap = Mockery::mock( Bootstrap::class );

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$subject = new Login();

		$subject->verify( $post, $settings, $bootstrap );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @param bool $has_referer Has referer.
	 *
	 * @return void
	 * @dataProvider dp_test_verify_not_verified
	 */
	public function test_verify_not_verified( bool $has_referer ): void {
		$post          = [ 'some post data' ];
		$settings      = [ 'some Elementor widget settings' ];
		$bootstrap     = Mockery::mock( Bootstrap::class );
		$widget_id     = 'some_id';
		$error_message = 'The hCaptcha is invalid.';
		$referer       = 'some-referer';
		$die_arr       = [];
		$setcookie     = [];
		$redirect      = false;
		$expected      = [
			'',
			'',
			[ 'response' => null ],
		];

		$_POST['widget_id'] = $widget_id;

		if ( $has_referer ) {
			$_SERVER['HTTP_REFERER'] = $referer;
		}

		$this->prepare_verify_post(
			'hcaptcha_login_nonce',
			'hcaptcha_login_data',
			false
		);

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter(
			'wp_redirect',
			static function () use ( &$redirect ) {
				$redirect = true;

				return false;
			}
		);

		FunctionMocker::replace(
			'setcookie',
			static function ( $name, $value ) use ( &$setcookie ) {
				$setcookie = [ $name, $value ];
			}
		);

		$subject = Mockery::mock( Login::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'exit' )->with()->times( (int) $has_referer );

		ob_start();
		$subject->verify( $post, $settings, $bootstrap );
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":false,"data":"' . $error_message . '"}', $json );
		self::assertSame( 'eael_login_error_' . $widget_id, $setcookie[0] );
		self::assertSame( $error_message, $setcookie[1] );
		self::assertSame( $redirect, $has_referer );
	}

	/**
	 * Data provider for test_verify_not_verified().
	 *
	 * @return array
	 */
	public function dp_test_verify_not_verified(): array {
		return [
			[ false ],
			[ true ],
		];
	}
}
