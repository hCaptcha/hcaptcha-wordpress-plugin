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
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ElementorPro\Modules\Forms\Widgets\Login as ElementorLogin;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class LoginTest
 *
 * @group elementor-pro
 * @group elementor-pro-login
 */
class LoginTest extends HCaptchaWPTestCase {

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
	 */
	public function test_render(): void {
		$element    = new ElementorLogin();
		$form       = <<<'HTML'
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
		$args       = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'elementor-pro/elementor-pro.php' ],
				'form_id' => 'login',
			],
		];
		$hcaptcha   = $this->get_hcap_form( $args );
		$hcaptcha   = '<div class="elementor-field-group elementor-column elementor-col-100">' . $hcaptcha . '</div>';
		$signatures = HCaptcha::get_signature( Login::class, 'login', true );
		$submit_div = '<div class="elementor-field-group elementor-column elementor-field-type-submit elementor-col-100">';
		$expected   = str_replace( $submit_div, $hcaptcha . $signatures . "\n" . $submit_div, $form );

		update_option(
			'hcaptcha_settings',
			[
				'elementor_pro_status' => [ 'login' ],
			]
		);

		hcaptcha()->init_hooks();

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
	 * Test before_render() and add_elementor_login_hcaptcha() when login limit is not exceeded.
	 *
	 * @return void
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
}
