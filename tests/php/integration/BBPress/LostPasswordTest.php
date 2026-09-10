<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use WP_Error;

/**
 * Test LostPassword class.
 *
 * @group bbpress
 * @group bbpress-lost-password
 */
class LostPasswordTest extends HCaptchaPluginWPTestCase {

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
		hcaptcha()->settings()->set( 'bbp_status', 'lost_pass' );

		$subject = new LostPassword();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );
		self::assertSame(
			10,
			has_filter( 'hcap_lost_password_request_owner', [ $subject, 'claim_request_owner' ] )
		);
		self::assertFalse(
			has_filter( 'hcap_auto_verify_unmatched_form', [ $subject, 'defer_auto_verification' ] )
		);

		hcaptcha()->settings()->set( 'bbp_status', 'some' );

		$subject = new LostPassword();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$tag  = 'bbp-lost-pass';
		$attr = [];
		$m    = [];

		$placeholder = '===hcaptcha placeholder===';
		$template    = <<<HTML
<form action="https://test.test/wp-login.php">
		$placeholder<button type="submit"/>
</form>
HTML;

		$args     = [
			'action' => 'hcaptcha_action',
			'name'   => 'hcaptcha_nonce',
			'auto'   => true,
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'lost_password',
			],
		];
		$hcaptcha = $this->get_hcap_form( $args );

		$output   = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha . "\n", $template );

		hcaptcha()->settings()->set( 'bbp_status', 'lost_pass' );
		$subject = new LostPassword();

		// Wrong tag.
		self::assertSame( $output, $subject->add_captcha( $output, 'some', $attr, $m ) );

		// Logged in.
		wp_set_current_user( 1 );

		self::assertSame( $output, $subject->add_captcha( $output, $tag, $attr, $m ) );

		// Add hCaptcha..
		wp_set_current_user( 0 );

		add_action(
			'hcap_auto_verify_register',
			static function ( $html ) use ( &$registered_output ) {
				$registered_output = $html;
			}
		);

		self::assertSame( $expected, $subject->add_captcha( $output, $tag, $attr, $m ) );
		self::assertSame( 1, did_action( 'hcap_auto_verify_register' ) );
		self::assertSame( $expected, $registered_output );
	}

	/**
	 * Test hCaptcha in the live bbPress lost-password shortcode.
	 *
	 * @return void
	 */
	public function test_live_lost_password_form(): void {
		hcaptcha()->settings()->set( 'bbp_status', 'lost_pass' );

		new LostPassword();
		$this->load_bbp_templates();

		$html = do_shortcode( '[bbp-lost-pass]' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringContainsString( 'class="bbp-login-form"', $html );
		self::assertStringContainsString( 'name="user_login"', $html );
		self::assertStringContainsString( 'name="hcaptcha_nonce"', $html );
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
}
