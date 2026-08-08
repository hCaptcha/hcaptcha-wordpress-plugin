<?php
/**
 * UltimateAddons LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\UltimateAddons;

use Elementor\Plugin as ElementorPlugin;
use Elementor\Widget_Base;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\UltimateAddons\Login;
use ReflectionClass;
use tad\FunctionMocker\FunctionMocker;
use UltimateElementor\Modules\LoginForm\Module as LoginFormModule;
use UltimateElementor\Modules\LoginForm\Widgets\LoginForm as UltimateElementorLogin;
use WP_Error;
use WP_User;

/**
 * Test Ultimate Addons Login class.
 *
 * @group ultimate-addons-login
 * @group ultimate-addons
 */
class LoginTest extends HCaptchaPluginWPTestCase {
	/**
	 * Plugin relative paths.
	 *
	 * @var string[]
	 */
	protected static $plugin = [
		'elementor/elementor.php',
		'ultimate-elementor/ultimate-elementor.php',
	];

	/**
	 * Hooks to replay after loading the plugin.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'elementor/init',
		'init',
	];

	/**
	 * Test that live Elementor and Ultimate Addons plugins are loaded.
	 *
	 * @return void
	 * @noinspection PhpUnusedLocalVariableInspection
	 */
	public function test_live_plugins_are_loaded(): void {
		$elementor_file         = wp_normalize_path( ( new ReflectionClass( ElementorPlugin::class ) )->getFileName() );
		$ultimate_addons_file   = wp_normalize_path( ( new ReflectionClass( UltimateElementorLogin::class ) )->getFileName() );
		$ultimate_addons_widget = $this->get_login_widget();

		self::assertTrue( is_plugin_active( 'elementor/elementor.php' ) );
		self::assertTrue( is_plugin_active( 'ultimate-elementor/ultimate-elementor.php' ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/elementor/' ), $elementor_file );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/ultimate-elementor/' ),
			$ultimate_addons_file
		);
		self::assertSame( '1.39.5', constant( 'UAEL_VER' ) );
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Login();

		// Hooks from Base.
		self::assertSame( 10, has_action( 'elementor/frontend/widget/before_render', [ $subject, 'before_render' ] ) );
		self::assertSame( 10, has_action( 'elementor/frontend/widget/after_render', [ $subject, 'add_hcaptcha' ] ) );

		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );

		// Own hooks.
		self::assertSame( 10, has_filter( 'wp_authenticate_user', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test before_render() and add_hcaptcha().
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_render(): void {
		$form = '<form>some HTML<div class="elementor-field-group something"><button type="submit">Login</button></div></form>';

		$subject = new Login();

		// Test with a wrong element.
		$element = $this->get_elementor_widget( 'heading' );

		ob_start();
		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha( $element );
		$output = ob_get_clean();

		self::assertSame( $form, $output );

		// Test with a correct element and login limit not exceeded.
		$element = $this->get_login_widget();

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		ob_start();
		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha( $element );
		$output = ob_get_clean();

		self::assertSame( $form, $output );

		// Test with a correct element and login limit exceeded.
		remove_filter( 'hcap_login_limit_exceeded', '__return_false' );
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$args        = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'ultimate-elementor/ultimate-elementor.php' ],
				'form_id' => 'login',
			],
		];
		$hcaptcha    = $this->get_hcap_form( $args );
		$hcap_form   =
			'<div class="elementor-field-group elementor-column elementor-col-100 elementor-hcaptcha">' .
			'<div class="uael-urf-field-wrapper">' .
			$hcaptcha .
			'</div>' .
			'</div>';
		$pattern     = '/(<div class="elementor-field-group.+?<button type="submit")/s';
		$replacement = $hcap_form . "\n$1";
		$expected    = preg_replace( $pattern, $replacement, $form );

		ob_start();
		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha( $element );
		$output = ob_get_clean();

		self::assertSame( $expected, $output );

		add_filter( 'hcap_delay_api_event', '__return_true' );

		ob_start();
		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha( $element );
		$output = ob_get_clean();

		remove_filter( 'hcap_delay_api_event', '__return_true' );

