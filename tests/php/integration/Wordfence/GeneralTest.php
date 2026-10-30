<?php
/**
 * GeneralTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Wordfence;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\Wordfence\General;
use HCaptcha\WP\Login;
use HCaptcha\WP\Register;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WordfenceLS\Controller_CAPTCHA as WordfenceCaptcha;
use WordfenceLS\Controller_Settings as WordfenceSettings;
use WordfenceLS\Controller_WordfenceLS;
use WordfenceLS\Model_View as WordfenceView;
use WordfenceLS\ViewNotFoundException;
use WP_Error;
use function Patchwork\relay;

/**
 * Test General class.
 *
 * @group wordfence
 */
class GeneralTest extends HCaptchaPluginWPTestCase {
	/**
	 * Register the admin notice when the installed Wordfence API exposes native hCaptcha settings.
	 */
	public function test_admin_script_with_native_hcaptcha_capability(): void {
		FunctionMocker::replace(
			'defined',
			static function ( string $name ): bool {
				if ( 0 === strpos( $name, 'WordfenceLS\\Controller_' ) ) {
					return true;
				}

				return relay();
			}
		);
		hcaptcha()->settings()->set( 'wordfence_status', [ 'login' ] );
		$subject = new General();

		self::assertSame( 10, has_action( 'admin_enqueue_scripts', [ $subject, 'admin_enqueue_scripts' ] ) );
		$subject->admin_enqueue_scripts( 'other' );
		self::assertFalse( wp_script_is( 'admin-wordfence' ) );
		$subject->admin_enqueue_scripts( 'toplevel_page_WFLS' );
		self::assertTrue( wp_script_is( 'admin-wordfence' ) );
		self::assertStringContainsString( 'HCaptchaWordfenceObject', wp_scripts()->get_data( 'admin-wordfence', 'data' ) );
	}

	/**
	 * Wordfence enablement gates signed WordPress login protection.
	 */
	public function test_login_protection_filter_uses_wordfence_setting(): void {
		$subject = new General();

		self::assertFalse( $subject->filter_wp_login_protection_enabled( true ) );
		hcaptcha()->settings()->set( 'wordfence_status', [ 'login' ] );
		self::assertTrue( $subject->filter_wp_login_protection_enabled( true ) );
		self::assertFalse( $subject->filter_wp_login_protection_enabled( false ) );
	}

	/**
	 * An unsigned native registration still belongs to the active hCaptcha verifier.
	 */
	public function test_unsigned_native_registration_keeps_hcaptcha_owner(): void {
		$subject = $this->enable_wordfence_integration( [ 'register' ] );
		$this->mark_native_registration_context();
		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::decode_id_info',
			[
				'valid' => false,
				'id'    => [],
			]
		);

