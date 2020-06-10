<?php
/**
 * Plugin Name: hCaptcha for Forms and More
 * Plugin URI: https://hcaptcha.com/
 * Description: hCaptcha is a new way to monetize your site traffic while keeping out bots and spam. It is a drop-in replacement for reCAPTCHA.
 * Author: hCaptcha
 * Author URI: https://hCaptcha.com/
 * Version: 1.5.0
 * Stable tag: 1.5.0
 *
 * Text Domain: hcaptcha-wp
 * Domain Path: /languages/
 *
 * @package hcaptcha-wp
 * @author  hCaptcha
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 */
define( 'HCAPTCHA_VERSION', '1.5.0' );

/**
 * Path to the plugin dir.
 */
define( 'HCAPTCHA_PATH', dirname( __FILE__ ) );

/**
 * Plugin dir url.
 */
define( 'HCAPTCHA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

require 'common/request.php';
require 'common/functions.php';

// Add admin page.
if ( is_admin() ) {
	require 'backend/nav.php';
}

/**
 * Add the hcaptcha script to footer.
 */
function hcap_captcha_script() {
	$dir = plugin_dir_url( __FILE__ );
	wp_enqueue_style( 'hcaptcha-style', $dir . 'style.css', [], HCAPTCHA_VERSION );
	wp_enqueue_script(
		'hcaptcha-script',
		'//hcaptcha.com/1/api.js?hl=' . get_option( 'hcaptcha_language' ),
		[],
		HCAPTCHA_VERSION,
		true
	);
}

add_action( 'wp_enqueue_scripts', 'hcap_captcha_script' );
add_action( 'login_enqueue_scripts', 'hcap_captcha_script' );

if ( ! function_exists( 'hcap_hcaptcha_error_message' ) ) {
	/**
	 * Print error message.
	 *
	 * @param string $hcaptcha_content Content of hCaptcha.
	 *
	 * @return string
	 */
	function hcap_hcaptcha_error_message( $hcaptcha_content = '' ) {
		$hcaptcha_content = sprintf( '<p id="hcap_error" class="error hcap_error">%s</p>', __( 'The Captcha is invalid.', 'hcaptcha-wp' ) ) . $hcaptcha_content;

		return $hcaptcha_content;
	}
}

/**
 * Load plugin modules.
 */
function hcap_load_modules() {
	$modules = [
		'Ninja Forms'               => [
			'hcaptcha_nf_status',
			'ninja-forms/ninja-forms.php',
			'nf/ninja-forms-hcaptcha.php',
		],
		'Contact Form 7'            => [
			'hcaptcha_cf7_status',
			'contact-form-7/wp-contact-form-7.php',
			'cf7/hcaptcha-cf7.php',
		],
		'Login Form'                => [
			'hcaptcha_lf_status',
			'',
			'default/login-form.php',
		],
		'Register Form'             => [
			'hcaptcha_rf_status',
			'',
			'default/register-form.php',
		],
		'Comment Form'              => [
			'hcaptcha_cmf_status',
			'',
			'default/comment-form.php',
		],
		'Lost Password Form'        => [
			'hcaptcha_lpf_status',
			'',
			[ 'common/lost-password-form.php', 'default/lost-password.php' ],
		],
		'WooCommerce Login'         => [
			'hcaptcha_wc_login_status',
			'woocommerce/woocommerce.php',
			'wc/wc-login.php',
		],
		'WooCommerce Register'      => [
			'hcaptcha_wc_reg_status',
			'woocommerce/woocommerce.php',
			'wc/wc-register.php',
		],
		'WooCommerce Lost Password' => [
			'hcaptcha_wc_lost_pass_status',
			'woocommerce/woocommerce.php',
			[ 'common/lost-password-form.php', 'wc/wc-lost-password.php' ],
		],
		'WooCommerce Checkout'      => [
			'hcaptcha_wc_checkout_status',
			'woocommerce/woocommerce.php',
			'wc/wc-checkout.php',
		],
		'BuddyPress Register'       => [
			'hcaptcha_bp_reg_status',
			'buddypress/bp-loader.php',
			'bp/bp-register.php',
		],
		'BuddyPress Create Group'   => [
			'hcaptcha_bp_create_group_status',
			'buddypress/bp-loader.php',
			'bp/bp-create-group.php',
		],
		'BB Press New Topic'        => [
			'hcaptcha_bbp_new_topic_status',
			'bbpress/bbpress.php',
			'bp/bp-create-group.php',
		],
		'BB Press Reply'            => [
			'hcaptcha_bbp_reply_status',
			'bbpress/bbpress.php',
			'bbp/bbp-reply.php',
		],
		'wpForo New Topic'          => [
			'hcaptcha_wpforo_new_topic_status',
			'wpforo/wpforo.php',
			'wpforo/wpforo-new-topic.php',
		],
		'wpForo Reply'              => [
			'hcaptcha_wpforo_reply_status',
			'wpforo/wpforo.php',
			'wpforo/wpforo-reply.php',
		],
		'MailChimp'                 => [
			'hcaptcha_mc4wp_status',
			'mailchimp-for-wp/mailchimp-for-wp.php',
			'mailchimp/mailchimp-for-wp.php',
		],
		'Jetpack'                   => [
			'hcaptcha_jetpack_cf_status',
			'jetpack/jetpack.php',
			'jetpack/jetpack.php',
		],
		'Subscriber'                => [
			'hcaptcha_subscribers_status',
			'subscriber/subscriber.php',
			'subscriber/subscriber.php',
		],
		'WC Wishlist'               => [
			'hcaptcha_wc_wl_create_list_status',
			'woocommerce-wishlists/woocommerce-wishlists.php',
			'wc_wl/wc-wl-create-list.php',
		],
	];

	foreach ( $modules as $module ) {
		$status = get_option( $module[0] );
		if ( 'on' !== $status ) {
			continue;
		}

		if ( ( $module[1] && ! is_plugin_active( $module[1] ) ) ) {
			continue;
		}

		foreach ( (array) $module[2] as $require ) {
			require_once $require;
		}
	}
}

add_action( 'init', 'hcap_load_modules' );

/**
 * Load plugin text domain.
 */
function hcaptcha_wp_load_textdomain() {
	load_plugin_textdomain( 'hcaptcha-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'hcaptcha_wp_load_textdomain' );
