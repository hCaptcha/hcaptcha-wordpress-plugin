<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\Login;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionClass;

/**
 * Test Login class.
 *
 * @group bbpress
 * @group bbpress-login
 */
class LoginTest extends HCaptchaPluginWPTestCase {

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
	 * Test the login form rendered by the live bbPress shortcode.
	 */
	public function test_live_login_form(): void {
		hcaptcha()->settings()->set( 'bbp_status', 'login' );

		new Login();

		$shortcodes_file = wp_normalize_path( ( new ReflectionClass( \BBP_Shortcodes::class ) )->getFileName() );
		$template        = bbp_get_template_part( 'form', 'user-login' );

		ob_start();
		load_template( $template, false );
		$html = ob_get_clean();

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/bbpress/' ), $shortcodes_file );
		self::assertTrue( shortcode_exists( 'bbp-login' ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/bbpress/' ), wp_normalize_path( $template ) );
		self::assertStringContainsString( 'class="bbp-login-form"', $html );
		self::assertStringContainsString( 'class="h-captcha"', $html );
		self::assertStringContainsString( 'hcaptcha_login_nonce', $html );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Login();

		self::assertSame( 10, has_filter( 'bbp_get_template_part', [ $subject, 'mark_template_login_form' ] ) );
		self::assertSame( 10, has_filter( 'bbp_login_widget_title', [ $subject, 'mark_widget_login_form' ] ) );
		self::assertSame( 10, has_action( 'login_form', [ $subject, 'add_bbpress_captcha' ] ) );
		self::assertSame( 10, has_filter( 'hcap_auto_verify_unmatched_form', [ $subject, 'defer_auto_verification' ] ) );
		self::assertSame(
			10,
			has_filter( 'hcap_wp_login_can_skip_verification', [ $subject, 'allow_wp_login_skip_verification' ] )
		);
	}

	/**
	 * Test adding hCaptcha to a template-based login form.
	 *
	 * @return void
	 */
	public function test_template_login_form(): void {
		$templates = [ 'form-user-login.php' ];
		$args      = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'login',
			],
		];
		$expected  = $this->get_hcap_form( $args );

		$subject = new Login();

		ob_start();
		self::assertSame( $templates, $subject->mark_template_login_form( $templates, 'some', 'user-login' ) );
		$subject->add_bbpress_captcha();
		self::assertSame( '', ob_get_clean() );

		hcaptcha()->settings()->set( 'bbp_status', 'login' );

		ob_start();
		self::assertSame( $templates, $subject->mark_template_login_form( $templates, 'form', 'user-login' ) );
		$subject->add_bbpress_captcha();
		self::assertSame( $expected, ob_get_clean() );

		// The marker must be consumed by the form.
		ob_start();
		$subject->add_bbpress_captcha();
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test adding hCaptcha to a login widget.
	 *
	 * @return void
	 */
	public function test_widget_login_form(): void {
		$title   = 'Widget title';
		$subject = new Login();

		self::assertSame( $title, $subject->mark_widget_login_form( $title ) );
	}

	/**
	 * Test allowing native WordPress login verification to defer to bbPress.
	 *
	 * @return void
	 */
	public function test_allow_wp_login_skip_verification(): void {
		$subject = new Login();

		self::assertTrue( $subject->allow_wp_login_skip_verification( true ) );
		self::assertFalse( $subject->allow_wp_login_skip_verification( false ) );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( Login::class );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST[ $name ] = $this->get_encoded_signature(
			Login::class,
			[ 'bbpress/bbpress.php' ],
			'login',
			true
		);

		self::assertTrue( $subject->allow_wp_login_skip_verification( false ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST[ $name ] = $this->get_encoded_signature(
			Login::class,
			[ 'bbpress/bbpress.php' ],
			'login',
			false
		);

		self::assertFalse( $subject->allow_wp_login_skip_verification( false ) );
	}

	/**
	 * Test deferring unmatched auto-verification to bbPress.
	 *
	 * @return void
	 */
	public function test_defer_auto_verification(): void {
		$registered_form = [];
		$subject         = new Login();
		$login_path      = (string) wp_parse_url( wp_login_url(), PHP_URL_PATH );
		$widget_id       = HCaptcha::widget_id_value(
			[
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'login',
			]
		);

		self::assertSame(
			$registered_form,
			$subject->defer_auto_verification( $registered_form, $login_path, $widget_id )
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( Login::class );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST[ $name ] = $this->get_encoded_signature(
			Login::class,
			[ 'bbpress/bbpress.php' ],
			'login',
			true
		);

		self::assertSame(
			$registered_form,
			$subject->defer_auto_verification( $registered_form, '/some-path', $widget_id )
		);
		self::assertSame(
			$registered_form,
			$subject->defer_auto_verification( $registered_form, $login_path, $widget_id )
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $widget_id;

		self::assertSame(
			$registered_form,
			$subject->defer_auto_verification( $registered_form, $login_path, 'bad-widget-id' )
		);
		self::assertNull( $subject->defer_auto_verification( $registered_form, $login_path, $widget_id ) );
	}
}
