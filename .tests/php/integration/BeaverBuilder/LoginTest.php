<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\BeaverBuilder;

use FLBuilderModule;
use HCaptcha\BeaverBuilder\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use WP_Error;
use WP_User;

/**
 * Class LoginTest
 *
 * @group beaver-builder
 * @group beaver-builder-login
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_current_filter'] );

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

		// Base hooks.
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );

		// Login hooks.
		self::assertSame(
			10,
			has_filter( 'fl_builder_render_module_content', [ $subject, 'add_beaver_builder_captcha' ] )
		);
		self::assertSame( 10, has_filter( 'wp_authenticate_user', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test add_beaver_builder_captcha().
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_beaver_builder_captcha(): void {
		$button    = '<div class="fl-button-wrap some"><button class="fl-button">Submit</button></div>';
		$form      = '<div class="fl-login-form logout">' . $button . '</div>';
		$some_out  = 'some output';
		$form_out  = 'some output ' . $form . ' more';
		$args      = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [],
				'form_id' => 'login',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$hcaptcha  = '<div class="fl-input-group fl-hcaptcha">' . $hcap_form . '</div>';
		$expected  = 'some output <div class="fl-login-form ">' . $hcaptcha . $button . '</div> more';
		$module    = Mockery::mock( 'alias:' . FLBuilderModule::class );

		$subject = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_limit_exceeded' )->andReturn( true );

		// Some output.
		self::assertSame( $some_out, $subject->add_beaver_builder_captcha( $some_out, $module ) );

		// Logout output.
		self::assertSame( $form_out, $subject->add_beaver_builder_captcha( $form_out, $module ) );

		$form_out = str_replace( 'logout', '', $form_out );

		// Login form in output.
		self::assertSame( $expected, $subject->add_beaver_builder_captcha( $form_out, $module ) );
	}

	/**
	 * Test add_beaver_builder_captcha() when login limit not exceeded.
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_beaver_builder_captcha_when_login_limit_not_exceeded(): void {
		$some_out = 'some output';
		$module   = Mockery::mock( 'alias:' . FLBuilderModule::class );

		$subject = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_limit_exceeded' )->andReturn( false );

		self::assertSame( $some_out, $subject->add_beaver_builder_captcha( $some_out, $module ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_verify(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'] = [ 'wp_ajax_nopriv_fl_builder_login_form_submit' ];

		$user = new WP_User( 1 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$subject = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_limit_exceeded' )->andReturn( true );

		self::assertSame( $user, $subject->verify( $user, 'some password' ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'] = [ 'wp_ajax_nopriv_fl_builder_login_form_submit' ];

		$user          = new WP_User( 1 );
		$error_message = '<strong>hCaptcha error:</strong> The hCaptcha is invalid.';
		$expected      = new WP_Error( 'invalid_hcaptcha', $error_message, 400 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$subject = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_limit_exceeded' )->andReturn( true );

		self::assertEquals( $expected, $subject->verify( $user, 'some password' ) );
	}

	/**
	 * Test verify() when not doing action.
	 *
	 * @return void
	 */
	public function test_verify_not_verified_when_not_doing_action(): void {
		$user = new WP_User( 1 );

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, 'some password' ) );
	}

	/**
	 * Test verify() when the login limit is not exceeded.
	 *
	 * @return void
	 */
	public function test_verify_not_verified_when_login_limit_not_exceeded(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'] = [ 'wp_ajax_nopriv_fl_builder_login_form_submit' ];

		$user = new WP_User( 1 );

		$subject = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_limit_exceeded' )->andReturn( false );

		self::assertEquals( $user, $subject->verify( $user, 'some password' ) );
	}
}
