<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ProfileBuilder;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\ProfileBuilder\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\LoginOut;
use Mockery;
use WP_User;

/**
 * Test Login class.
 *
 * @group profile-builder
 * @group profile-builder-login
 */
class LoginTest extends HCaptchaWPTestCase {
	/**
	 * Skip unrelated form rendering and keep a low-risk login unchanged.
	 */
	public function test_other_form_and_login_below_limit(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		self::assertSame( 'other', $subject->add_wppb_captcha( 'other', [] ) );
		self::assertSame( $user, $subject->verify( $user, 'password' ) );

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );
		do_action( 'wppb_process_login_start' );

		try {
			self::assertSame( $user, $subject->verify( $user, 'password' ) );
		} finally {
			remove_filter( 'hcap_login_limit_exceeded', '__return_false' );
		}
	}

	/**
	 * Print the Profile Builder login spacing rule.
	 */
	public function test_print_inline_styles(): void {
		ob_start();
		( new Login() )->print_inline_styles();

		self::assertStringContainsString( '#wppb-loginform .h-captcha', ob_get_clean() );
	}

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'profile_builder_status', 'login' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test init hooks.
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->init_hooks();

		self::assertSame(
			PHP_INT_MAX,
			has_filter( 'wppb_login_form_args', [ $subject, 'mark_profile_builder_login_form' ] )
		);
		self::assertSame( 10, has_filter( 'login_form_middle', [ $subject, 'add_wppb_captcha' ] ) );
		self::assertSame(
			PHP_INT_MAX,
			has_filter(
				'wppb_login_form_before_content_output',
				[ $subject, 'finish_profile_builder_login_form' ]
			)
		);
	}

	/**
	 * Test that the specific Profile Builder widget suppresses the generic LoginOut widget.
	 *
	 * @return void
	 */
	public function test_specific_widget_suppresses_login_out_widget(): void {
		new LoginOut();
		$subject = new Login();

		apply_filters( 'login_form_defaults', [] );
		apply_filters( 'wppb_login_form_args', [] );

		$content = (string) apply_filters( 'login_form_middle', '', [] );
		$id      = [
			'source'  => [ 'profile-builder/index.php' ],
			'form_id' => 'login',
		];

		self::assertSame( 1, substr_count( $content, 'name="hcaptcha-widget-id"' ) );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $content );
		self::assertStringContainsString( 'name="hcap_hp_test"', $content );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $content );

		self::assertSame( 'form', $subject->finish_profile_builder_login_form( 'form', [] ) );
	}
}
