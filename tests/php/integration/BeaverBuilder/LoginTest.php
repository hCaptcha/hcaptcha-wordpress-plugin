<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\BeaverBuilder;

use FLBuilderModel;
use HCaptcha\BeaverBuilder\Login;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ReflectionClass;
use WP_Error;
use WP_User;

/**
 * Class LoginTest
 *
 * @group beaver-builder
 * @group beaver-builder-login
 */
class LoginTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'bb-plugin/fl-builder.php';

	/**
	 * Hooks to replay after loading Beaver Builder.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Load Beaver Builder modules after the WordPress test bootstrap.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_current_filter'] );

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

		// Base hooks.
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );

		// Login hooks.
		self::assertSame(
			10,
			has_filter( 'fl_builder_render_module_content', [ $subject, 'add_beaver_builder_captcha' ] )
		);
		self::assertSame( 10, has_filter( 'wp_authenticate_user', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test add_beaver_builder_captcha().
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_beaver_builder_captcha(): void {
		$button    = '<div class="fl-button-wrap some"><button class="fl-button">Submit</button></div>';
		$form      = '<div class="fl-login-form logout">' . $button . '</div>';
		$some_out  = 'some output';
		$form_out  = 'some output ' . $form . ' more';
		$args      = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'bb-plugin/fl-builder.php' ],
				'form_id' => 'login',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$hcaptcha  = '<div class="fl-input-group fl-hcaptcha">' . $hcap_form . '</div>';
		$expected  = 'some output <div class="fl-login-form ">' . $hcaptcha . $button . '</div> more';
		$module    = FLBuilderModel::$modules['login-form'];

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$subject = new Login();

		// Some output.
		self::assertSame( $some_out, $subject->add_beaver_builder_captcha( $some_out, $module ) );

		// Logout output.
		self::assertSame( $form_out, $subject->add_beaver_builder_captcha( $form_out, $module ) );

		$form_out = str_replace( 'logout', '', $form_out );

		// Login form in output.
		self::assertSame( $expected, $subject->add_beaver_builder_captcha( $form_out, $module ) );
	}

	/**
	 * Test add_beaver_builder_captcha() when login limit not exceeded.
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_beaver_builder_captcha_when_login_limit_not_exceeded(): void {
		$some_out = 'some output';
		$module   = FLBuilderModel::$modules['login-form'];

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$subject = new Login();

		self::assertSame( $some_out, $subject->add_beaver_builder_captcha( $some_out, $module ) );
	}

	/**
	 * Test hCaptcha in a form rendered by the live Beaver Builder module.
	 *
	 * @return void
	 */
	public function test_live_login_form(): void {
		$subject  = new Login();
		$module   = clone FLBuilderModel::$modules['login-form'];
		$settings = FLBuilderModel::get_module_defaults( 'login-form' );
		$class    = new ReflectionClass( $module );

		$module->node     = 'login-test';
		$module->settings = $settings;
		$id               = $module->node;

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		ob_start();
		include $module->dir . 'includes/frontend.php';
		$html = (string) ob_get_clean();
		$html = apply_filters( 'fl_builder_render_module_content', $html, $module );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/bb-plugin/' ),
			wp_normalize_path( (string) $class->getFileName() )
		);
		self::assertStringContainsString( 'class="fl-login-form', $html );
		self::assertStringContainsString( 'name="fl-login-form-name"', $html );
		self::assertStringContainsString( 'class="fl-input-group fl-hcaptcha"', $html );
		self::assertStringContainsString( 'name="hcaptcha_login_nonce"', $html );
		self::assertSame( 10, has_filter( 'fl_builder_render_module_content', [ $subject, 'add_beaver_builder_captcha' ] ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_verify(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'] = [ 'wp_ajax_nopriv_fl_builder_login_form_submit' ];

		$user = new WP_User( 1 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$this->prepare_widget_id();

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$subject = new Login();

		self::assertSame( $user, $subject->verify( $user, 'some password' ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'] = [ 'wp_ajax_nopriv_fl_builder_login_form_submit' ];

		$user          = new WP_User( 1 );
		$error_message = 'The hCaptcha is invalid.';
		$expected      = new WP_Error( 'fail', $error_message, 400 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );
		$this->prepare_widget_id();

		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$subject = new Login();

		self::assertEquals( $expected, $subject->verify( $user, 'some password' ) );
	}

	/**
	 * Test verify() when not doing action.
	 *
	 * @return void
	 */
	public function test_verify_not_verified_when_not_doing_action(): void {
		$user = new WP_User( 1 );

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, 'some password' ) );
	}

	/**
	 * Test verify() when the login limit is not exceeded.
	 *
	 * @return void
	 */
	public function test_verify_not_verified_when_login_limit_not_exceeded(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'] = [ 'wp_ajax_nopriv_fl_builder_login_form_submit' ];

		$user = new WP_User( 1 );

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, 'some password' ) );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'bb-plugin/fl-builder.php' ],
				'form_id' => 'login',
			]
		);
	}
}
