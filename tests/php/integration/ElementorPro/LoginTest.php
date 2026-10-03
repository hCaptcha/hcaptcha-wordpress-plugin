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

namespace HCaptcha\Tests\Integration\ElementorPro;

use Elementor\Element_Base;
use HCaptcha\ElementorPro\Login;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ElementorPro\Modules\Forms\Widgets\Login as ElementorLogin;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_User;

/**
 * Class LoginTest
 *
 * @group elementor-pro
 * @group elementor-pro-login
 */
class LoginTest extends HCaptchaPluginWPTestCase {
	/**
	 * Plugin relative paths.
	 *
	 * @var string[]
	 */
	protected static $plugin = [
		'elementor/elementor.php',
		'elementor-pro/elementor-pro.php',
	];

	/**
	 * Hooks to replay after loading the plugins.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		LoginAttempts::delete_all();

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

		self::assertSame( 10, has_action( 'wp_login', [ $subject, 'login' ] ) );
		self::assertSame( 10, has_action( 'wp_login_failed', [ $subject, 'login_failed' ] ) );

		self::assertSame( 10, has_action( 'elementor/frontend/widget/before_render', [ $subject, 'before_render' ] ) );
		self::assertSame( 10, has_action( 'elementor/frontend/widget/after_render', [ $subject, 'add_elementor_login_hcaptcha' ] ) );

		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test before_render() and add_elementor_login_hcaptcha().
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_render(): void {
		$element = new ElementorLogin();
		$form    = <<<'HTML'
<div class="elementor-element elementor-element-fb88da3 elementor-widget elementor-widget-login" data-id="fb88da3" data-element_type="widget" data-widget_type="login.default">
	<div class="elementor-widget-container">
		<form class="elementor-login elementor-form" method="post" action="https://test.test/wp-login.php">
			<input type="hidden" name="redirect_to" value="/elementor-login/">
			<div class="elementor-form-fields-wrapper">
				<div class="elementor-field-type-text elementor-field-group elementor-column elementor-col-100 elementor-field-required">
					<label for="user">Username or Email Address</label>					<input size="1" type="text" name="log" id="user" placeholder="" class="elementor-field elementor-field-textual elementor-size-sm">
				</div>
				<div class="elementor-field-type-text elementor-field-group elementor-column elementor-col-100 elementor-field-required">
					<label for="password">Password</label>					<input size="1" type="password" name="pwd" id="password" placeholder="" class="elementor-field elementor-field-textual elementor-size-sm">
				</div>

				<div class="elementor-field-type-checkbox elementor-field-group elementor-column elementor-col-100 elementor-remember-me">
					<label for="elementor-login-remember-me">
						<input type="checkbox" id="elementor-login-remember-me" name="rememberme" value="forever">
						Remember Me						</label>
				</div>

				<div class="elementor-field-group elementor-column elementor-field-type-submit elementor-col-100">
					<button type="submit" class="elementor-size-sm elementor-button" name="wp-submit">
						<span class="elementor-button-text">Log In</span>
					</button>
				</div>

				<div class="elementor-field-group elementor-column elementor-col-100">
					<a class="elementor-lost-password" href="https://test.test/wp-login.php?action=lostpassword&redirect_to=%2Felementor-login%2F">
						Lost your password?							</a>

					<span class="elementor-login-separator"> | </span>
					<a class="elementor-register" href="https://test.test/wp-login.php?action=register">
						Register							</a>
				</div>
			</div>
		</form>
	</div>
</div>
HTML;
		$args    = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'elementor-pro/elementor-pro.php' ],
				'form_id' => 'login',
			],
		];
		update_option(
			'hcaptcha_settings',
			[
				'elementor_pro_status' => [ 'login' ],
			]
		);

		hcaptcha()->init_hooks();
		remove_all_actions( 'hcap_signature' );

		$hcaptcha   = $this->get_hcap_form( $args );
		$hcaptcha   = '<div class="elementor-field-group elementor-column elementor-col-100">' . $hcaptcha . '</div>';
		$signatures = HCaptcha::get_signature( Login::class, 'elementor-login', true );
		$submit_div = '<div class="elementor-field-group elementor-column elementor-field-type-submit elementor-col-100">';
		$expected   = str_replace( $submit_div, $hcaptcha . $signatures . "\n" . $submit_div, $form );

		$subject = new Login();

		ob_start();

		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_elementor_login_hcaptcha( $element );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test before_render() and add_elementor_login_hcaptcha() with a wrong element.
	 *
	 * @return void
	 */
	public function test_render_with_wrong_element(): void {
		$element = Mockery::mock( Element_Base::class );
		$form    = 'Some form';

		$subject = new Login();

		ob_start();

		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_elementor_login_hcaptcha( $element );

		self::assertSame( $form, ob_get_clean() );
	}

