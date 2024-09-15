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
	exit();
}

/**
 * Delete several options from 'sitemeta' table.
 *
 * @param array $options Options.
 *
 * @return void
 */
function hcap_delete_site_meta_options( array $options ) {
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
function hcap_delete_options( array $options ) {
	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Delete 'hcaptcha_events' table.
 *
 * @return void
 */
function hcap_delete_events_table() {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}hcaptcha_events`" );
}

/**
 * Cleanup site data.
 *
 * @param array $options Options.
 *
 * @return void
 */
function hcap_cleanup_site_data( array $options ) {
	hcap_delete_options( $options );
	hcap_delete_events_table();
}

/**
 * Delete options from all sites.
 *
 * @return void
 * @noinspection DisconnectedForeachInstructionInspection
 */
function hcap_cleanup_data() {
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
		switch_to_blog( $site->blog_id );
		hcap_cleanup_site_data( $all_options );
		restore_current_blog();
	}
}

// Perform plugin cleanup tasks.
hcap_cleanup_data();

exit;
