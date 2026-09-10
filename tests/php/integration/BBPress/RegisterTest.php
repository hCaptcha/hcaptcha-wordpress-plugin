<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WP\Register as WPRegister;
use WP_Error;

/**
 * Test Register class.
 *
 * @group bbpress
 * @group bbpress-register
 */
class RegisterTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'bbpress/bbpress.php';

	/**
	 * Hooks to replay after loading bbPress.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'setup_theme',
		'after_setup_theme',
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		hcaptcha()->settings()->set( 'bbp_status', 'register' );

		$subject = new Register();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_filter( 'registration_errors', [ $subject, 'verify' ] ) );
		self::assertSame( 10, has_filter( 'hcap_registration_request_owner', [ $subject, 'claim_request_owner' ] ) );
		self::assertSame(
			10,
			has_filter( 'hcap_auto_verify_unmatched_form', [ $subject, 'defer_auto_verification' ] )
		);

		hcaptcha()->settings()->set( 'bbp_status', 'some' );

		$subject = new Register();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$tag  = 'bbp-register';
		$attr = [];
		$m    = [];

		$placeholder = '===hcaptcha placeholder===';
		$template    = <<<HTML
<form action="https://test.test/wp-login.php">
		$placeholder<button type="submit"/>
</form>
HTML;

		$args     = [
			'action' => 'hcaptcha_bbp_register',
			'name'   => 'hcaptcha_bbp_register_nonce',
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'register',
			],
		];
		$hcaptcha = $this->get_hcap_form( $args );

		$output   = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha . "\n", $template );

		hcaptcha()->settings()->set( 'bbp_status', 'register' );
		$subject = new Register();

		// Wrong tag.
		self::assertSame( $output, $subject->add_captcha( $output, 'some', $attr, $m ) );

		// Logged in.
		wp_set_current_user( 1 );

		self::assertSame( $output, $subject->add_captcha( $output, $tag, $attr, $m ) );

		// Status is 'register'.
		wp_set_current_user( 0 );

		$subject = new Register();

		self::assertSame( $expected, $subject->add_captcha( $output, $tag, $attr, $m ) );
	}

	/**
	 * Test hCaptcha in the live bbPress registration shortcode.
	 *
	 * @return void
	 */
	public function test_live_registration_form(): void {
		hcaptcha()->settings()->set( 'bbp_status', 'register' );

		new Register();
		$this->load_bbp_templates();

		$html = do_shortcode( '[bbp-register]' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringContainsString( 'class="bbp-login-form"', $html );
		self::assertStringContainsString( 'name="user_login"', $html );
		self::assertStringContainsString( 'name="hcaptcha_bbp_register_nonce"', $html );
	}

	/**
	 * Load bbPress templates when WP_USE_THEMES is disabled by the test runner.
	 *
	 * @return void
	 */
	private function load_bbp_templates(): void {
		add_action(
			'bbp_locate_template',
			static function ( $located, $template_name, $template_names, $template_locations, $load, $load_once ) {
				if ( $load && $located ) {
					load_template( $located, $load_once );
				}
			},
			10,
			6
		);
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$sanitized_user_login = 'login';
		$user_email           = 'email';
		$errors               = new WP_Error();
		$expected             = new WP_Error();

		$errors->add( 'some code', 'some message' );
		$expected->add( 'some code', 'some message' );

		$this->prepare_verify_post( 'hcaptcha_bbp_register_nonce', 'hcaptcha_bbp_register' );
		$this->prepare_widget_id();

		$subject = new Register();

		self::assertEquals( $expected, $subject->verify( $errors, $sanitized_user_login, $user_email ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_when_not_verified(): void {
		$sanitized_user_login = 'login';
		$user_email           = 'email';
		$errors               = new WP_Error();
		$expected             = new WP_Error();

		$errors->add( 'some code', 'some message' );
		$expected->add( 'some code', 'some message' );
		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$subject = new Register();

		$this->prepare_verify_post( 'hcaptcha_bbp_register_nonce', 'hcaptcha_bbp_register', false );
		$this->prepare_widget_id();

		self::assertEquals( $expected, $subject->verify( $errors, $sanitized_user_login, $user_email ) );
	}

	/**
	 * Test verify() skips a request owned by the native WordPress verifier.
	 *
	 * @return void
	 */
	public function test_verify_skips_wordpress_owner(): void {
		$errors  = new WP_Error( 'some code', 'some message' );
		$subject = new Register();

		new WPRegister();

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'register',
			]
		);

		self::assertSame( $errors, $subject->verify( $errors, '', '' ) );
	}

	/**
	 * Prepare widget id.
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'register',
			]
		);
	}
}
