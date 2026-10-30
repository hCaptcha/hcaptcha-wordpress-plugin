<?php
/**
 * SettingsStorageTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Settings;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Main;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\AntiSpamPage;
use HCaptcha\Settings\General;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\SettingsStorage;
use KAGG\Settings\Abstracts\SettingsBase;
use lucatume\WPBrowser\TestCase\WPTestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use WP_Hook;

/**
 * Test maintenance persistence through real WordPress multisite option hooks.
 */
class SettingsStorageTest extends WPTestCase {

	/**
	 * Exercise restoration of a guard registered at a non-default priority.
	 */
	private const GUARD_PRIORITY = 23;

	/**
	 * Real General settings tab.
	 *
	 * @var General|null
	 */
	private ?General $general = null;

	/**
	 * Option hooks present before the test.
	 *
	 * @var array<string, WP_Hook|null>
	 */
	private array $option_hooks = [];

	/**
	 * Cached General settings present before the test.
	 *
	 * @var array
	 */
	private array $general_settings = [];

	/**
	 * Users temporarily granted network authority.
	 *
	 * @var int[]
	 */
	private array $super_admins = [];

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		global $wp_filter;

		parent::setUp();

		self::assertTrue( is_multisite() );
		self::assertFalse( Request::is_cli(), 'Anonymous guard tests require a non-CLI process.' );

		$settings = hcaptcha()->settings();

		self::assertNotNull( $settings );

		$this->general = $settings->get_tab( General::class );

		self::assertInstanceOf( General::class, $this->general );

		$property = new ReflectionProperty( SettingsBase::class, 'settings' );
		$property->setAccessible( true );
		$this->general_settings = $property->getValue( $this->general );

		foreach ( $this->option_hook_names() as $hook ) {
			$this->option_hooks[ $hook ] = isset( $wp_filter[ $hook ] ) ? clone $wp_filter[ $hook ] : null;
		}

		wp_set_current_user( 0 );

		self::assertSame( 0, get_current_user_id() );
		self::assertFalse( current_user_can( 'manage_network_options' ) );

		$this->seed_settings( $this->local_settings(), $this->network_settings() );
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		global $wp_filter;

		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		foreach ( $this->super_admins as $user_id ) {
			revoke_super_admin( $user_id );
		}

