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

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\Maintenance\Login;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_User;

/**
 * Test Maintenance Login class.
 *
 * @group maintenance-login
 * @group maintenance
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_POST['is_custom_login'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Login();

		self::assertSame( 10, has_action( 'load_custom_style', [ hcaptcha(), 'print_inline_styles' ] ) );
		self::assertSame( 10, has_action( 'load_custom_style', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 10, has_action( 'after_main_container', [ $subject, 'after_main_container' ] ) );
		self::assertSame( 10, has_action( 'load_custom_scripts', [ $subject, 'add_hcaptcha' ] ) );

		self::assertSame( 10, has_filter( 'wp_authenticate_user', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test render flow.
	 */
	public function test_render(): void {
		$footer_scripts = '<!-- footer-scripts -->';

		// Junk all late styles and scripts not related to the test.
		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_get_clean();

		$subject = new Login();

		// Force protection enabled so get_hcaptcha() returns markup.
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		add_action(
			'wp_print_footer_scripts',
			static function () use ( $footer_scripts ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $footer_scripts;
			}
		);

		$form      = '<form><input type="text" name="login" /><input type="submit" value="Login"></form>';
		$args      = [
			'action' => 'hcaptcha_maintenance_login',
			'name'   => 'hcaptcha_maintenance_login_nonce',
			'id'     => [
				'source'  => [ 'maintenance/maintenance.php' ],
				'form_id' => 'login',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$search    = '<input type="submit"';
		$expected  = str_replace( $search, "\n" . $hcap_form . "\n" . $search, $form ) . $footer_scripts;

		// Start buffering and emit a minimal form.
		$subject->after_main_container();

		ob_start();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha();

		$output = ob_get_clean();

		// hCaptcha should be injected before the 'submit' input and footer scripts should be printed.
		self::assertSame( $expected, $output );
	}

	/**
	 * Test render flow when an error message is set by verify(): it should be displayed in span.login-error.
	 */
	public function test_render_injection_with_error_message(): void {
		$footer_scripts = '<!-- footer-scripts -->';

		$subject = new Login();

		// Simulate Maintenance custom login POST and exceeded limit.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['is_custom_login'] = '1';

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		// Prepare a failed verification so verify() sets error_message.
		$this->prepare_verify_post_html( 'hcaptcha_maintenance_login_nonce', 'hcaptcha_maintenance_login', false );

		$user = new WP_User( 1 );

		$subject->verify( $user, 'pass' );

		// Start buffering and emit a form with a login-error span.
		$subject->after_main_container();

		add_action(
			'wp_print_footer_scripts',
			static function () use ( $footer_scripts ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $footer_scripts;
			}
		);

		$form      = '<form><span class="login-error">Some old error</span><input type="text" name="login" /><input type="submit" value="Login"></form>';
		$args      = [
			'action' => 'hcaptcha_maintenance_login',
			'name'   => 'hcaptcha_maintenance_login_nonce',
			'id'     => [
				'source'  => [ 'maintenance/maintenance.php' ],
				'form_id' => 'login',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$search    = '<input type="submit"';
		$expected  = str_replace( $search, "\n" . $hcap_form . "\n" . $search, $form ) . $footer_scripts;
		$expected  = preg_replace( '#(<span class="login-error">).*?(</span>)#', '$1<strong>hCaptcha error:</strong> The hCaptcha is invalid.$2', $expected );

		ob_start();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha();

		$output = ob_get_clean();

		// hCaptcha should be injected before the 'submit' input and footer scripts should be printed.
		self::assertSame( $expected, $output );
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

		$result = $subject->verify( $user, 'pass' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_hcaptcha', $result->get_error_code() );
		self::assertSame( '<strong>hCaptcha error:</strong> The hCaptcha is invalid.', $result->get_error_message() );
		self::assertSame( 400, $result->get_error_data() );
	}

	/**
	 * Test print_inline_styles().
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
}
