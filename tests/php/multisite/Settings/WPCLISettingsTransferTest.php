<?php
/**
 * WPCLISettingsTransferTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Settings;

use HCaptcha\CLI\Commands;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\SettingsTransfer;
use KAGG\Settings\Abstracts\SettingsBase;
use lucatume\WPBrowser\TestCase\WPTestCase;
use WP_CLI;

require_once dirname( __DIR__, 2 ) . '/integration/CLI/WPCLIExitException.php';
require_once dirname( __DIR__, 2 ) . '/integration/CLI/WPCLI.php';
require_once dirname( __DIR__, 2 ) . '/integration/CLI/functions.php';

/**
 * Test trusted WP-CLI network settings transfers without a WordPress user.
 */
class WPCLISettingsTransferTest extends WPTestCase {
	/**
	 * Temporary import file.
	 *
	 * @var string
	 */
	private string $file = '';

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();

		self::assertTrue( is_multisite() );
		self::assertSame( realpath( dirname( __DIR__, 4 ) . '/hcaptcha.php' ), realpath( HCAPTCHA_FILE ) );

		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		self::assertTrue( constant( 'WP_CLI' ) );

		WP_CLI::reset();
		wp_set_current_user( 0 );

		update_site_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'                 => 'cli-network-site-key',
				'secret_key'               => 'cli-network-secret-key',
				'theme'                    => 'light',
				SettingsBase::NETWORK_WIDE => [ 'on' ],
			]
		);
		update_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [ 'on' ] );
		update_option( PluginSettingsBase::OPTION_NAME, [ 'theme' => 'site-local-theme' ] );
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		if ( $this->file && file_exists( $this->file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $this->file );
		}

		delete_site_option( PluginSettingsBase::OPTION_NAME );
		delete_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE );
		delete_option( PluginSettingsBase::OPTION_NAME );
		WP_CLI::reset();

		parent::tearDown();
	}

	/**
	 * Test that trusted WP-CLI export and import preserve network-wide mode without --user.
	 */
	public function test_wp_cli_export_and_import_preserve_network_wide_mode_without_user(): void {
		self::assertSame( 0, get_current_user_id() );

		ob_start();
		( new Commands() )->export( [], [ 'include-keys' => true ] );
		$export = json_decode( (string) ob_get_clean(), true );

		self::assertSame( JSON_ERROR_NONE, json_last_error() );
		self::assertSame( 'cli-network-site-key', $export['keys']['site_key'] ?? null );
		self::assertSame( 'cli-network-secret-key', $export['keys']['secret_key'] ?? null );

		$export['settings']['theme'] = 'dark';
		$this->file                  = tempnam( sys_get_temp_dir(), 'hcap-cli-network-' );

		self::assertIsString( $this->file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $this->file, wp_json_encode( $export ) );

		( new Commands() )->import( [ $this->file ], [ 'allow-keys' => true ] );

		$network_settings = get_site_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( [ 'on' ], get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
		self::assertSame( [ 'on' ], $network_settings[ SettingsBase::NETWORK_WIDE ] ?? null );
		self::assertSame( 'dark', $network_settings['theme'] ?? null );
		self::assertSame( 'cli-network-secret-key', $network_settings['secret_key'] ?? null );
		self::assertSame( [ 'theme' => 'site-local-theme' ], get_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertSame( [ 'hCaptcha settings were successfully imported.' ], WP_CLI::$success_messages );
	}
}