	/**
	 * Test before_render() and add_elementor_login_hcaptcha() when the login limit is not exceeded.
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_render_when_login_limit_is_not_exceeded(): void {
		$element = new ElementorLogin();
		$form    = 'Some form';

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$subject = new Login();

		ob_start();

		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_elementor_login_hcaptcha( $element );

		self::assertSame( $form, ob_get_clean() );
	}

	/**
	 * Test a real below-threshold form carries a signature that cannot preserve its exemption.
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_rendered_below_threshold_form_requires_captcha_after_failure(): void {
		update_option(
			'hcaptcha_settings',
			[
				'elementor_pro_status' => [ 'login' ],
				'wp_status'            => [],
				'login_limit'          => 1,
				'login_interval'       => 15,
			]
		);
		hcaptcha()->init_hooks();
		remove_all_actions( 'hcap_signature' );
		remove_all_filters( 'wp_authenticate_user' );
		remove_all_filters( 'hcap_wp_login_can_skip_verification' );
		$subject = new Login();
		$element = new ElementorLogin();
		$user    = new WP_User( 1 );
		$this->set_protected_property( $subject, 'ip', '203.0.113.20' );

		ob_start();
		$subject->before_render( $element );
		echo '<form><div class="elementor-field-group"><button type="submit">Login</button></div></form>';
		$subject->add_elementor_login_hcaptcha( $element );
		$form = (string) ob_get_clean();

		self::assertStringNotContainsString( '<h-captcha', $form );
		$this->submit_signature( $form );
		$this->mark_native_login_request();
		self::assertTrue( HCaptcha::check_signature( Login::class, 'elementor-login' ) );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
		$subject->login_failed( 'test-user' );
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Test a valid below-threshold signature cannot be replayed after the threshold is crossed.
	 *
	 * @return void
	 */
	public function test_replayed_below_threshold_signature_requires_captcha(): void {
		$ip   = '203.0.113.18';
		$user = new WP_User( 1 );

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'          => 1,
				'login_interval'       => 15,
				'elementor_pro_status' => [ 'login' ],
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Login();
		$this->set_protected_property( $subject, 'ip', $ip );

		ob_start();
		$subject->display_signature();
		$this->submit_signature( (string) ob_get_clean() );
		$this->mark_native_login_request();

		self::assertTrue( HCaptcha::check_signature( Login::class, 'login' ) );
		self::assertSame( $user, $subject->check_signature( $user, 'password' ) );

		$subject->login_failed( 'test-user' );

		$result = $subject->check_signature( $user, 'password' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertTrue( $result->has_errors() );
	}

	/**
	 * Test a false signature cannot skip an immediately required challenge.
	 *
	 * @return void
	 */
	public function test_false_signature_cannot_skip_when_login_limit_is_zero(): void {
		$user = new WP_User( 1 );

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'          => 0,
				'login_interval'       => 15,
				'elementor_pro_status' => [ 'login' ],
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Login();

		ob_start();
		$subject->display_signature();
		$this->submit_signature( (string) ob_get_clean() );
		$this->mark_native_login_request();

		self::assertTrue( HCaptcha::check_signature( Login::class, 'login' ) );

		$result = $subject->check_signature( $user, 'password' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertTrue( $result->has_errors() );
	}

	/**
	 * Test a solved challenge above the threshold remains valid.
	 *
	 * @return void
	 */
	public function test_solved_challenge_above_threshold(): void {
		$ip   = '203.0.113.19';
		$user = new WP_User( 1 );

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'          => 1,
				'login_interval'       => 15,
				'elementor_pro_status' => [ 'login' ],
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Login();
		$this->set_protected_property( $subject, 'ip', $ip );
		$subject->login_failed( 'test-user' );

		ob_start();
		$subject->add_captcha();
		$captcha = (string) ob_get_clean();

		ob_start();
		$subject->display_signature( 'elementor-login' );
		$this->submit_signature( (string) ob_get_clean() );

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'elementor-pro/elementor-pro.php' ],
				'form_id' => 'login',
			]
		);
		$this->mark_native_login_request();

		self::assertStringContainsString( '<h-captcha', $captcha );
		self::assertNull( HCaptcha::check_signature( Login::class, 'elementor-login' ) );
		self::assertSame( $user, $subject->check_signature( $user, 'password' ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
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
	.elementor-widget-login .h-captcha {
		margin-bottom: 0;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Login();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Submit a rendered signature field.
	 *
	 * @param string $signature Rendered signature field.
	 *
	 * @return void
	 */
	private function submit_signature( string $signature ): void {
		preg_match( '/name="([^"]+)"\s+value="([^"]+)"/', $signature, $matches );

		self::assertCount( 3, $matches );

		$_POST[ html_entity_decode( $matches[1], ENT_QUOTES ) ] = html_entity_decode( $matches[2], ENT_QUOTES );
	}

	/**
	 * Mark the request as a native login submission.
	 *
	 * @return void
	 */
	private function mark_native_login_request(): void {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
