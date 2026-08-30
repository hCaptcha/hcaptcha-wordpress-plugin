<?php
/**
 * ProtectTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\PasswordProtected;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\PasswordProtected\Protect;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionClass;
use WP_Error;

/**
 * Test Protect class.
 *
 * @group password-protected
 */
class ProtectTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'password-protected/password-protected.php';

	/**
	 * Hooks to replay after loading Password Protected.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Test the live Password Protected login template.
	 */
	public function test_live_login_form(): void {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Global name defined by Password Protected.
		global $Password_Protected;

		new Protect();

		$plugin_file = wp_normalize_path( ( new ReflectionClass( \Password_Protected::class ) )->getFileName() );

		ob_start();
		load_template( PASSWORD_PROTECTED_DIR . 'theme/password-protected-login.php' );
		$html = ob_get_clean();

		self::assertTrue( is_plugin_active( static::$plugin ) );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Global name defined by Password Protected.
		self::assertInstanceOf( \Password_Protected::class, $Password_Protected );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/password-protected/' ), $plugin_file );
		self::assertStringContainsString( 'id="loginform"', $html );
		self::assertStringContainsString( 'name="password_protected_pwd"', $html );
		self::assertStringContainsString( 'class="h-captcha"', $html );
		self::assertStringContainsString( 'hcaptcha_password_protected_nonce', $html );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Protect();

		self::assertSame( 10, has_filter( 'password_protected_below_password_field', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_action( 'password_protected_verify_recaptcha', [ $subject, 'verify' ] ) );
		self::assertSame( 10, has_action( 'password_protected_login_head', [ hcaptcha(), 'print_inline_styles' ] ) );
		self::assertSame( 20, has_action( 'password_protected_login_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$form_id   = 'protect';
		$hcap_form = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_password_protected',
				'name'   => 'hcaptcha_password_protected_nonce',
				'id'     => [
					'source'  => [ 'password-protected/password-protected.php' ],
					'form_id' => $form_id,
				],
			]
		);

		$subject = new Protect();

		ob_start();

		$subject->add_hcaptcha();

		self::assertSame( $hcap_form, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @param bool $verified Verified or not.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 */
	public function test_verify( bool $verified ): void {
		$action   = 'hcaptcha_password_protected';
		$nonce    = 'hcaptcha_password_protected_nonce';
		$errors   = new WP_Error();
		$expected = $verified ? $errors : new WP_Error( 'fail', 'The hCaptcha is invalid.', 400 );

		$this->prepare_verify_post( $nonce, $action, $verified );
		$this->prepare_widget_id();

		$subject = new Protect();

		// Verify the hCaptcha.
		self::assertEquals( $expected, $subject->verify( $errors ) );
	}

	/**
	 * Test verify() when widget id is missing.
	 *
	 * @return void
	 */
	public function test_verify_missing_widget_id(): void {
		$action   = 'hcaptcha_password_protected';
		$nonce    = 'hcaptcha_password_protected_nonce';
		$errors   = new WP_Error();
		$expected = new WP_Error( 'bad-signature', 'Bad hCaptcha signature!', 400 );

		$this->prepare_verify_post( $nonce, $action );

		$subject = new Protect();

		self::assertEquals( $expected, $subject->verify( $errors ) );
	}
	/**
	 * Data provider for test_verify().
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			[ 'not verified' => false ],
			[ 'verified' => true ],
		];
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'password-protected/password-protected.php' ],
				'form_id' => 'protect',
			]
		);
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		$subject = new Protect();

		ob_start();

		$subject->print_inline_styles();

		$output = ob_get_clean();

		self::assertStringContainsString( '<style>', $output );
		self::assertStringContainsString( 'body.login-password-protected #loginform', $output );
		self::assertStringContainsString( 'min-width:302px', preg_replace( '/\s+/', '', $output ) );
		self::assertStringContainsString( 'body.login-password-protected p.submit+div', $output );
	}
}
