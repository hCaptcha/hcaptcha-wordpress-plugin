<?php
/**
 * GeneralTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Wordfence;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\Wordfence\General;
use HCaptcha\WP\Login;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WordfenceLS\Controller_CAPTCHA as WordfenceCaptcha;
use WordfenceLS\Controller_Settings as WordfenceSettings;
use WordfenceLS\Controller_WordfenceLS;
use WordfenceLS\Model_View as WordfenceView;
use WordfenceLS\ViewNotFoundException;

/**
 * Test General class.
 *
 * @group wordfence
 */
class GeneralTest extends HCaptchaPluginWPTestCase {
	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'wordfence-login-security/wordfence-login-security.php';

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Restore the live plugin hooks reset by WordPress test isolation.
		Controller_WordfenceLS::shared()->init();
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$settings = WordfenceSettings::shared();

		foreach ( $this->get_wordfence_captcha_setting_keys() as $setting_key ) {
			$settings->remove( $setting_key );
		}

		wp_dequeue_script( 'wordfence-ls-recaptcha' );
		wp_deregister_script( 'wordfence-ls-recaptcha' );
		wp_dequeue_script( 'wordfence-ls-hcaptcha' );
		wp_deregister_script( 'wordfence-ls-hcaptcha' );
		wp_dequeue_script( 'admin-wordfence' );
		wp_deregister_script( 'admin-wordfence' );

