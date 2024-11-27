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
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the `uninstall.php` file.
 *
 * @group uninstall-file
 */
class UninstallFileTest extends HCaptchaWPTestCase {

	/**
	 * Test uninstall.php file.
	 *
	 * @param bool $is_multisite Whether the site is multisite.
	 *
	 * @dataProvider dp_test_uninstall_file
	 */
	public function test_uninstall_file( bool $is_multisite ): void {
		global $wpdb;

		$uninstall_file = HCAPTCHA_PATH . '/uninstall.php';

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		if ( $is_multisite ) {
			update_site_option( PluginSettingsBase::OPTION_NAME, [ 'some settings' ] );
			update_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [ 'some network settings' ] );

			FunctionMocker::replace(
				'defined',
				static function ( $constant_name ) {
					return 'SUBDOMAIN_INSTALL' === $constant_name;
				}
			);
		}

		update_option( PluginSettingsBase::OPTION_NAME, [ 'some settings' ] );
		update_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [ 'some network settings' ] );
		update_option( LoginBase::LOGIN_DATA, [ 'some login data' ] );
		update_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME, [ 'some migration data' ] );

		// Disable temporary tables creating.
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );

		$table_name      = 'hcaptcha_events';
		$full_table_name = $wpdb->prefix . $table_name;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name ) );

		self::assertFalse( (bool) $exists );

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $full_table_name (
		    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		    source      VARCHAR(256)    NOT NULL,
		    form_id     VARCHAR(20)     NOT NULL,
		    ip          VARCHAR(39)     NOT NULL,
		    user_agent  VARCHAR(256)    NOT NULL,
		    uuid        VARCHAR(36)     NOT NULL,
		    error_codes VARCHAR(256)    NOT NULL,
		    date_gmt    DATETIME        NOT NULL,
		    PRIMARY KEY (id),
		    KEY source (source),
		    KEY form_id (form_id),
		    KEY hcaptcha_id (source, form_id),
		    KEY ip (ip),
		    KEY uuid (uuid),
		    KEY date_gmt (date_gmt)
		) $charset_collate";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $sql );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name ) );

		self::assertTrue( (bool) $exists );

		// Run testing code.
		if ( ! function_exists( 'hcap_cleanup_data' ) ) {
			require_once $uninstall_file;
		} else {
			hcap_cleanup_data();
		}

		if ( $is_multisite ) {
			self::assertFalse( get_site_option( PluginSettingsBase::OPTION_NAME ) );
			self::assertFalse( get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
		}

		self::assertFalse( get_option( PluginSettingsBase::OPTION_NAME ) );
		self::assertFalse( get_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ) );
		self::assertFalse( get_option( LoginBase::LOGIN_DATA ) );
		self::assertFalse( get_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name ) );

		self::assertFalse( (bool) $exists );
	}

	/**
	 * Data provider for test_uninstall_file.
	 *
	 * @return array
	 */
	public function dp_test_uninstall_file(): array {
		return [
			[ false ],
			[ true ],
		];
	}
}