		delete_option( PluginSettingsBase::OPTION_NAME );
		delete_site_option( PluginSettingsBase::OPTION_NAME );
		delete_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE );
		wp_set_current_user( 0 );

		if ( $this->general ) {
			$property = new ReflectionProperty( SettingsBase::class, 'settings' );
			$property->setAccessible( true );
			$property->setValue( $this->general, $this->general_settings );
		}

		foreach ( $this->option_hooks as $hook => $callbacks ) {
			if ( null === $callbacks ) {
				unset( $wp_filter[ $hook ] );
			} else {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the test hook fixture.
				$wp_filter[ $hook ] = $callbacks;
			}
		}

		parent::tearDown();
	}

	/**
	 * Anonymous license checks change only the license in the active network scope.
	 *
	 * @param string $stored_license   Previously stored network license.
	 * @param string $expected_license Expected network license.
	 *
	 * @dataProvider dp_test_anonymous_license_maintenance
	 */
	public function test_anonymous_license_maintenance( string $stored_license, string $expected_license ): void {
		$local              = $this->local_settings();
		$network            = $this->network_settings();
		$network['license'] = $stored_license;

		$this->seed_settings( $local, $network );

		$request_url   = '';
		$request_count = 0;
		$response      = static function ( $preempt, array $arguments, string $url ) use ( &$request_url, &$request_count ): array {
			$request_url = $url;
			++$request_count;

			return [
				'body'     => wp_json_encode(
					[
						'pass'     => true,
						'features' => [ 'custom_theme' => true ],
					]
				),
				'response' => [ 'code' => 200 ],
			];
		};

		add_filter( 'pre_http_request', $response, PHP_INT_MAX, 3 );

		try {
			HCaptcha::save_license_level();
		} finally {
			remove_filter( 'pre_http_request', $response, PHP_INT_MAX );
		}

		$network['license'] = $expected_license;
		$query              = [];

		wp_parse_str( (string) wp_parse_url( $request_url, PHP_URL_QUERY ), $query );

		self::assertSame( 1, $request_count );
		self::assertSame( $network['site_key'], $query['sitekey'] ?? null );
		self::assertSame( General::MODE_TEST_ENTERPRISE_SAFE_END_USER, hcaptcha()->settings()->get_mode() );
		$this->assert_storage( $local, $network );
	}

	/**
	 * An existing network Enterprise license survives a local Free license.
	 *
	 * @return array
	 */
	public function dp_test_anonymous_license_maintenance(): array {
		return [
			'network license promoted'    => [ 'free', 'pro' ],
			'network Enterprise retained' => [ 'enterprise', 'enterprise' ],
		];
	}

	/**
	 * Explicit local maintenance cannot change network ownership or unrelated fields.
	 */
	public function test_explicit_local_maintenance_preserves_network_scope(): void {
		$local   = $this->local_settings();
		$network = $this->network_settings();

		self::assertTrue(
			SettingsStorage::update(
				[
					'license'                  => 'pro',
					SettingsBase::NETWORK_WIDE => [ 'on' ],
				],
				[ 'obsolete_marker', SettingsBase::NETWORK_WIDE ],
				false
			)
		);

		$local['license'] = 'pro';
		unset( $local['obsolete_marker'] );

		$this->assert_storage( $local, $network );
	}

	/**
	 * Posted form keys cannot acknowledge network review notices during maintenance or a rejected save.
	 */
	public function test_form_only_notice_acknowledgement_requires_authority(): void {
		$local   = $this->local_settings();
		$network = $this->network_settings();
		$network[ Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION ] = 'on';
		$network['trusted_address_headers']                           = [];
		$this->seed_settings( $local, $network );
		$anti_spam = hcaptcha()->settings()->get_tab( AntiSpamPage::class );
		$hook      = 'pre_update_site_option_' . PluginSettingsBase::OPTION_NAME;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve deliberately unauthorized form data for this regression.
		$post = $_POST;

		self::assertInstanceOf( AntiSpamPage::class, $anti_spam );
		add_filter( $hook, [ $anti_spam, 'clear_trusted_address_headers_review_notice' ], 30, 2 );
		$_POST[ PluginSettingsBase::OPTION_NAME ] = [ 'trusted_address_headers' => [] ];

		try {
			self::assertTrue( SettingsStorage::update( [ 'license' => 'pro' ] ) );
			$network['license'] = 'pro';
			$this->assert_storage( $local, $network );
			self::assertFalse( update_site_option( PluginSettingsBase::OPTION_NAME, [ 'license' => 'free' ] ) );
			$this->assert_storage( $local, $network );

			$method = new ReflectionMethod( AntiSpamPage::class, 'save_cloudflare_detection_status' );
			$method->setAccessible( true );
			$status = [
				'status'     => 'detected',
				'confidence' => 'high',
				'checked_at' => 123,
			];
			$method->invoke( $anti_spam, $status );
			$network[ AntiSpamPage::CLOUDFLARE_DETECTION_STATUS_OPTION ] = $status;
			$this->assert_storage( $local, $network );

			$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
			grant_super_admin( $user_id );
			$this->super_admins[] = $user_id;
			wp_set_current_user( $user_id );
			self::assertTrue( update_site_option( PluginSettingsBase::OPTION_NAME, $network ) );
			$saved = get_site_option( PluginSettingsBase::OPTION_NAME );
			self::assertArrayNotHasKey( Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION, $saved );
			self::assertSame( $status, $saved[ AntiSpamPage::CLOUDFLARE_DETECTION_STATUS_OPTION ] );
			self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
		} finally {
			$_POST = $post;
			remove_filter( $hook, [ $anti_spam, 'clear_trusted_address_headers_review_notice' ], 30 );
		}
	}

	/**
	 * Events markers follow each blog rather than the active network settings.
	 */
	public function test_events_markers_belong_to_distinct_blogs(): void {
		$local   = $this->local_settings();
		$network = $this->network_settings();
		$blog_id = $this->factory()->blog->create();
		$mark    = new ReflectionMethod( Events::class, 'mark_table_created' );
		$unmark  = new ReflectionMethod( Events::class, 'unmark_table_created' );

		$mark->setAccessible( true );
		$unmark->setAccessible( true );

		self::assertFalse( Events::table_exists() );

		$mark->invoke( null );
		$local[ Events::TABLE_CREATED_OPTION_NAME ] = 'on';

		self::assertTrue( Events::table_exists() );
		$this->assert_storage( $local, $network );

		$secondary               = $this->local_settings();
		$secondary['site_key']   = 'secondary-site-key';
		$secondary['secret_key'] = 'secondary-secret-key';
		$secondary['local_only'] = 'secondary-only';

		switch_to_blog( $blog_id );

		try {
			delete_option( PluginSettingsBase::OPTION_NAME );
			self::assertTrue( add_option( PluginSettingsBase::OPTION_NAME, $secondary ) );
			self::assertFalse( Events::table_exists() );

			$mark->invoke( null );
			$secondary[ Events::TABLE_CREATED_OPTION_NAME ] = 'on';

			self::assertTrue( Events::table_exists() );
			$this->assert_storage( $secondary, $network );
		} finally {
			restore_current_blog();
		}

		$unmark->invoke( null );
		unset( $local[ Events::TABLE_CREATED_OPTION_NAME ] );

		self::assertFalse( Events::table_exists() );
		$this->assert_storage( $local, $network );

		switch_to_blog( $blog_id );

		try {
			self::assertTrue( Events::table_exists() );
			$this->assert_storage( $secondary, $network );

			$unmark->invoke( null );
			unset( $secondary[ Events::TABLE_CREATED_OPTION_NAME ] );

			self::assertFalse( Events::table_exists() );
			$this->assert_storage( $secondary, $network );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * A thrown native option hook cannot leave maintenance authorization behind.
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_maintenance_authorization_expires_after_a_thrown_hook(): void {
		global $wp_filter, $wp_current_filter;

		$local          = $this->local_settings();
		$network        = $this->network_settings();
		$value          = array_merge( $network, [ 'license' => 'pro' ] );
		$hook           = 'pre_update_site_option_' . PluginSettingsBase::OPTION_NAME;
		$guard          = clone $wp_filter[ $hook ];
		$current_filter = $wp_current_filter;
		$throw          = static function (): void {
			throw new RuntimeException( 'Interrupted maintenance write.' );
		};

		add_filter( $hook, $throw, self::GUARD_PRIORITY + 1 );

		try {
			SettingsStorage::update( [ 'license' => 'pro' ] );
			self::fail( 'The native option hook must interrupt this write.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Interrupted maintenance write.', $exception->getMessage() );
		} finally {
			// WordPress does not unwind its hook execution state when a callback throws.
			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_filter[ $hook ] = $guard;
			$wp_current_filter  = $current_filter;
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		self::assertFalse( update_site_option( PluginSettingsBase::OPTION_NAME, $value ) );
		self::assertFalse( update_option( PluginSettingsBase::OPTION_NAME, $value ) );
		$this->assert_storage( $local, $network );

		self::assertTrue( SettingsStorage::update( [ 'license' => 'pro' ] ) );
		$network['license'] = 'pro';

		$this->assert_storage( $local, $network );
	}

	/**
	 * A maintenance hook cannot lend its authorization to a different native write.
	 */
	public function test_reentrant_native_writes_require_the_exact_maintenance_value(): void {
		$local     = $this->local_settings();
		$network   = $this->network_settings();
		$attack    = array_merge(
			$network,
			[
				'secret_key'               => 'reentrant-secret',
				SettingsBase::NETWORK_WIDE => [],
			]
		);
		$entered   = false;
		$results   = [];
		$hook      = 'pre_update_site_option_' . PluginSettingsBase::OPTION_NAME;
		$reentrant = static function ( $value ) use ( &$entered, &$results, $attack ) {
			if ( ! $entered ) {
				$entered   = true;
				$results[] = update_site_option( PluginSettingsBase::OPTION_NAME, $attack );
				$results[] = update_option( PluginSettingsBase::OPTION_NAME, $attack );
			}

			return $value;
		};

		add_filter( $hook, $reentrant, self::GUARD_PRIORITY - 1 );

		try {
			self::assertTrue( SettingsStorage::update( [ 'license' => 'pro' ] ) );
		} finally {
			remove_filter( $hook, $reentrant, self::GUARD_PRIORITY - 1 );
		}

		$network['license'] = 'pro';

		self::assertSame( [ false, false ], $results );
		$this->assert_storage( $local, $network );
	}

	/**
	 * A normal authorized network save restores the guard for later anonymous writes.
	 */
	public function test_authorized_network_save_restores_the_native_guard(): void {
		$local   = $this->local_settings();
		$network = $this->network_settings();
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$hook    = 'pre_update_site_option_' . PluginSettingsBase::OPTION_NAME;

		grant_super_admin( $user_id );
		$this->super_admins[] = $user_id;
		wp_set_current_user( $user_id );

		self::assertTrue( current_user_can( 'manage_network_options' ) );
		self::assertSame( self::GUARD_PRIORITY, has_filter( $hook, [ $this->general, 'pre_update_option_filter' ] ) );
		self::assertFalse( update_option( PluginSettingsBase::OPTION_NAME, array_merge( $network, [ 'theme' => 'dark' ] ) ) );

		$saved = get_site_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( 'dark', $saved['theme'] ?? null );
		self::assertSame( $network['secret_key'], $saved['secret_key'] ?? null );
		self::assertSame( $network['custom_themes'], $saved['custom_themes'] ?? null );
		self::assertSame( self::GUARD_PRIORITY, has_filter( $hook, [ $this->general, 'pre_update_option_filter' ] ) );
		$this->assert_storage( $local, $saved );

		wp_set_current_user( 0 );
		self::assertFalse( current_user_can( 'manage_network_options' ) );

		$attack = array_merge(
			$saved,
			[
				'secret_key'               => 'guest-secret',
				SettingsBase::NETWORK_WIDE => [],
			]
		);

		self::assertFalse( update_site_option( PluginSettingsBase::OPTION_NAME, $attack ) );
		self::assertFalse( update_option( PluginSettingsBase::OPTION_NAME, $attack ) );
		$this->assert_storage( $local, $saved );
	}

	/**
	 * Storage can maintain both scopes before Main initializes its Settings instance.
	 */
	public function test_storage_does_not_depend_on_main_settings(): void {
		$local    = $this->local_settings();
		$network  = $this->network_settings();
		$main     = hcaptcha();
		$property = new ReflectionProperty( Main::class, 'settings' );

		$property->setAccessible( true );
		$settings = $property->getValue( $main );
		$property->setValue( $main, null );

		try {
			self::assertNull( $main->settings() );
			self::assertTrue( SettingsStorage::is_network_wide() );
			self::assertSame( $network, SettingsStorage::get() );
			self::assertSame( $local, SettingsStorage::get( false ) );
			self::assertTrue( SettingsStorage::update( [ 'license' => 'pro' ] ) );
			self::assertTrue( SettingsStorage::update( [ Events::TABLE_CREATED_OPTION_NAME => 'on' ], [], false ) );

			$network['license']                         = 'pro';
			$local[ Events::TABLE_CREATED_OPTION_NAME ] = 'on';

			$this->assert_storage( $local, $network );
		} finally {
			$property->setValue( $main, $settings );
		}
	}

	/**
	 * The local fixture contains unrelated credentials, checkboxes, and maintenance data.
	 *
	 * @return array
	 */
	private function local_settings(): array {
		return [
			'site_key'                 => 'local-site-key',
			'secret_key'               => 'local-secret-key',
			'license'                  => 'free',
			'mode'                     => General::MODE_LIVE,
			'theme'                    => 'light',
			'custom_themes'            => [ 'on' ],
			'wp_status'                => [ 'login', 'register' ],
			'local_only'               => 'keep-local',
			'obsolete_marker'          => 'remove-local',
			SettingsBase::NETWORK_WIDE => [],
		];
	}

	/**
	 * The network fixture has different credentials and a misleading network Events marker.
	 *
	 * @return array
	 */
	private function network_settings(): array {
		return [
			'site_key'                        => 'network-site-key',
			'secret_key'                      => 'network-secret-key',
			'license'                         => 'free',
			'mode'                            => General::MODE_TEST_ENTERPRISE_SAFE_END_USER,
			'theme'                           => 'light',
			'custom_themes'                   => [ 'on' ],
			'wp_status'                       => [ 'login' ],
			'network_only'                    => 'keep-network',
			Events::TABLE_CREATED_OPTION_NAME => 'on',
			SettingsBase::NETWORK_WIDE        => [ 'on' ],
		];
	}

	/**
	 * Seed exact options, then attach the real General guard to native WordPress hooks.
	 *
	 * @param array $local   Local settings.
	 * @param array $network Network settings.
	 */
	private function seed_settings( array $local, array $network ): void {
		foreach ( $this->option_hook_names() as $hook ) {
			remove_all_filters( $hook );
		}

		delete_option( PluginSettingsBase::OPTION_NAME );
		delete_site_option( PluginSettingsBase::OPTION_NAME );
		delete_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE );

		self::assertTrue( add_option( PluginSettingsBase::OPTION_NAME, $local ) );
		self::assertTrue( add_site_option( PluginSettingsBase::OPTION_NAME, $network ) );
		self::assertTrue( add_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [ 'on' ] ) );

		$init = new ReflectionMethod( SettingsBase::class, 'init_settings' );
		$init->setAccessible( true );
		$init->invoke( $this->general );

		foreach ( $this->option_hook_names() as $hook ) {
			add_filter( $hook, [ $this->general, 'pre_update_option_filter' ], self::GUARD_PRIORITY, 2 );
		}

		$this->assert_storage( $local, $network );
	}

	/**
	 * Assert exact stored values and unchanged standalone network ownership.
	 *
	 * @param array $local   Expected local settings.
	 * @param array $network Expected network settings.
	 */
	private function assert_storage( array $local, array $network ): void {
		self::assertSame( $local, get_option( PluginSettingsBase::OPTION_NAME, [] ) );
		self::assertSame( $network, get_site_option( PluginSettingsBase::OPTION_NAME, [] ) );
		self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [] ) );
	}

	/**
	 * Native option hooks exercised by these tests.
	 *
	 * @return string[]
	 */
	private function option_hook_names(): array {
		return [
			'pre_update_option_' . PluginSettingsBase::OPTION_NAME,
			'pre_update_site_option_' . PluginSettingsBase::OPTION_NAME,
		];
	}
}
