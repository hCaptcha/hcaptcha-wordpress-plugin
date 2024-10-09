<?php
/**
 * UninstallFileTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\PluginSettingsBase;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Test the `uninstall.php` file.
 *
 * @group uninstall-file
 */
class UninstallFileTest extends HCaptchaWPTestCase {

	/**
	 * Test uninstall.php file content.
	 *
	 * @noinspection HttpUrlsUsage
	 */
	public function test_uninstall_file_content(): void {
		$uninstall_file = HCAPTCHA_PATH . '/uninstall.php';

		define( 'WP_UNINSTALL_PLUGIN', true );

		update_option( PluginSettingsBase::OPTION_NAME, [ 'some settings' ] );
		update_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [ 'some network settings' ] );
		update_option( LoginBase::LOGIN_DATA, [ 'some login data' ] );
		update_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME, [ 'some migration data' ] );

		require $uninstall_file;

		self::assertFalse( get_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertFalse( get_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
		self::assertFalse( get_option( LoginBase::LOGIN_DATA ) );
		self::assertFalse( get_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME ) );
	}
}