		self::assertTrue( $this->set_method_accessibility( $subject, 'is_native_registration_protected' )->invoke( $subject ) );
	}


	/**
	 * HCaptcha modules loaded before each test.
	 *
	 * @var array<string, object>
	 */
	private array $original_loaded_classes = [];

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'wordfence/wordfence.php';

	/**
	 * Wordfence can expose recoverable duplicate-column migration errors on Windows MySQL.
	 *
	 * @var array<string, string[]>
	 */
	protected static array $plugin_allowed_activation_errors = [
		'wordfence/wordfence.php' => [ 'unexpected_output' ],
	];

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_loaded_classes = $this->get_protected_property( hcaptcha(), 'loaded_classes' );

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
		wp_dequeue_script( 'wordfence-ls-login' );
		wp_deregister_script( 'wordfence-ls-login' );
		wp_dequeue_script( 'admin-wordfence' );
		wp_deregister_script( 'admin-wordfence' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['action'] );
		$this->set_protected_property( hcaptcha(), 'loaded_classes', $this->original_loaded_classes );

		parent::tearDown();
	}

	/**
	 * Test that the live Wordfence Login Security plugin is loaded.
	 *
	 * @return void
	 */
	public function test_live_plugin_is_loaded(): void {
		$plugin_dir  = realpath( WP_PLUGIN_DIR . '/wordfence' );
		$plugin_file = wp_normalize_path( ( new ReflectionClass( Controller_WordfenceLS::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( 'wordfence/wordfence.php' ) );
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

		self::assertSame(
			10,
			has_filter( 'hcap_wp_login_protection_enabled', [ $subject, 'filter_wp_login_protection_enabled' ] )
		);

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
		$this->enable_wordfence_integration( [ 'register' ] );
		$this->mark_native_registration_context();

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
		self::assertFalse( wp_script_is( 'wordfence-ls-login' ) );
		self::assertFalse( wp_script_is( 'wordfence-ls-login', 'registered' ) );
	}

	/**
	 * Keep Wordfence CAPTCHA script when no hCaptcha verifier owns the page.
	 *
	 * @param string   $action    WordPress login action.
	 * @param string[] $wp_status Enabled native hCaptcha forms.
	 * @dataProvider dp_test_preserve_wordfence_captcha_script
	 */
	public function test_preserve_wordfence_captcha_script( string $action, array $wp_status ): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( $wp_status );

		if ( 'register' === $action ) {
			$this->mark_native_registration_context();
		} else {
			$this->mark_native_login_context();
		}

		do_action( 'login_enqueue_scripts' );

		self::assertTrue( wp_script_is( 'wordfence-ls-recaptcha' ) );
		self::assertTrue( wp_script_is( 'wordfence-ls-login' ) );
		self::assertStringContainsString( 'recaptchacompat=off', hcaptcha()->get_api_src() );
	}

	/**
	 * Data provider for native pages that remain owned by Wordfence.
	 *
	 * @return array
	 */
	public function dp_test_preserve_wordfence_captcha_script(): array {
		return [
			'login hCaptcha off'        => [ 'login', [] ],
			'login hCaptcha on'         => [ 'login', [ 'login' ] ],
			'registration hCaptcha off' => [ 'register', [ 'login' ] ],
		];
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

		$subject = $this->enable_wordfence_integration( [ 'login' ] );
		$this->mark_native_login_context();

		self::assertTrue( $subject->block_wordfence_recaptcha( true ) );
		self::assertFalse( $subject->block_wordfence_recaptcha( false ) );
		self::assertTrue( $this->wordfence_captcha_required_during_authentication() );
	}

	/**
	 * Wordfence must still verify login when its hCaptcha replacement is disabled.
	 */
	public function test_native_wordfence_login_without_hcaptcha_replacement(): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration();
		$this->mark_native_login_context();

		self::assertTrue( $this->wordfence_captcha_required_during_authentication() );
	}

	/**
	 * Wordfence AJAX credential preflight must keep its CAPTCHA requirement.
	 */
	public function test_native_wordfence_login_preflight_with_hcaptcha_replacement(): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( [ 'login' ] );

		self::assertTrue( $this->wordfence_captcha_required_during_authentication() );
	}

	/**
	 * Wordfence must still verify registration when its hCaptcha replacement is disabled.
	 *
	 * @param string[] $wp_status       Enabled native hCaptcha forms.
	 * @param bool     $native_context Whether this is the native WP registration action.
	 * @param bool     $expected       Whether Wordfence should require its own token.
	 * @dataProvider dp_test_native_wordfence_registration
	 */
	public function test_native_wordfence_registration( array $wp_status, bool $native_context, bool $expected ): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( $wp_status );

		if ( $native_context ) {
			$this->mark_native_registration_context();
		} else {
			$_GET['action'] = 'register';
		}

		$errors = new WP_Error();

		do_action( 'register_post', 'wordfence-user', 'wordfence@example.test', $errors );

		self::assertSame( $expected, $errors->has_errors() );
		self::assertSame( $expected ? 'wfls_captcha_required' : '', $errors->get_error_code() );
	}

	/**
	 * Data provider for Wordfence registration coverage.
	 *
	 * @return array
	 */
	public function dp_test_native_wordfence_registration(): array {
		return [
			'no replacement'       => [ [], true, true ],
			'login only'           => [ [ 'login' ], true, true ],
			'registration enabled' => [ [ 'register' ], true, false ],
			'alternate route'      => [ [ 'register' ], false, true ],
		];
	}

	/**
	 * An earlier registration error callback cannot skip the active hCaptcha verifier.
	 */
	public function test_registration_error_filter_keeps_hcaptcha_verifier(): void {
		$this->enable_wordfence_integration( [ 'register' ] );
		$this->mark_native_registration_context();

		add_filter(
			'registration_errors',
			static function () {
				return new WP_Error( 'earlier', 'Earlier registration error.' );
			},
			1
		);

		$errors = apply_filters( 'registration_errors', new WP_Error(), 'wordfence-user', 'wordfence@example.test' );

		self::assertContains( 'earlier', $errors->get_error_codes() );
		self::assertContains( 'bad-signature', $errors->get_error_codes() );
	}

	/**
	 * Removing the replacement verifier restores the native Wordfence requirement.
	 */
	public function test_native_registration_without_active_hcaptcha_callback(): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( [ 'register' ] );
		$this->mark_native_registration_context();

		$register = hcaptcha()->get( Register::class );
		remove_filter( 'registration_errors', [ $register, 'verify' ] );

		$errors = new WP_Error();
		do_action( 'register_post', 'wordfence-user', 'wordfence@example.test', $errors );

		self::assertSame( 'wfls_captcha_required', $errors->get_error_code() );
	}

	/**
	 * Keep Wordfence when another hCaptcha registration verifier owns the request.
	 */
	public function test_native_registration_with_foreign_hcaptcha_owner(): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( [ 'register' ] );
		$this->mark_native_registration_context();

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::decode_id_info',
			[
				'valid' => true,
				'id'    => [
					'source'  => [ 'ForeignRegistration' ],
					'form_id' => 'register',
				],
			]
		);
		add_filter(
			'hcap_registration_request_owner',
			static function () {
				return 'ForeignRegistration';
			},
			1
		);

		$errors = new WP_Error();
		do_action( 'register_post', 'wordfence-user', 'wordfence@example.test', $errors );

		self::assertSame( 'wfls_captcha_required', $errors->get_error_code() );

		do_action( 'login_enqueue_scripts' );

		self::assertTrue( wp_script_is( 'wordfence-ls-recaptcha' ) );
		self::assertTrue( wp_script_is( 'wordfence-ls-login' ) );
	}

	/**
	 * The native registration verifier owns its signed widget request.
	 */
	public function test_native_registration_with_own_hcaptcha_widget(): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( [ 'register' ] );
		$this->mark_native_registration_context();

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::decode_id_info',
			[
				'valid' => true,
				'id'    => [
					'source'  => HCaptcha::get_class_source( Register::class ),
					'form_id' => 'register',
				],
			]
		);

		$errors = new WP_Error();
		do_action( 'register_post', 'wordfence-user', 'wordfence@example.test', $errors );

		self::assertFalse( $errors->has_errors() );

		$errors = apply_filters( 'registration_errors', $errors, 'wordfence-user', 'wordfence@example.test' );

		self::assertTrue( $errors->has_errors() );
		self::assertNotContains( 'wfls_captcha_required', $errors->get_error_codes() );
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
		$data   = wp_scripts()->get_data( 'admin-wordfence', 'data' );
		$prefix = 'var HCaptchaWordfenceObject = ';
		$json   = substr( $data, strlen( $prefix ), -1 );

		self::assertSame( [ 'jquery' ], $script->deps );
		self::assertStringEndsWith( '/assets/js/admin-wordfence.min.js', $script->src );
		self::assertStringStartsWith( $prefix, $data );
		self::assertSame(
			[
				'noticeLabel'       => 'hCaptcha integration is active',
				'noticeDescription' => 'Wordfence CAPTCHA settings still apply to forms without hCaptcha protection.',
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
	 * Test that Wordfence verifies a native login when hCaptcha login is enabled.
	 *
	 * @return void
	 */
	public function test_live_wordfence_authentication(): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( [ 'login' ] );
		$this->mark_native_login_context();
		$_POST['wfls-captcha-token'] = 'wordfence-test-token';

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
			static function () use ( &$requests ) {
				++$requests;

				return [
					'headers'  => [],
					'body'     => '{"success":true,"score":0.9,"action":"login"}',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			}
		);

		$result = apply_filters(
			'authenticate',
			$user,
			'wordfence-user',
			'wordfence-password'
		);

		self::assertSame( $user, $result );
		self::assertSame( 1, $requests );
	}

	/**
	 * A login attempt without Wordfence token must not proceed to authentication.
	 */
	public function test_live_wordfence_authentication_without_token(): void {
		$this->configure_wordfence_captcha( 'recaptcha' );
		$this->enable_wordfence_integration( [ 'login' ] );
		$this->mark_native_login_context();

		$result = apply_filters( 'authenticate', null, 'nonexistent-wordfence-user', 'invalid-password' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'wfls_captcha_verify', $result->get_error_code() );
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
	 * @param string[] $wp_status Enabled native hCaptcha forms.
	 *
	 * @return General
	 */
	private function enable_wordfence_integration( array $wp_status = [] ): General {
		update_option(
			'hcaptcha_settings',
			[
				'wordfence_status' => [ 'login' ],
				'wp_status'        => $wp_status,
			]
		);

		hcaptcha()->init_hooks();
		$loaded_classes = $this->get_protected_property( hcaptcha(), 'loaded_classes' );

		foreach (
			[
				'login'    => Login::class,
				'register' => Register::class,
			] as $setting => $class_name
		) {
			if ( in_array( $setting, $wp_status, true ) ) {
				$loaded_classes[ $class_name ] = new $class_name();
			} else {
				unset( $loaded_classes[ $class_name ] );
			}
		}

		$this->set_protected_property( hcaptcha(), 'loaded_classes', $loaded_classes );

		return new General();
	}

	/**
	 * Mark the native WordPress login action as dispatched by wp-login.php.
	 */
	private function mark_native_login_context(): void {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Mark the native WordPress registration action as dispatched by wp-login.php.
	 */
	private function mark_native_registration_context(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php?action=register';
		$_GET['action']         = 'register';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_register']  = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Read the actual Wordfence requirement from its authentication filter chain.
	 *
	 * @return bool
	 */
	private function wordfence_captcha_required_during_authentication(): bool {
		$required = false;
		$check    = static function ( $user ) use ( &$required ) {
			$required = WordfenceCaptcha::shared()->is_captcha_required();

			return $user;
		};

		add_filter( 'authenticate', $check, 0 );
		apply_filters( 'authenticate', null, '', '' );
		remove_filter( 'authenticate', $check, 0 );

		return $required;
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