		self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $output );
	}


	/**
	 * Test verify() when not in UAEL AJAX action -> returns original.
	 */
	public function test_verify_not_uael_action(): void {
		$subject = new Login();
		$errors  = new WP_Error();

		self::assertSame( $errors, $subject->verify( $errors, 'pass' ) );
	}

	/**
	 * Test verify() when in UAEL AJAX action, NOT login limit exceeded -> returns original.
	 */
	public function test_verify_not_exceeded(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$filter_result = null;

		add_action(
			'wp_ajax_nopriv_uael_login_form_submit',
			static function () use ( $subject, $user, &$filter_result ) {
				$filter_result = $subject->verify( $user, 'some pass' );
			}
		);

		do_action( 'wp_ajax_nopriv_uael_login_form_submit' );

		self::assertSame( $user, $filter_result );
	}

	/**
	 * Test verify() when in UAEL AJAX action, login limit exceeded and verified OK -> returns original.
	 */
	public function test_verify(): void {
		$subject = new Login();
		$user    = new WP_User( 1 );

		// Simulate successful verification.
		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$this->prepare_widget_id();

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$filter_result = null;

		add_action(
			'wp_ajax_nopriv_uael_login_form_submit',
			static function () use ( $subject, $user, &$filter_result ) {
				$filter_result = $subject->verify( $user, 'some pass' );
			}
		);

		do_action( 'wp_ajax_nopriv_uael_login_form_submit' );

		self::assertSame( $user, $filter_result );
	}

	/**
	 * Test verify() when in UAEL AJAX action, login limit exceeded and verification FAILS -> sends a JSON error.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_not_verified(): void {
		$user     = new WP_User( 1 );
		$response = [
			'success' => false,
			'data'    => [ 'hCaptchaError' => 'The hCaptcha is invalid.' ],
		];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		// Simulate failed verification.
		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login', false );
		$this->prepare_widget_id();

		$subject = new Login();

		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );
		add_action(
			'wp_ajax_nopriv_uael_login_form_submit',
			static function () use ( $subject, $user, &$filter_result ) {
				$filter_result = $subject->verify( $user, 'some pass' );
			}
		);

		ob_start();
		do_action( 'wp_ajax_nopriv_uael_login_form_submit' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		self::assertSame( json_encode( $response ), ob_get_clean() );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify() when widget id is bad.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_bad_widget_id(): void {
		$user     = new WP_User( 1 );
		$response = [
			'success' => false,
			'data'    => [ 'hCaptchaError' => 'Bad hCaptcha signature!' ],
		];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'login',
			]
		);

		$subject = new Login();

		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );
		add_action(
			'wp_ajax_nopriv_uael_login_form_submit',
			static function () use ( $subject, $user, &$filter_result ) {
				$filter_result = $subject->verify( $user, 'some pass' );
			}
		);

		ob_start();
		do_action( 'wp_ajax_nopriv_uael_login_form_submit' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		self::assertSame( json_encode( $response ), ob_get_clean() );

		self::assertSame( $expected, $die_arr );
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
	.uael-login-form .h-captcha {
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
	 * Get a widget registered by the live Elementor instance.
	 *
	 * @param string $widget_name Widget name.
	 *
	 * @return Widget_Base
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function get_elementor_widget( string $widget_name ): Widget_Base {
		$widget = ElementorPlugin::instance()->widgets_manager->get_widget_types( $widget_name );

		self::assertInstanceOf( Widget_Base::class, $widget );

		return $widget;
	}

	/**
	 * Get the live Ultimate Addons login widget.
	 *
	 * @return UltimateElementorLogin
	 */
	private function get_login_widget(): UltimateElementorLogin {
		$widgets_manager = ElementorPlugin::instance()->widgets_manager;
		$widget          = $widgets_manager->get_widget_types( 'uael-login-form' );

		if ( ! $widget ) {
			LoginFormModule::instance()->init_widgets();
			$widget = $widgets_manager->get_widget_types( 'uael-login-form' );
		}

		self::assertInstanceOf( UltimateElementorLogin::class, $widget );

		return $widget;
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
			'source'  => [ 'ultimate-elementor/ultimate-elementor.php' ],
			'form_id' => 'login',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
}
