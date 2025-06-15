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

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\LostPassword;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;
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
	 * Tear down test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset(
			$_SERVER['REQUEST_URI'],
			$_GET['action'],
			$GLOBALS['mockery'][ 'alias:' . Plugin::class ]
		);

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
		$validation_error   = new WP_Error( 'some error' );
		$expected           = clone $validation_error;
		$_POST['wp-submit'] = 'some';

		$this->prepare_verify_post( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password' );

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$validation_error   = new WP_Error( 'some error' );
		$expected           = clone $validation_error;
		$_POST['wp-submit'] = 'some';

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post_html( 'hcaptcha_wp_lost_password_nonce', 'hcaptcha_wp_lost_password', false );

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() when not proper post key.
	 */
	public function test_verify_when_NOT_proper_post_key(): void {
		$validation_error = new WP_Error( 'some error' );
		$expected         = clone $validation_error;

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
	 * Test get_login_url() with Perfmatters plugin.
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
	 * Test get_login_url() with WPS Hide Login plugin.
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
}
