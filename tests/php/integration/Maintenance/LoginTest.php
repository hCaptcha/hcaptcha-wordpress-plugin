<?php
/**
 * Maintenance LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Maintenance;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Utils;
use HCaptcha\Maintenance\Login;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use MTNC;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_User;

/**
 * Test Maintenance Login class.
 *
 * @group maintenance-login
 * @group maintenance
 */
class LoginTest extends HCaptchaPluginWPTestCase {

	/**
	 * Maintenance plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'maintenance/maintenance.php';

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_POST['is_custom_login'], $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertTrue( class_exists( 'MTNC', false ) );
		self::assertInstanceOf( MTNC::class, $GLOBALS['wf_mtnc'] );

		$subject = new Login();

		self::assertSame( 10, has_action( 'mtnc_load_options_style', [ hcaptcha(), 'print_inline_styles' ] ) );
		self::assertSame( 10, has_action( 'mtnc_load_options_style', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 10, has_action( 'mtnc_after_main_container', [ $subject, 'after_main_container' ] ) );
		self::assertSame( 10, has_action( 'mtnc_load_custom_scripts', [ $subject, 'add_hcaptcha' ] ) );

		self::assertSame( 10, has_filter( 'wp_authenticate_user', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test render flow.
	 *
	 * @noinspection PhpUnusedLocalVariableInspection
	 */
	public function test_render(): void {
		$footer_scripts = '<!-- footer-scripts -->';

		// In WP 6.9.1, a new error is triggered:
		// The script with the handle "hcaptcha-kadence-advanced" was enqueued with dependencies that are not registered: kadence-blocks-advanced-form. (This message was added in version 6.9.1.).
		// Some tests enqueuing scripts without proper dependencies, since those dependencies are in plugins.
		// Here we suppress the error caused by the doing 'wp_print_footer_scripts' in the tested class.
		Utils::instance()->remove_action_regex( '/WPTestCase/', 'doing_it_wrong_run' );

		// Junk all late styles and scripts aren't related to the test.
		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_get_clean();

		$subject = new Login();

		// Force protection enabled, so get_hcaptcha() returns markup.
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		add_action(
			'wp_print_footer_scripts',
			static function () use ( $footer_scripts ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $footer_scripts;
			}
		);
		add_filter( 'script_loader_tag', 'mtnc_defer_scripts', 10, 2 );

		$args      = [
			'action' => 'hcaptcha_maintenance_login',
			'name'   => 'hcaptcha_maintenance_login_nonce',
			'id'     => [
				'source'  => [ 'maintenance/maintenance.php' ],
				'form_id' => 'login',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$output    = $this->render_maintenance_page();

		// The real Maintenance login form should contain hCaptcha before the `submit` input.
		self::assertStringContainsString( '<form name="login-form" id="login-form"', $output );
		self::assertStringContainsString( '<input type="hidden" name="is_custom_login" value="1"', $output );
		self::assertStringContainsString( $hcap_form, $output );
		self::assertStringContainsString( $footer_scripts, $output );
		self::assertLessThan( strpos( $output, '<input type="submit"' ), strpos( $output, $hcap_form ) );
		self::assertFalse( has_filter( 'script_loader_tag', 'mtnc_defer_scripts' ) );

		add_filter( 'hcap_delay_api_event', '__return_true' );

		$output = $this->render_maintenance_page();

		remove_filter( 'hcap_delay_api_event', '__return_true' );

		self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $output );
	}

	/**
	 * Test render flow when an error message is set by verify(): it should be displayed in span.login-error.
	 */
	public function test_render_injection_with_error_message(): void {
		$subject = new Login();

		// Simulate Maintenance custom login POST and exceeded limit.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['is_custom_login'] = '1';

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		// Prepare failed verification so verify() sets error_message.
		$this->prepare_verify_post_html( 'hcaptcha_maintenance_login_nonce', 'hcaptcha_maintenance_login', false );
		$this->prepare_widget_id();

		$user = new WP_User( 1 );

		$subject->verify( $user, 'pass' );

		// Keep the hCaptcha error on the integration while rendering an unsubmitted real form.
		unset( $_POST['is_custom_login'] );

		$output = $this->render_maintenance_page();

		self::assertStringContainsString( '<form name="login-form" id="login-form"', $output );
		self::assertStringContainsString(
			'<span class="login-error"><strong>hCaptcha error:</strong> The hCaptcha is invalid.</span>',
			$output
		);
		self::assertStringContainsString( 'name="hcaptcha_maintenance_login_nonce"', $output );
	}

	/**
	 * Test verify() when not Maintenance custom login: returns original.
	 */
	public function test_verify_not_custom_login(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		self::assertSame( $user, $subject->verify( $user, 'pass' ) );
	}

	/**
	 * Test verify() when login limit not exceeded: returns original.
	 */
	public function test_verify_not_exceeded(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['is_custom_login'] = '1';

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		self::assertSame( $user, $subject->verify( $user, 'pass' ) );
	}

	/**
	 * Test verify() when exceeded and verification succeeds: returns original.
	 */
	public function test_verify_success(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['is_custom_login'] = '1';

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$this->prepare_verify_post_html( 'hcaptcha_maintenance_login_nonce', 'hcaptcha_maintenance_login' );
		$this->prepare_widget_id();

		self::assertSame( $user, $subject->verify( $user, 'pass' ) );
	}

	/**
	 * Test verify() when exceeded and verification fails: returns WP_Error with proper code/message/data.
	 */
	public function test_verify_failure(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['is_custom_login'] = '1';

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$this->prepare_verify_post_html( 'hcaptcha_maintenance_login_nonce', 'hcaptcha_maintenance_login', false );
		$this->prepare_widget_id();

		$result = $subject->verify( $user, 'pass' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_hcaptcha', $result->get_error_code() );
		self::assertSame( '<strong>hCaptcha error:</strong> The hCaptcha is invalid.', $result->get_error_message() );
		self::assertSame( 400, $result->get_error_data() );
	}

	/**
	 * Test verify() when widget id is bad.
	 */
	public function test_verify_bad_widget_id(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['is_custom_login'] = '1';

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$this->prepare_verify_post_html( 'hcaptcha_maintenance_login_nonce', 'hcaptcha_maintenance_login' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'login',
			]
		);

		$result = $subject->verify( $user, 'pass' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_hcaptcha', $result->get_error_code() );
		self::assertSame( '<strong>hCaptcha error:</strong> Bad hCaptcha signature!', $result->get_error_message() );
		self::assertSame( 400, $result->get_error_data() );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'CSS'
	body.maintenance > .login-form-container {
		min-width: 343px;
		max-width: 343px;
		right: -343px;
	}

	body.maintenance.open-login-form > .login-form-container {
		right: 0;
	}

	body.maintenance #login-form a.lost-pass {
		margin-bottom: 2em;
	}

	body.maintenance #login-form .h-captcha {
		margin-top: 2em;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Login();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param array $id Widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'maintenance/maintenance.php' ],
			'form_id' => 'login',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Render the real Maintenance frontend template.
	 *
	 * @return string
	 * @noinspection PhpUndefinedConstantInspection
	 */
	private function render_maintenance_page(): string {
		global $wf_mtnc;

		$options      = $wf_mtnc->get_options();
		$test_options = array_merge(
			$options,
			[
				'login_button'     => '1',
				'no_cache_headers' => '0',
				'blockse'          => '0',
			]
		);
		$buffer_level = ob_get_level();

		$wf_mtnc->update_options( 'options', $test_options );

		ob_start();

		try {
			require MTNC_LOAD . 'index.php';

			$output = ob_get_clean();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			$wf_mtnc->update_options( 'options', $options );
		}

		return $output;
	}
}
