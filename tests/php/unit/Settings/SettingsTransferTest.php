<?php
/**
 * SettingsTransferTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Helpers\Request;
use HCaptcha\Main;
use HCaptcha\Settings\General;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\Settings;
use HCaptcha\Settings\SettingsTransfer;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_Mock;

/**
 * Test SettingsTransfer.
 *
 * @group settings
 * @group settings-transfer
 */
class SettingsTransferTest extends HCaptchaTestCase {
	/**
	 * Test an import payload from another plugin is rejected.
	 */
	public function test_apply_import_payload_rejects_plugin_mismatch(): void {
		WP_Mock::userFunction( 'is_wp_error' )->andReturn( true );

		$error = Mockery::mock( 'overload:WP_Error' );
		$error->shouldReceive( 'get_error_code' )->andReturn( 'plugin_mismatch' );

		$payload                           = $this->get_payload( [] );
		$payload['meta']['schema_version'] = 'invalid';
		$result                            = ( new SettingsTransfer() )->apply_import_payload( $payload );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'plugin_mismatch', $result->get_error_code() );
	}

	/**
	 * Test a dry run and credentials extracted from the import payload.
	 */
	public function test_dry_run_with_keys(): void {
		$payload         = $this->get_payload( [ 'theme' => 'dark' ] );
		$payload['keys'] = [
			'site_key'   => 'site',
			'secret_key' => 'secret',
		];
		$settings        = Mockery::mock( Settings::class );
		$settings->shouldReceive( 'get_plugin_name' )->once()->andReturn( 'hCaptcha for WP' );
		$main = Mockery::mock( Main::class );
		$main->shouldReceive( 'settings' )->once()->andReturn( $settings );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );
		WP_Mock::userFunction( 'is_wp_error' )->with( null )->once()->andReturn( false );

		$transfer = new SettingsTransfer();
		$method   = $this->set_method_accessibility( $transfer, 'get_import_settings' );
		self::assertSame(
			[
				'theme'      => 'dark',
				'site_key'   => 'site',
				'secret_key' => 'secret',
			],
			$method->invoke( $transfer, $payload, true )
		);
		self::assertNull( $transfer->apply_import_payload( $payload, true, true ) );
	}

	/**
	 * Test failure to save sanitized settings.
	 */
	public function test_apply_import_payload_reports_save_failure(): void {
		$payload      = $this->get_payload( [ 'theme' => 'dark' ] );
		$old_settings = [ 'theme' => 'light' ];
		$general      = Mockery::mock( General::class );
		$general->shouldReceive( 'is_network_wide' )->once()->andReturn( false );
		$general->shouldReceive( 'pre_update_option_filter' )->with( [ 'theme' => 'dark' ], $old_settings )
			->once()->andReturn( [ 'theme' => 'dark' ] );
		$tab = Mockery::mock( PluginSettingsBase::class );
		$tab->shouldReceive( 'sanitize_option_callback' )->with( [ 'theme' => 'dark' ] )
			->once()->andReturn( [ 'theme' => 'dark' ] );
		$settings = Mockery::mock( Settings::class );
		$settings->shouldReceive( 'get_plugin_name' )->once()->andReturn( 'hCaptcha for WP' );
		$settings->shouldReceive( 'get_tab' )->with( General::class )->once()->andReturn( $general );
		$settings->shouldReceive( 'get_raw_settings' )->once()->andReturn( $old_settings );
		$settings->shouldReceive( 'get_tabs' )->once()->andReturn( [ $tab ] );
		$main = Mockery::mock( Main::class );
		$main->shouldReceive( 'settings' )->twice()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->twice()->andReturn( $main );
		WP_Mock::userFunction( 'is_wp_error' )->with( null )->once()->andReturn( false );
		WP_Mock::userFunction( 'update_option' )
			->with( PluginSettingsBase::OPTION_NAME, [ 'theme' => 'dark' ] )->once()->andReturn( false );
		$error = Mockery::mock( 'overload:WP_Error' );
		$error->shouldReceive( 'get_error_code' )->andReturn( 'save_failed' );

		$result = ( new SettingsTransfer() )->apply_import_payload( $payload );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'save_failed', $result->get_error_code() );
	}

	/**
	 * Test export payload with and without credentials.
	 *
	 * @param bool $include_keys Whether credentials are included.
	 *
	 * @dataProvider dp_test_build_export_payload
	 */
	public function test_build_export_payload( bool $include_keys ): void {
		$settings = Mockery::mock( Settings::class );
		$main     = Mockery::mock( Main::class );
		FunctionMocker::replace( 'constant', '1.0.0' );

		$settings->shouldReceive( 'get_raw_settings' )->once()->andReturn(
			[
				'site_key'   => 'site',
				'secret_key' => 'secret',
				'theme'      => 'dark',
			]
		);
		$settings->shouldReceive( 'get_plugin_name' )->once()->andReturn( 'hCaptcha for WP' );
		$main->shouldReceive( 'settings' )->once()->andReturn( $settings );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );

		$payload = ( new SettingsTransfer() )->build_export_payload( $include_keys );

		self::assertSame( [ 'theme' => 'dark' ], $payload['settings'] );
		self::assertSame( 'hCaptcha for WP', $payload['meta']['plugin'] );
		self::assertSame( '1.0.0', $payload['meta']['plugin_version'] );
		self::assertSame( SettingsTransfer::SCHEMA_VERSION, $payload['meta']['schema_version'] );
		self::assertNotFalse( strtotime( $payload['meta']['exported_at'] ) );

		if ( $include_keys ) {
			self::assertSame(
				[
					'site_key'   => 'site',
					'secret_key' => 'secret',
				],
				$payload['keys']
			);
		} else {
			self::assertArrayNotHasKey( 'keys', $payload );
		}
	}

	/**
	 * Export variants.
	 *
	 * @return array
	 */
	public function dp_test_build_export_payload(): array {
		return [
			'without keys' => [ false ],
			'with keys'    => [ true ],
		];
	}

	/**
	 * Test that site admins cannot import over active network-wide settings.
	 *
	 * @param array $imported_settings Imported settings.
	 *
	 * @dataProvider dp_test_site_admin_cannot_import_network_settings
	 */
	public function test_site_admin_cannot_import_network_settings( array $imported_settings ): void {
		$payload  = $this->get_payload( $imported_settings );
		$general  = Mockery::mock( General::class );
		$settings = Mockery::mock( Settings::class );
		$main     = Mockery::mock( Main::class );
		$error    = Mockery::mock( 'overload:WP_Error' );

		$error->shouldReceive( 'get_error_code' )->andReturn( 'hcaptcha_network_settings_forbidden' );

		$general->shouldReceive( 'is_network_wide' )->withNoArgs()->once()->andReturn( true );
		$settings->shouldReceive( 'get_plugin_name' )->withNoArgs()->once()->andReturn( 'hCaptcha for WP' );
		$settings->shouldReceive( 'get_tab' )->with( General::class )->once()->andReturn( $general );
		$settings->shouldReceive( 'get_tabs' )->never();
		$settings->shouldReceive( 'get_raw_settings' )->never();
		$main->shouldReceive( 'settings' )->withNoArgs()->twice()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->withNoArgs()->twice()->andReturn( $main );
		WP_Mock::userFunction( 'is_wp_error' )->with( null )->once()->andReturn( false );
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_network_options' )->once()->andReturn( false );
		WP_Mock::userFunction( 'update_option' )->never();
		FunctionMocker::replace( '\\' . Request::class . '::is_cli', false );

		$result = ( new SettingsTransfer() )->apply_import_payload( $payload );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'hcaptcha_network_settings_forbidden', $result->get_error_code() );
	}

	/**
	 * Data provider for test_site_admin_cannot_import_network_settings().
	 *
	 * @return array
	 */
	public function dp_test_site_admin_cannot_import_network_settings(): array {
		return [
			'network marker omitted'   => [ [ 'theme' => 'dark' ] ],
			'network marker disabled'  => [
				[
					'_network_wide' => [],
					'theme'         => 'dark',
				],
			],
			'canonical network marker' => [
				[
					'_network_wide' => [ 'on' ],
					'theme'         => 'dark',
				],
			],
			'legacy scalar marker'     => [
				[
					'_network_wide' => 'on',
					'theme'         => 'dark',
				],
			],
		];
	}

	/**
	 * Test that legitimate settings imports remain available.
	 *
	 * @param bool $network_wide Network-wide settings are active.
	 * @param bool $is_cli       The import runs through WP-CLI.
	 * @param bool $network_admin The user can manage network settings.
	 *
	 * @dataProvider dp_test_legitimate_imports_remain_available
	 */
	public function test_legitimate_imports_remain_available(
		bool $network_wide,
		bool $is_cli,
		bool $network_admin
	): void {
		$imported_settings = [ 'theme' => 'dark' ];
		$old_settings      = [ 'theme' => 'light' ];
		$prepared_settings = [
			'_network_wide' => $network_wide ? [ 'on' ] : [],
			'theme'         => 'dark',
		];
		$payload           = $this->get_payload( $imported_settings );
		$general           = Mockery::mock( General::class );
		$settings          = Mockery::mock( Settings::class );
		$main              = Mockery::mock( Main::class );

		$general->shouldReceive( 'is_network_wide' )->withNoArgs()->once()->andReturn( $network_wide );
		$general->shouldReceive( 'pre_update_option_filter' )
			->with( $imported_settings, $old_settings )->once()->andReturn( $prepared_settings );
		$settings->shouldReceive( 'get_plugin_name' )->withNoArgs()->once()->andReturn( 'hCaptcha for WP' );
		$settings->shouldReceive( 'get_tab' )->with( General::class )->once()->andReturn( $general );
		$settings->shouldReceive( 'get_raw_settings' )->withNoArgs()->once()->andReturn( $old_settings );
		$settings->shouldReceive( 'get_tabs' )->withNoArgs()->once()->andReturn( [] );
		$settings->shouldReceive( 'init' )->withNoArgs()->once();
		$main->shouldReceive( 'settings' )->withNoArgs()->twice()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->withNoArgs()->twice()->andReturn( $main );
		WP_Mock::userFunction( 'is_wp_error' )->with( null )->once()->andReturn( false );
		WP_Mock::userFunction( 'update_option' )
			->with( PluginSettingsBase::OPTION_NAME, $prepared_settings )->once()->andReturn( true );

		if ( $network_wide && ! $is_cli ) {
			WP_Mock::userFunction( 'current_user_can' )
				->with( 'manage_network_options' )->once()->andReturn( $network_admin );
		}

		FunctionMocker::replace( '\\' . Request::class . '::is_cli', $is_cli );
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\HCaptcha::save_license_level' );

		$result = ( new SettingsTransfer() )->apply_import_payload( $payload );

		self::assertNull( $result );
	}

	/**
	 * Data provider for test_legitimate_imports_remain_available().
	 *
	 * @return array
	 */
	public function dp_test_legitimate_imports_remain_available(): array {
		return [
			'site admin, site settings'       => [ false, false, false ],
			'network admin, network settings' => [ true, false, true ],
			'WP-CLI, network settings'        => [ true, true, false ],
		];
	}

	/**
	 * Test that WP-CLI preserves active network-wide settings without a WordPress user.
	 */
	public function test_wp_cli_preserves_network_wide_settings_without_user(): void {
		$old_settings      = [
			'_network_wide' => [ 'on' ],
			'theme'         => 'light',
		];
		$imported_settings = [ 'theme' => 'dark' ];
		$new_settings      = [
			'_network_wide' => [ 'on' ],
			'theme'         => 'dark',
		];
		$payload           = $this->get_payload( $imported_settings );
		$general           = Mockery::mock( General::class )->makePartial();
		$general->shouldAllowMockingProtectedMethods();
		$settings = Mockery::mock( Settings::class );
		$main     = Mockery::mock( Main::class );

		$general->shouldReceive( 'get_network_wide' )->withNoArgs()->times( 3 )->andReturn( [ 'on' ] );
		$general->shouldReceive( 'form_fields' )->withNoArgs()->once()->andReturn( [] );
		$general->shouldReceive( 'option_name' )
			->withNoArgs()->times( 5 )->andReturn( PluginSettingsBase::OPTION_NAME );
		$settings->shouldReceive( 'get_plugin_name' )->withNoArgs()->once()->andReturn( 'hCaptcha for WP' );
		$settings->shouldReceive( 'get_tab' )->with( General::class )->once()->andReturn( $general );
		$settings->shouldReceive( 'get_raw_settings' )->withNoArgs()->once()->andReturn( $old_settings );
		$settings->shouldReceive( 'get_tabs' )->withNoArgs()->once()->andReturn( [] );
		$main->shouldReceive( 'settings' )->withNoArgs()->twice()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->withNoArgs()->twice()->andReturn( $main );
		WP_Mock::userFunction( 'is_wp_error' )->with( null )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_multisite' )->withNoArgs()->once()->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_network_options' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_site_option' )
			->with( PluginSettingsBase::OPTION_NAME, [] )->twice()->andReturn( $old_settings );
		WP_Mock::userFunction( 'update_site_option' )
			->with( PluginSettingsBase::OPTION_NAME . '_network_wide', [ 'on' ] )->once();
		WP_Mock::userFunction( 'remove_filter' )
			->with(
				'pre_update_site_option_' . PluginSettingsBase::OPTION_NAME,
				[ $general, 'pre_update_option_filter' ]
			)->once();
		WP_Mock::userFunction( 'update_site_option' )
			->with( PluginSettingsBase::OPTION_NAME, $new_settings )->once();
		WP_Mock::userFunction( 'update_option' )->never();
		FunctionMocker::replace( '\\' . Request::class . '::is_cli', true );

		$result = ( new SettingsTransfer() )->apply_import_payload( $payload );

		self::assertSame( 'Current and imported hCaptcha settings are the same.', $result );
	}

	/**
	 * Get a valid import payload.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	private function get_payload( array $settings ): array {
		return [
			'meta'     => [
				'plugin'         => 'hCaptcha for WP',
				'schema_version' => SettingsTransfer::SCHEMA_VERSION,
			],
			'settings' => $settings,
		];
	}
}
