<?php
/**
 * Admin settings page file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Admin menu slug.
 */
define( 'HCAPTCHA_MENU_SLUG', 'hcaptcha-options' );

/**
 * Add admin options page.
 */
function hcaptcha_options_nav() {
	add_options_page(
		__( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' ),
		'hCaptcha',
		'manage_options',
		HCAPTCHA_MENU_SLUG,
		'hcaptcha_options'
	);
}

add_action( 'admin_menu', 'hcaptcha_options_nav' );

/**
 * Settings page.
 */
function hcaptcha_options() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__(
				'You do not have sufficient permissions to access this page.',
				'hcaptcha-for-forms-and-more'
			),
			'hCaptcha'
		);
	}

	require_once HCAPTCHA_PATH . '/backend/settings.php';
}

/**
 * Admin styles.
 */
function hcap_admin_enqueue_scripts() {
	wp_enqueue_style(
		'hcaptcha-admin',
		HCAPTCHA_URL . '/css/admin.css',
		[],
		HCAPTCHA_VERSION
	);
}

add_action( 'admin_enqueue_scripts', 'hcap_admin_enqueue_scripts' );

/**
 * Add link to plugin setting page on plugins page.
 *
 * @param array  $actions     An array of plugin action links. By default this can include 'activate',
 *                            'deactivate', and 'delete'. With Multisite active this can also include
 *                            'network_active' and 'network_only' items.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @param array  $plugin_data An array of plugin data. See `get_plugin_data()`.
 * @param string $context     The plugin context. By default this can include 'all', 'active', 'inactive',
 *                            'recently_activated', 'upgrade', 'mustuse', 'dropins', and 'search'.
 *
 * @return array|mixed Plugin links
 */
function hcap_add_settings_link( $actions, $plugin_file, $plugin_data, $context ) {
	$ctl_actions = [
		'settings' =>
			'<a href="' . admin_url( 'options-general.php?page=' . HCAPTCHA_MENU_SLUG ) .
			'" aria-label="' . esc_attr__( 'View hCaptcha settings', 'hcaptcha-for-forms-and-more' ) . '">' .
			esc_html__( 'Settings', 'hcaptcha-for-forms-and-more' ) . '</a>',
	];

	return array_merge( $ctl_actions, $actions );
}

add_filter( 'plugin_action_links_' . plugin_basename( HCAPTCHA_FILE ), 'hcap_add_settings_link', 10, 4 );
