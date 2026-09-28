<?php
/**
 * NetworkSettingsAuthorizationTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Settings;

use HCaptcha\Abilities\Abilities;
use HCaptcha\Admin\OnboardingWizard;
use HCaptcha\Helpers\Request;
use HCaptcha\Settings\General;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\SettingsTransfer;
use HCaptcha\Settings\Tools;
use KAGG\Settings\Abstracts\SettingsBase;
use lucatume\WPBrowser\TestCase\WPAjaxTestCase;
use ReflectionClass;
use WPAjaxDieContinueException;

/**
 * Test network settings authorization against real WordPress multisite capabilities.
 */
class NetworkSettingsAuthorizationTest extends WPAjaxTestCase {
	/**
	 * Network site key.
	 */
	private const NETWORK_SITE_KEY = 'network-site-key';

	/**
	 * Network secret key.
	 */
	private const NETWORK_SECRET_KEY = 'network-secret-key';

	/**
	 * General settings tab.
	 *
	 * @var General|null
	 */
	private ?General $general = null;

	/**
	 * Tools settings tab.
	 *
	 * @var Tools|null
	 */
	private ?Tools $tools = null;

	/**
	 * Users temporarily granted super admin access.
	 *
	 * @var int[]
	 */
	private array $super_admins = [];

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();

		self::assertTrue( is_multisite() );
		self::assertSame( realpath( dirname( __DIR__, 4 ) . '/hcaptcha.php' ), realpath( HCAPTCHA_FILE ) );

		$settings = hcaptcha()->settings();

		self::assertNotNull( $settings );

		$this->general = $settings->get_tab( General::class );
		$this->tools   = $settings->get_tab( Tools::class );

		self::assertInstanceOf( General::class, $this->general );
		self::assertInstanceOf( Tools::class, $this->tools );

		add_action( 'wp_ajax_' . Tools::EXPORT_ACTION, [ $this->tools, 'ajax_handle_export' ] );