		parent::tearDown();
	}

	/**
	 * Test that the live Wordfence Login Security plugin is loaded.
	 *
	 * @return void
	 */
	public function test_live_plugin_is_loaded(): void {
		$plugin_dir  = realpath( WP_PLUGIN_DIR . '/wordfence-login-security' );
		$plugin_file = wp_normalize_path( ( new ReflectionClass( Controller_WordfenceLS::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( 'wordfence-login-security/wordfence-login-security.php' ) );
		self::assertNotFalse( $plugin_dir );
		self::assertStringStartsWith(
			trailingslashit( wp_normalize_path( $plugin_dir ) ),
			$plugin_file
		);
		self::assertTrue( version_compare( constant( 'WORDFENCE_LS_VERSION' ), '1.1.16', '>=' ) );
	}

	/**
	 * Test init_hooks().
	 *
	 * @param string $wordfence_status Wordfence status.
	 * @dataProvider dp_test_init_hooks
	 */
	public function test_init_hooks( string $wordfence_status ): void {
		if ( 'login' === $wordfence_status ) {
			update_option(
				'hcaptcha_settings',
				[
					'wordfence_status' => [ 'login' ],
				]
			);
		}

		hcaptcha()->init_hooks();

		$subject = new General();

		if ( 'login' === $wordfence_status ) {
			self::assertSame( [ 'on' ], hcaptcha()->settings()->get( 'recaptcha_compat_off' ) );
			self::assertSame( 20, has_action( 'login_enqueue_scripts', [ $subject, 'remove_wordfence_recaptcha_script' ] ) );
			self::assertSame( 10, has_filter( 'wordfence_ls_require_captcha', [ $subject, 'block_wordfence_recaptcha' ] ) );
			self::assertSame(
				self::has_native_hcaptcha_support() ? 10 : false,
				has_action( 'admin_enqueue_scripts', [ $subject, 'admin_enqueue_scripts' ] )
			);
		} else {
			self::assertSame( 10, has_action( 'plugins_loaded', [ $subject, 'remove_wp_login_hcaptcha_hooks' ] ) );
		}
	}

	/**
	 * Data provider for test_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_hooks(): array {
		return [
			'not active' => [ '' ],
			'active'     => [ 'login' ],
		];
	}

	/**
	 * Test that the live Wordfence CAPTCHA script is removed.
	 *
	 * @param string $provider      CAPTCHA provider.
	 * @param string $script_handle Wordfence script handle.
	 *
	 * @return void
	 * @dataProvider dp_test_remove_wordfence_captcha_script
	 */
	public function test_remove_wordfence_captcha_script( string $provider, string $script_handle ): void {
		$this->skip_unsupported_hcaptcha_provider( $provider );
		$this->configure_wordfence_captcha( $provider );
		$this->enable_wordfence_integration();

		$wordfence_enqueued_script = false;

		add_action(
			'login_enqueue_scripts',
			static function () use ( &$wordfence_enqueued_script, $script_handle ) {
				$wordfence_enqueued_script = wp_script_is( $script_handle );
			},
			15
		);

		self::assertTrue( WordfenceCaptcha::shared()->enabled() );
		self::assertFalse( wp_script_is( $script_handle, 'registered' ) );

		do_action( 'login_enqueue_scripts' );

		self::assertTrue( $wordfence_enqueued_script );
		self::assertFalse( wp_script_is( $script_handle ) );
		self::assertFalse( wp_script_is( $script_handle, 'registered' ) );
	}

	/**
	 * Data provider for test_remove_wordfence_captcha_script().
	 *
	 * @return array
	 */
	public function dp_test_remove_wordfence_captcha_script(): array {
		return [
			'reCAPTCHA' => [ 'recaptcha', 'wordfence-ls-recaptcha' ],
			'hCaptcha'  => [ 'hcaptcha', 'wordfence-ls-hcaptcha' ],
		];
	}

	/**
	 * Test block_wordfence_recaptcha().
	 *
	 * @param string $provider CAPTCHA provider.
	 *
	 * @return void
	 * @dataProvider dp_test_captcha_provider
	 */
	public function test_block_wordfence_recaptcha( string $provider ): void {
		$this->skip_unsupported_hcaptcha_provider( $provider );
		$this->configure_wordfence_captcha( $provider );

		self::assertTrue( WordfenceCaptcha::shared()->is_captcha_required() );

		$subject = $this->enable_wordfence_integration();

		self::assertFalse( $subject->block_wordfence_recaptcha() );
		self::assertFalse( WordfenceCaptcha::shared()->is_captcha_required() );
	}

	/**
	 * Test that the admin selectors target the live Wordfence hCaptcha UI.
	 *
	 * @return void
	 * @throws ViewNotFoundException ViewNotFoundException.
	 */
	public function test_live_wordfence_hcaptcha_admin_ui(): void {
		$this->skip_unsupported_hcaptcha_provider( 'hcaptcha' );

		$html = WordfenceView::create( 'settings/hcaptcha-ui' )->render();

		self::assertStringContainsString( 'id="wfls-hcaptcha-settings"', $html );
		self::assertStringContainsString( 'id="wfls-enable-hcaptcha"', $html );
		self::assertStringContainsString( 'id="input-hcaptchaSiteKey"', $html );
		self::assertStringContainsString( 'id="input-hcaptchaSecret"', $html );
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @param string $hook_suffix Wordfence admin page hook suffix.
	 *
	 * @return void
	 * @dataProvider dp_test_admin_enqueue_scripts
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_admin_enqueue_scripts( string $hook_suffix ): void {
		$subject = $this->enable_wordfence_integration();

		$subject->admin_enqueue_scripts( 'some_page' );

		self::assertFalse( wp_script_is( 'admin-wordfence' ) );

		$subject->admin_enqueue_scripts( $hook_suffix );

		if ( ! self::has_native_hcaptcha_support() ) {
			self::assertFalse( wp_script_is( 'admin-wordfence' ) );

			return;
		}

		self::assertTrue( wp_script_is( 'admin-wordfence' ) );

		$script = wp_scripts()->registered['admin-wordfence'];
		$notice = HCaptcha::get_hcaptcha_plugin_notice();
		$data   = wp_scripts()->get_data( 'admin-wordfence', 'data' );
		$prefix = 'var HCaptchaWordfenceObject = ';
		$json   = substr( $data, strlen( $prefix ), -1 );

		self::assertSame( [ 'jquery' ], $script->deps );
		self::assertStringEndsWith( '/assets/js/admin-wordfence.min.js', $script->src );
		self::assertStringStartsWith( $prefix, $data );
		self::assertSame(
			[
				'noticeLabel'       => $notice['label'],
				'noticeDescription' => html_entity_decode( $notice['description'], ENT_QUOTES, 'UTF-8' ),
			],
			json_decode( $json, true )
		);
	}

	/**
	 * Data provider for test_admin_enqueue_scripts().
	 *
	 * @return array
	 */
	public function dp_test_admin_enqueue_scripts(): array {
		return [
			'standalone plugin' => [ 'toplevel_page_WFLS' ],
			'Wordfence plugin'  => [ 'wordfence_page_WFLS' ],
		];
	}

	/**
	 * Test the real Wordfence authentication path with native CAPTCHA blocked.
	 *
	 * @param string $provider CAPTCHA provider.
	 *
	 * @return void
	 * @dataProvider dp_test_captcha_provider
	 */
	public function test_live_wordfence_authentication( string $provider ): void {
		$this->skip_unsupported_hcaptcha_provider( $provider );
		$this->configure_wordfence_captcha( $provider );
		$this->enable_wordfence_integration();

		$user_id  = $this->factory()->user->create(
			[
				'user_login' => 'wordfence-user',
				'user_pass'  => 'wordfence-password',
			]
		);
		$user     = get_user_by( 'id', $user_id );
		$requests = 0;

		add_filter(
			'pre_http_request',
			static function ( $response ) use ( &$requests ) {
				++$requests;

				return $response;
			}
		);

		$result = Controller_WordfenceLS::shared()->_authenticate(
			$user,
			'wordfence-user',
			'wordfence-password'
		);

		self::assertSame( $user, $result );
		self::assertSame( 0, $requests );
	}

	/**
	 * Data provider for live Wordfence CAPTCHA tests.
	 *
	 * @return array
	 */
	public function dp_test_captcha_provider(): array {
		return [
			'reCAPTCHA' => [ 'recaptcha' ],
			'hCaptcha'  => [ 'hcaptcha' ],
		];
	}

	/**
	 * Test remove_wp_login_hcaptcha_hooks().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_remove_wp_login_hcaptcha_hooks(): void {
		$subject = new General();

		$subject->remove_wp_login_hcaptcha_hooks();

		$main     = hcaptcha();
		$wp_login = new Login();

		$loaded_classes                 = $this->get_protected_property( $main, 'loaded_classes' );
		$loaded_classes[ Login::class ] = $wp_login;

		$this->set_protected_property( $main, 'loaded_classes', $loaded_classes );

		$wp_login = hcaptcha()->get( Login::class );

		self::assertSame( 10, has_action( 'login_form', [ $wp_login, 'add_captcha' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'wp_authenticate_user', [ $wp_login, 'check_signature' ] ) );

		$subject->remove_wp_login_hcaptcha_hooks();

		self::assertFalse( has_action( 'login_form', [ $wp_login, 'add_captcha' ] ) );
		self::assertFalse( has_filter( 'wp_authenticate_user', [ $wp_login, 'check_signature' ] ) );
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
	#loginform[style="position: relative;"] > .h-captcha {
	    visibility: hidden !important;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new General();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Configure the live Wordfence CAPTCHA provider.
	 *
	 * @param string $provider CAPTCHA provider.
	 *
	 * @return void
	 */
	private function configure_wordfence_captcha( string $provider ): void {
		$is_hcaptcha = 'hcaptcha' === $provider;
		$settings    = WordfenceSettings::shared();
		$values      = [
			WordfenceSettings::OPTION_ENABLE_AUTH_CAPTCHA => ! $is_hcaptcha,
			WordfenceSettings::OPTION_RECAPTCHA_SITE_KEY  => 'wordfence-recaptcha-site-key',
			WordfenceSettings::OPTION_RECAPTCHA_SECRET    => 'wordfence-recaptcha-secret',
		];

		if ( $is_hcaptcha ) {
			$values[ WordfenceSettings::OPTION_ENABLE_HCAPTCHA ]   = true;
			$values[ WordfenceSettings::OPTION_HCAPTCHA_SITE_KEY ] = 'wordfence-hcaptcha-site-key';
			$values[ WordfenceSettings::OPTION_HCAPTCHA_SECRET ]   = 'wordfence-hcaptcha-secret';
		}

		$settings->set_multiple( $values, true );
	}

	/**
	 * Enable the hCaptcha integration for Wordfence.
	 *
	 * @return General
	 */
	private function enable_wordfence_integration(): General {
		update_option(
			'hcaptcha_settings',
			[
				'wordfence_status' => [ 'login' ],
			]
		);

		hcaptcha()->init_hooks();

		return new General();
	}

	/**
	 * Get Wordfence CAPTCHA setting keys used by the tests.
	 *
	 * @return string[]
	 */
	private function get_wordfence_captcha_setting_keys(): array {
		$setting_keys = [
			WordfenceSettings::OPTION_ENABLE_AUTH_CAPTCHA,
			WordfenceSettings::OPTION_RECAPTCHA_SITE_KEY,
			WordfenceSettings::OPTION_RECAPTCHA_SECRET,
		];

		if ( self::has_native_hcaptcha_support() ) {
			$setting_keys[] = WordfenceSettings::OPTION_ENABLE_HCAPTCHA;
			$setting_keys[] = WordfenceSettings::OPTION_HCAPTCHA_SITE_KEY;
			$setting_keys[] = WordfenceSettings::OPTION_HCAPTCHA_SECRET;
		}

		return $setting_keys;
	}

	/**
	 * Skip a native hCaptcha test when Wordfence does not support the provider.
	 *
	 * @param string $provider CAPTCHA provider.
	 *
	 * @return void
	 */
	private function skip_unsupported_hcaptcha_provider( string $provider ): void {
		if ( 'hcaptcha' === $provider && ! self::has_native_hcaptcha_support() ) {
			self::markTestSkipped( 'Wordfence does not have native hCaptcha support.' );
		}
	}

	/**
	 * Determine whether Wordfence has native hCaptcha support.
	 *
	 * @return bool
	 */
	private static function has_native_hcaptcha_support(): bool {
		return defined( 'WordfenceLS\\Controller_CAPTCHA::PROVIDER_HCAPTCHA' ) &&
			defined( 'WordfenceLS\\Controller_Settings::OPTION_ENABLE_HCAPTCHA' ) &&
			defined( 'WordfenceLS\\Controller_Settings::OPTION_HCAPTCHA_SITE_KEY' ) &&
			defined( 'WordfenceLS\\Controller_Settings::OPTION_HCAPTCHA_SECRET' );
	}
}
