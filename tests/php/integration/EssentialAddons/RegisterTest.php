<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EssentialAddons;

use Elementor\Plugin as ElementorPlugin;
use Essential_Addons_Elementor\Classes\Bootstrap;
use Essential_Addons_Elementor\Elements\Login_Register as EssentialAddonsLoginRegister;
use HCaptcha\EssentialAddons\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class RegisterTest
 *
 * @group essential-addons
 * @group essential-addons-register
 */
class RegisterTest extends HCaptchaPluginWPTestCase {
	/**
	 * Plugin relative paths.
	 *
	 * @var string[]
	 */
	protected static $plugin = [
		'elementor/elementor.php',
		'essential-addons-for-elementor-lite/essential_adons_elementor.php',
	];

	/**
	 * Hooks to replay after loading the plugin.
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
		unset( $_POST['widget_id'], $_SERVER['HTTP_REFERER'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'eael/login-register/after-password-field', [ $subject, 'add_register_hcaptcha' ] )
		);
		self::assertSame( 10, has_action( 'eael/login-register/before-register', [ $subject, 'verify' ] ) );

		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );

		self::assertSame( 0, has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );

		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test add_register_hcaptcha().
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_register_hcaptcha(): void {
		$widget   = $this->get_login_register_widget();
		$args     = [
			'action' => 'hcaptcha_essential_addons_register',
			'name'   => 'hcaptcha_essential_addons_register_nonce',
			'id'     => [
				'source'  => [
					'essential-addons-elementor/essential_adons_elementor.php',
					'essential-addons-for-elementor-lite/essential_adons_elementor.php',
				],
				'form_id' => 'register',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Register();

		ob_start();

		$subject->add_register_hcaptcha( $widget );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_register_hcaptcha() with built-in form interaction.
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_register_hcaptcha_with_form_interaction(): void {
		$widget  = $this->get_login_register_widget();
		$subject = new Register();
		$level   = ob_get_level();

		add_filter( 'hcap_delay_api_event', '__return_true' );

		try {
			ob_start();
			$subject->add_register_hcaptcha( $widget );
			$actual = (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			remove_filter( 'hcap_delay_api_event', '__return_true' );
		}

		self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $actual );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$this->prepare_verify_post(
			'hcaptcha_essential_addons_register_nonce',
			'hcaptcha_essential_addons_register'
		);
		$this->prepare_widget_id();

		$subject = new Register();

		$subject->verify();
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @param bool $has_referer Has referer.
	 *
	 * @return void
	 * @dataProvider dp_test_verify_not_verified
	 */
	public function test_verify_not_verified( bool $has_referer ): void {
		$widget_id     = 'some_id';
		$error_message = 'The hCaptcha is invalid.';
		$referer       = 'some-referer';
		$die_arr       = [];
		$setcookie     = [];
		$redirect      = false;
		$expected      = [
			'',
			'',
			[ 'response' => null ],
		];

		$_POST['widget_id'] = $widget_id;

		if ( $has_referer ) {
			$_SERVER['HTTP_REFERER'] = $referer;
		}

		$this->prepare_verify_post(
			'hcaptcha_essential_addons_register_nonce',
			'hcaptcha_essential_addons_register',
			false
		);
		$this->prepare_widget_id(
			[
				'source'  => [],
				'form_id' => 'register',
			]
		);

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter(
			'wp_redirect',
			static function () use ( &$redirect ) {
				$redirect = true;

				return false;
			}
		);

		FunctionMocker::replace(
			'setcookie',
			static function ( $name, $value ) use ( &$setcookie ) {
				$setcookie = [ $name, $value ];
			}
		);

		$subject = Mockery::mock( Register::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'exit' )->with()->times( (int) $has_referer );

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":false,"data":"' . $error_message . '"}', $json );
		self::assertSame( 'eael_login_error_' . $widget_id, $setcookie[0] );
		self::assertSame( $error_message, $setcookie[1] );
		self::assertSame( $redirect, $has_referer );
	}

	/**
	 * Data provider for test_verify_not_verified().
	 *
	 * @return array
	 */
	public function dp_test_verify_not_verified(): array {
		return [
			[ false ],
			[ true ],
		];
	}

	/**
	 * Test verify() when widget id is bad.
	 *
	 * @return void
	 */
	public function test_verify_bad_widget_id(): void {
		$widget_id     = 'some_id';
		$error_message = 'Bad hCaptcha signature!';
		$die_arr       = [];
		$setcookie     = [];
		$expected      = [
			'',
			'',
			[ 'response' => null ],
		];

		$_POST['widget_id'] = $widget_id;

		$this->prepare_verify_post(
			'hcaptcha_essential_addons_register_nonce',
			'hcaptcha_essential_addons_register'
		);
		$this->prepare_widget_id(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'register',
			]
		);

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		FunctionMocker::replace(
			'setcookie',
			static function ( $name, $value ) use ( &$setcookie ) {
				$setcookie = [ $name, $value ];
			}
		);

		$subject = new Register();

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":false,"data":"' . $error_message . '"}', $json );
		self::assertSame( 'eael_login_error_' . $widget_id, $setcookie[0] );
		self::assertSame( $error_message, $setcookie[1] );
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
	#eael-register-form .h-captcha {
		margin-top: 1rem;
		margin-bottom: 0;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Register();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Get the live Essential Addons login/register widget.
	 *
	 * @return EssentialAddonsLoginRegister
	 */
	private function get_login_register_widget(): EssentialAddonsLoginRegister {
		$widgets_manager = ElementorPlugin::instance()->widgets_manager;
		$widget          = $widgets_manager->get_widget_types( 'eael-login-register' );

		if ( ! $widget ) {
			Bootstrap::instance()->register_elements( $widgets_manager );
			$widget = $widgets_manager->get_widget_types( 'eael-login-register' );
		}

		self::assertInstanceOf( EssentialAddonsLoginRegister::class, $widget );

		return $widget;
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param array $id The hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [
				'essential-addons-elementor/essential_adons_elementor.php',
				'essential-addons-for-elementor-lite/essential_adons_elementor.php',
			],
			'form_id' => 'register',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
}