		delete_site_option( PluginSettingsBase::OPTION_NAME );
		delete_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE );
		delete_option( PluginSettingsBase::OPTION_NAME );
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		if ( $this->tools ) {
			remove_action( 'wp_ajax_' . Tools::EXPORT_ACTION, [ $this->tools, 'ajax_handle_export' ] );
		}

		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		foreach ( $this->super_admins as $user_id ) {
			revoke_super_admin( $user_id );
		}

		delete_site_option( PluginSettingsBase::OPTION_NAME );
		delete_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE );
		delete_option( PluginSettingsBase::OPTION_NAME );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Test that a main-site administrator cannot access active network settings.
	 */
	public function test_main_site_administrator_cannot_render_or_export_network_settings(): void {
		$user_id = $this->create_main_site_administrator();

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'site-local-key',
				'secret_key' => 'site-local-secret',
				'theme'      => 'light',
			]
		);

		$nonce       = wp_create_nonce( Tools::EXPORT_ACTION );
		$site_export = $this->export_settings( $nonce, true );

		self::assertSame( $user_id, get_current_user_id() );
		self::assertSame( 'site-local-key', $site_export['keys']['site_key'] ?? null );
		self::assertSame( 'site-local-secret', $site_export['keys']['secret_key'] ?? null );

		$this->activate_network_settings();

		self::assertNotFalse( wp_verify_nonce( $nonce, Tools::EXPORT_ACTION ) );
		$this->assert_page_access_denied();
		$this->assert_export_access_denied( $nonce, false );
		$this->assert_export_access_denied( $nonce, true );
		$this->assert_import_access_denied();
		self::assertFalse( ( new Abilities() )->can_export_settings() );
		self::assertFalse( ( new Abilities() )->can_import_settings() );
	}

	/**
	 * Test that a secondary-site administrator receives no broader network access.
	 */
	public function test_secondary_site_administrator_cannot_render_or_export_network_settings(): void {
		$blog_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$added   = add_user_to_blog( $blog_id, $user_id, 'administrator' );

		self::assertTrue( $added );

		switch_to_blog( $blog_id );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		self::assertTrue( current_user_can( 'manage_options' ) );
		self::assertFalse( current_user_can( 'manage_network_options' ) );

		$this->activate_network_settings();

		$nonce = wp_create_nonce( Tools::EXPORT_ACTION );

		$this->assert_page_access_denied();
		$this->assert_export_access_denied( $nonce, false );
		$this->assert_export_access_denied( $nonce, true );
		$this->assert_import_access_denied();
		self::assertFalse( ( new Abilities() )->can_export_settings() );
		self::assertFalse( ( new Abilities() )->can_import_settings() );
	}

	/**
	 * Test that a network administrator can render and export network settings.
	 */
	public function test_network_administrator_can_render_and_export_network_settings(): void {
		$this->create_network_administrator();
		$this->activate_network_settings();

		ob_start();
		$this->general->settings_base_page();
		$page = (string) ob_get_clean();

		self::assertStringContainsString( '<form', $page );
		self::assertStringNotContainsString( self::NETWORK_SECRET_KEY, $page );

		$secret_field = $this->render_secret_field();

		self::assertStringContainsString( 'id="secret_key"', $secret_field );
		self::assertStringContainsString( 'value=""', $secret_field );
		self::assertStringNotContainsString( self::NETWORK_SECRET_KEY, $secret_field );

		$nonce               = wp_create_nonce( Tools::EXPORT_ACTION );
		$export_without_keys = $this->export_settings( $nonce, false );
		$export_with_keys    = $this->export_settings( $nonce, true );

		self::assertArrayNotHasKey( 'keys', $export_without_keys );
		self::assertStringNotContainsString( self::NETWORK_SECRET_KEY, (string) wp_json_encode( $export_without_keys ) );
		self::assertSame( self::NETWORK_SITE_KEY, $export_with_keys['keys']['site_key'] ?? null );
		self::assertSame( self::NETWORK_SECRET_KEY, $export_with_keys['keys']['secret_key'] ?? null );
		self::assertTrue( ( new Abilities() )->can_export_settings() );
		self::assertTrue( ( new Abilities() )->can_import_settings() );
	}

	/**
	 * Test that a blank network secret field preserves the stored secret.
	 */
	public function test_blank_sensitive_field_preserves_network_secret(): void {
		$this->create_network_administrator();
		$this->activate_network_settings();

		update_option( PluginSettingsBase::OPTION_NAME, [ 'theme' => 'site-local-theme' ] );
		add_filter(
			'pre_update_option_' . PluginSettingsBase::OPTION_NAME,
			[ $this->general, 'pre_update_option_filter' ],
			10,
			2
		);

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'                 => self::NETWORK_SITE_KEY,
				'secret_key'               => '',
				'theme'                    => 'dark',
				SettingsBase::NETWORK_WIDE => [ 'on' ],
			]
		);

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( self::NETWORK_SECRET_KEY, $network_settings['secret_key'] ?? null );
		self::assertSame( 'dark', $network_settings['theme'] ?? null );

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'                 => self::NETWORK_SITE_KEY,
				'secret_key'               => 'replacement-network-secret',
				'theme'                    => 'dark',
				SettingsBase::NETWORK_WIDE => [ 'on' ],
			]
		);

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( 'replacement-network-secret', $network_settings['secret_key'] ?? null );
		self::assertSame( [ 'theme' => 'site-local-theme' ], get_option( PluginSettingsBase::OPTION_NAME ) );
	}

	/**
	 * Test that onboarding cannot copy network credentials into site-local settings.
	 */
	public function test_onboarding_cannot_copy_network_settings_to_site_option(): void {
		$this->create_main_site_administrator();

		$nonce = wp_create_nonce( OnboardingWizard::UPDATE_ACTION );

		$this->activate_network_settings();
		add_filter(
			'pre_update_option_' . PluginSettingsBase::OPTION_NAME,
			[ $this->general, 'pre_update_option_filter' ],
			10,
			2
		);

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME );
		$site_settings    = get_option( PluginSettingsBase::OPTION_NAME );
		$_POST            = [
			'nonce' => $nonce,
			'value' => 'completed',
		];
		$did_die          = false;

		self::assertNotFalse( wp_verify_nonce( $nonce, OnboardingWizard::UPDATE_ACTION ) );

		try {
			$this->_handleAjax( OnboardingWizard::UPDATE_ACTION );
		} catch ( WPAjaxDieContinueException $exception ) {
			$did_die = true;
		}

		self::assertTrue( $did_die );
		self::assertSame( $site_settings, get_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( $network_settings, get_site_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
		self::assertSame(
			[
				'success' => false,
				'data'    => 'You are not allowed to perform this action.',
			],
			json_decode( $this->_last_response, true )
		);
	}

	/**
	 * Test that automatic onboarding initialization cannot disclose network credentials.
	 */
	public function test_onboarding_initialization_preserves_network_ownership(): void {
		$this->create_main_site_administrator();
		$this->activate_network_settings();
		add_filter(
			'pre_update_option_' . PluginSettingsBase::OPTION_NAME,
			[ $this->general, 'pre_update_option_filter' ],
			10,
			2
		);

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME );
		$site_settings    = get_option( PluginSettingsBase::OPTION_NAME );

		( new OnboardingWizard( $this->general ) )->init();

		self::assertSame( $site_settings, get_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( $network_settings, get_site_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
	}

	/**
	 * Test that onboarding GET requests use the current settings ownership.
	 *
	 * @param string $action Nonce action.
	 *
	 * @dataProvider dp_test_onboarding_request_checks_network_ownership
	 */
	public function test_onboarding_request_checks_network_ownership( string $action ): void {
		$user_id = $this->create_main_site_administrator();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve the test request.
		$original_get = $_GET;
		$nonce        = wp_create_nonce( $action );

		try {
			$_GET[ OnboardingWizard::NONCE_PARAM ] = $nonce;

			self::assertTrue( OnboardingWizard::verify_request( $action ) );

			$this->activate_network_settings();

			self::assertNotFalse( wp_verify_nonce( $nonce, $action ) );
			self::assertFalse( OnboardingWizard::verify_request( $action ) );

			grant_super_admin( $user_id );
			$this->super_admins[] = $user_id;

			self::assertTrue( OnboardingWizard::verify_request( $action ) );
		} finally {
			$_GET = $original_get;
		}
	}

	/**
	 * Data provider for test_onboarding_request_checks_network_ownership().
	 *
	 * @return array
	 */
	public function dp_test_onboarding_request_checks_network_ownership(): array {
		return [
			'direct step' => [ OnboardingWizard::STEP_ACTION ],
			'auto setup'  => [ OnboardingWizard::AUTO_SETUP_ACTION ],
		];
	}

	/**
	 * Test that network administrators can still update onboarding state.
	 */
	public function test_network_administrator_can_update_onboarding(): void {
		$this->create_network_administrator();
		$this->activate_network_settings();
		add_filter(
			'pre_update_option_' . PluginSettingsBase::OPTION_NAME,
			[ $this->general, 'pre_update_option_filter' ],
			10,
			2
		);

		$site_settings = get_option( PluginSettingsBase::OPTION_NAME );

		( new OnboardingWizard( $this->general ) )->init();

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME );

		self::assertSame( 'completed', $network_settings[ OnboardingWizard::OPTION_NAME ] ?? null );
		self::assertSame( self::NETWORK_SECRET_KEY, $network_settings['secret_key'] ?? null );
		self::assertSame( $site_settings, get_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
	}

	/**
	 * Test that indirect updates cannot copy network credentials without settings-page filters.
	 */
	public function test_indirect_updates_preserve_network_ownership(): void {
		$this->create_main_site_administrator();
		$this->activate_network_settings();
		remove_all_filters( 'pre_update_option_' . PluginSettingsBase::OPTION_NAME );

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME );
		$site_settings    = get_option( PluginSettingsBase::OPTION_NAME );

		$this->general->update_option( 'whats_new', 'test-version' );
		hcaptcha()->settings()->update( 'wp_status', [ 'login' ] );

		self::assertSame( $site_settings, get_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( $network_settings, get_site_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
	}

	/**
	 * Test that blank credentials keep their active value when settings ownership changes.
	 *
	 * @param bool $network_wide Whether network-wide settings are initially active.
	 *
	 * @dataProvider dp_test_blank_secret_survives_ownership_change
	 */
	public function test_blank_secret_survives_ownership_change( bool $network_wide ): void {
		$this->create_network_administrator();
		$this->activate_network_settings();
		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'local-site-key',
				'secret_key' => 'local-secret-key',
			]
		);
		update_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, $network_wide ? [ 'on' ] : [] );
		$this->general->init();
		add_filter(
			'pre_update_option_' . PluginSettingsBase::OPTION_NAME,
			[ $this->general, 'pre_update_option_filter' ],
			10,
			2
		);

		$site_key   = $network_wide ? self::NETWORK_SITE_KEY : 'local-site-key';
		$secret_key = $network_wide ? self::NETWORK_SECRET_KEY : 'local-secret-key';

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'                 => $site_key,
				'secret_key'               => '',
				SettingsBase::NETWORK_WIDE => $network_wide ? [] : [ 'on' ],
			]
		);

		$settings = $network_wide
			? get_option( PluginSettingsBase::OPTION_NAME )
			: get_site_option( PluginSettingsBase::OPTION_NAME );

		self::assertSame( $site_key, $settings['site_key'] ?? null );
		self::assertSame( $secret_key, $settings['secret_key'] ?? null );
		self::assertSame( $network_wide ? [] : [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
	}

	/**
	 * Data provider for test_blank_secret_survives_ownership_change().
	 *
	 * @return array
	 */
	public function dp_test_blank_secret_survives_ownership_change(): array {
		return [
			'network to site' => [ true ],
			'site to network' => [ false ],
		];
	}

	/**
	 * Create and select an administrator on the network main site.
	 *
	 * @return int
	 */
	private function create_main_site_administrator(): int {
		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		self::assertSame( get_main_site_id(), get_current_blog_id() );

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		wp_set_current_user( $user_id );

		self::assertTrue( current_user_can( 'manage_options' ) );
		self::assertFalse( current_user_can( 'manage_network_options' ) );

		return $user_id;
	}

	/**
	 * Create and select a network administrator.
	 *
	 * @return int
	 */
	private function create_network_administrator(): int {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		grant_super_admin( $user_id );
		$this->super_admins[] = $user_id;

		wp_set_current_user( $user_id );

		self::assertTrue( current_user_can( 'manage_options' ) );
		self::assertTrue( current_user_can( 'manage_network_options' ) );

		return $user_id;
	}

	/**
	 * Activate network settings with known credentials.
	 */
	private function activate_network_settings(): void {
		update_site_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'                 => self::NETWORK_SITE_KEY,
				'secret_key'               => self::NETWORK_SECRET_KEY,
				'theme'                    => 'light',
				SettingsBase::NETWORK_WIDE => [ 'on' ],
			]
		);
		update_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [ 'on' ] );

		$this->general->init();
	}

	/**
	 * Assert that direct settings page rendering is denied without leaking credentials.
	 */
	private function assert_page_access_denied(): void {
		foreach ( hcaptcha()->settings()->get_tabs() as $tab ) {
			$this->assert_tab_access_denied( $tab );
		}
	}

	/**
	 * Assert that a settings tab denies direct rendering.
	 *
	 * @param PluginSettingsBase $tab Settings tab.
	 */
	private function assert_tab_access_denied( PluginSettingsBase $tab ): void {
		$die_message     = '';
		$buffer_level    = ob_get_level();
		$not_ajax_filter = static function (): bool {
			return false;
		};
		$die_filter      = static function ( $handler ) use ( &$die_message ): callable {
			return static function ( $message ) use ( &$die_message ): void {
				$die_message = (string) $message;
			};
		};

		add_filter( 'wp_doing_ajax', $not_ajax_filter, PHP_INT_MAX );
		add_filter( 'wp_die_handler', $die_filter, PHP_INT_MAX );

		ob_start();

		try {
			$tab->settings_base_page();
			$output = (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			remove_filter( 'wp_die_handler', $die_filter, PHP_INT_MAX );
			remove_filter( 'wp_doing_ajax', $not_ajax_filter, PHP_INT_MAX );
		}

		self::assertSame( 'You are not allowed to access this page.', $die_message );
		self::assertSame( '', $output );
		self::assertStringNotContainsString( self::NETWORK_SITE_KEY, $output );
		self::assertStringNotContainsString( self::NETWORK_SECRET_KEY, $output );
	}

	/**
	 * Assert that payload flags cannot bypass the existing network import guard.
	 */
	private function assert_import_access_denied(): void {
		self::assertFalse( Request::is_cli() );

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME );
		$site_settings    = get_option( PluginSettingsBase::OPTION_NAME );
		$payload          = [
			'meta'     => [
				'plugin'         => hcaptcha()->settings()->get_plugin_name(),
				'schema_version' => SettingsTransfer::SCHEMA_VERSION,
			],
			'settings' => [ 'theme' => 'dark' ],
			'keys'     => [
				'site_key'   => 'imported-site-key',
				'secret_key' => 'imported-secret-key',
			],
		];

		foreach ( [ null, [], [ 'on' ], 'on' ] as $network_wide ) {
			if ( null !== $network_wide ) {
				$payload['settings'][ SettingsBase::NETWORK_WIDE ] = $network_wide;
			}

			$result = ( new SettingsTransfer() )->apply_import_payload( $payload, true );

			self::assertWPError( $result );
			self::assertSame( 'hcaptcha_network_settings_forbidden', $result->get_error_code() );
			self::assertSame( $network_settings, get_site_option( PluginSettingsBase::OPTION_NAME ) );
			self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
			self::assertSame( $site_settings, get_option( PluginSettingsBase::OPTION_NAME ) );
		}
	}

	/**
	 * Assert that an Ajax export is denied without leaking credentials.
	 *
	 * @param string $nonce        Export nonce.
	 * @param bool   $include_keys Whether keys were requested.
	 */
	private function assert_export_access_denied( string $nonce, bool $include_keys ): void {
		[ $response, $raw_response ] = $this->export_settings_with_raw_response( $nonce, $include_keys );

		self::assertFalse( $response['success'] ?? true );
		self::assertSame( 'You are not allowed to perform this action.', $response['data'] ?? null );
		self::assertArrayNotHasKey( 'settings', $response );
		self::assertArrayNotHasKey( 'keys', $response );
		self::assertStringNotContainsString( self::NETWORK_SITE_KEY, $raw_response );
		self::assertStringNotContainsString( self::NETWORK_SECRET_KEY, $raw_response );
	}

	/**
	 * Export settings over the registered Ajax action.
	 *
	 * @param string $nonce        Export nonce.
	 * @param bool   $include_keys Whether keys should be included.
	 *
	 * @return array
	 */
	private function export_settings( string $nonce, bool $include_keys ): array {
		[ $response ] = $this->export_settings_with_raw_response( $nonce, $include_keys );

		return $response;
	}

	/**
	 * Export settings and return decoded and raw Ajax responses.
	 *
	 * @param string $nonce        Export nonce.
	 * @param bool   $include_keys Whether keys should be included.
	 *
	 * @return array{0: array, 1: string}
	 */
	private function export_settings_with_raw_response( string $nonce, bool $include_keys ): array {
		$_POST = [
			'nonce'        => $nonce,
			'include_keys' => $include_keys ? 'on' : 'off',
		];

		$this->_last_response = '';
		$did_die              = false;

		try {
			$this->_handleAjax( Tools::EXPORT_ACTION );
		} catch ( WPAjaxDieContinueException $exception ) {
			$did_die = true;
		}

		self::assertTrue( $did_die, 'wp_send_json() must terminate the Ajax request.' );

		$response = json_decode( $this->_last_response, true );

		self::assertSame( JSON_ERROR_NONE, json_last_error() );
		self::assertIsArray( $response );

		return [ $response, $this->_last_response ];
	}

	/**
	 * Render the configured secret field.
	 *
	 * @return string
	 */
	private function render_secret_field(): string {
		$property = ( new ReflectionClass( $this->general ) )->getProperty( 'form_fields' );

		$property->setAccessible( true );
		$form_fields = $property->getValue( $this->general );
		$property->setAccessible( false );

		$arguments             = $form_fields['secret_key'];
		$arguments['field_id'] = 'secret_key';

		ob_start();
		$this->general->field_callback( $arguments );

		return (string) ob_get_clean();
	}
}
