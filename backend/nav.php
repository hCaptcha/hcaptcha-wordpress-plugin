<?php
/**
 * Admin settings page file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add admin options page.
 */
function hcaptcha_options_nav() {
	add_options_page(
		'hCaptcha Settings',
		'hCaptcha',
		'manage_options',
		'hcaptcha-options',
		'hcaptcha_options'
	);
}

add_action( 'admin_menu', 'hcaptcha_options_nav' );

/**
 * Settings page.
 */
function hcaptcha_options() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
	}

	include dirname( __FILE__ ) . '/settings.php';
}
