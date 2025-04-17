<?php
/**
 * Uninstall file.
 *
 * @package hcaptcha-wp
 */

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\PluginSettingsBase;
use KAGG\Settings\Abstracts\SettingsBase;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// @codeCoverageIgnoreStart
	exit();
	// @codeCoverageIgnoreEnd
}

/**
 * Path to the plugin dir.
 */
if ( ! defined( 'HCAPTCHA_PATH' ) ) {
	define( 'HCAPTCHA_PATH', __DIR__ );
}

/**
 * Delete several options from 'sitemeta' table.
 *
 * @param array $options Options.
 *
 * @return void
 */
function hcap_delete_site_meta_options( array $options ): void {
	foreach ( $options as $option ) {
		delete_site_option( $option );
	}
}

/**
 * Delete several options from site 'options' table.
 *
 * @param array $options Options.
 *
 * @return void
 */
function hcap_delete_options( array $options ): void {
	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Delete 'hcaptcha_events' table.
 *
 * @return void
 */
function hcap_delete_events_table(): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}hcaptcha_events`" );
}

/**
 * Cleanup site data.
 *
 * @param array $options Options.
 *
 * @return void
 */
function hcap_cleanup_site_data( array $options ): void {
	hcap_delete_options( $options );
	hcap_delete_events_table();
}

/**
 * Delete options from all sites.
 *
 * @return void
 * @noinspection DisconnectedForeachInstructionInspection
 */
function hcap_cleanup_data(): void {
	$options              = get_option( PluginSettingsBase::OPTION_NAME, [] );
	$cleanup_on_uninstall = $options['cleanup_on_uninstall'] ?? '';

	if ( [ 'on' ] !== $cleanup_on_uninstall ) {
		return;
	}

	// Perform plugin cleanup tasks.
	$settings    = [ PluginSettingsBase::OPTION_NAME, PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE ];
	$other       = [ LoginBase::LOGIN_DATA, Migrations::MIGRATED_VERSIONS_OPTION_NAME ];
	$all_options = array_merge( $settings, $other );

	if ( ! is_multisite() ) {
		// If not multisite, just delete the option from the single site.
		hcap_cleanup_site_data( $all_options );

		return;
	}

	// Delete site meta options.
	hcap_delete_site_meta_options( $settings );

	// Get all sites.
	$sites = get_sites();

	foreach ( $sites as $site ) {
		// Switch to each site and delete options.
		switch_to_blog( (int) $site->blog_id );
		hcap_cleanup_site_data( $all_options );
		restore_current_blog();
	}
}

require_once HCAPTCHA_PATH . '/vendor/autoload.php';

hcap_cleanup_data();
