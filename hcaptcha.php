<?php
/**
 * Plugin hCaptcha
 *
 * @package              hcaptcha-wp
 * @author               hCaptcha
 * @license              GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name:          hCaptcha for Forms and More
 * Plugin URI:           https://hcaptcha.com/
 * Description:          hCaptcha is a new way to monetize your site traffic while keeping out bots and spam. It is a drop-in replacement for reCAPTCHA.
 * Version:              1.9.2
 * Requires at least:    4.4
 * Requires PHP:         5.6
 * Author:               hCaptcha
 * Author URI:           https://hcaptcha.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          hcaptcha-for-forms-and-more
 * Domain Path:          /languages/
 *
 * WC requires at least: 3.0
 * WC tested up to:      5.2
 */

// If this file is called directly, abort.
use HCaptcha\CF7\CF7;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Plugin version.
 */
define( 'HCAPTCHA_VERSION', '1.9.2' );

/**
 * Path to the plugin dir.
 */
define( 'HCAPTCHA_PATH', __DIR__ );

/**
 * Plugin dir url.
 */
define( 'HCAPTCHA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Main plugin file.
 */
define( 'HCAPTCHA_FILE', __FILE__ );

require_once HCAPTCHA_PATH . '/vendor/autoload.php';

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
	$param_array = [];
	$compat      = get_option( 'hcaptcha_recaptchacompat' );
	$language    = get_option( 'hcaptcha_language' );

	if ( $compat ) {
		$param_array['recaptchacompat'] = 'off';
	}

	if ( $language ) {
		$param_array['hl'] = $language;
	}

	$url_params = add_query_arg( $param_array, '' );

	wp_enqueue_style( 'hcaptcha-style', HCAPTCHA_URL . '/css/style.css', [], HCAPTCHA_VERSION );
	wp_enqueue_script(
		'hcaptcha-script',
		'//hcaptcha.com/1/api.js' . $url_params,
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
		$hcaptcha_content = sprintf( '<p id="hcap_error" class="error hcap_error">%s</p>', __( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ) ) . $hcaptcha_content;

		return $hcaptcha_content;
	}
}

/**
 * Load plugin modules.
 *
 * @noinspection PhpIncludeInspection
 */
function hcap_load_modules() {
	$modules = array(
		'Ninja Forms'               => array(
			'hcaptcha_nf_status',
			'ninja-forms/ninja-forms.php',
			'nf/ninja-forms-hcaptcha.php',
		),
		'Contact Form 7'            => array(
			'hcaptcha_cf7_status',
			'contact-form-7/wp-contact-form-7.php',
			CF7::class,
		),
		'Login Form'                => array(
			'hcaptcha_lf_status',
			'',
			'default/login-form.php',
		),
		'Register Form'             => array(
			'hcaptcha_rf_status',
			'',
			'default/register-form.php',
		),
		'Comment Form'              => array(
			'hcaptcha_cmf_status',
			'',
			'default/comment-form.php',
		),
		'Lost Password Form'        => array(
			'hcaptcha_lpf_status',
			'',
			array( 'common/lost-password-form.php', 'default/lost-password.php' ),
		),
		'WooCommerce Login'         => array(
			'hcaptcha_wc_login_status',
			'woocommerce/woocommerce.php',
			'wc/wc-login.php',
		),
		'WooCommerce Register'      => array(
			'hcaptcha_wc_reg_status',
			'woocommerce/woocommerce.php',
			'wc/wc-register.php',
		),
		'WooCommerce Lost Password' => array(
			'hcaptcha_wc_lost_pass_status',
			'woocommerce/woocommerce.php',
			array( 'common/lost-password-form.php', 'wc/wc-lost-password.php' ),
		),
		'WooCommerce Checkout'      => array(
			'hcaptcha_wc_checkout_status',
			'woocommerce/woocommerce.php',
			'wc/wc-checkout.php',
		),
		'BuddyPress Register'       => array(
			'hcaptcha_bp_reg_status',
			'buddypress/bp-loader.php',
			'bp/bp-register.php',
		),
		'BuddyPress Create Group'   => array(
			'hcaptcha_bp_create_group_status',
			'buddypress/bp-loader.php',
			'bp/bp-create-group.php',
		),
		'BB Press New Topic'        => array(
			'hcaptcha_bbp_new_topic_status',
			'bbpress/bbpress.php',
			'bbp/bbp-new-topic.php',
		),
		'BB Press Reply'            => array(
			'hcaptcha_bbp_reply_status',
			'bbpress/bbpress.php',
			'bbp/bbp-reply.php',
		),
		'WPForms Lite'              => array(
			'hcaptcha_wpforms_status',
			'wpforms-lite/wpforms.php',
			'wpforms/wpforms.php',
		),
		'WPForms Pro'               => array(
			'hcaptcha_wpforms_pro_status',
			'wpforms/wpforms.php',
			'wpforms/wpforms.php',
		),
		'wpForo New Topic'          => array(
			'hcaptcha_wpforo_new_topic_status',
			'wpforo/wpforo.php',
			'wpforo/wpforo-new-topic.php',
		),
		'wpForo Reply'              => array(
			'hcaptcha_wpforo_reply_status',
			'wpforo/wpforo.php',
			'wpforo/wpforo-reply.php',
		),
		'MailChimp'                 => array(
			'hcaptcha_mc4wp_status',
			'mailchimp-for-wp/mailchimp-for-wp.php',
			'mailchimp/mailchimp-for-wp.php',
		),
		'Jetpack'                   => array(
			'hcaptcha_jetpack_cf_status',
			'jetpack/jetpack.php',
			'jetpack/jetpack.php',
		),
		'Subscriber'                => array(
			'hcaptcha_subscribers_status',
			'subscriber/subscriber.php',
			'subscriber/subscriber.php',
		),
		'WC Wishlist'               => array(
			'hcaptcha_wc_wl_create_list_status',
			'woocommerce-wishlists/woocommerce-wishlists.php',
			'wc_wl/wc-wl-create-list.php',
		),
	);

	if ( ! function_exists( 'is_plugin_active' ) ) {
		// @codeCoverageIgnoreStart
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// @codeCoverageIgnoreEnd
	}

	foreach ( $modules as $module ) {
		$status = get_option( $module[0] );
		if ( 'on' !== $status ) {
			continue;
		}

		if ( ( $module[1] && ! is_plugin_active( $module[1] ) ) ) {
			continue;
		}

		foreach ( (array) $module[2] as $component ) {
			if ( false === strpos( $component, '.php' ) ) {
				new $component();
				continue;
			}

			require_once HCAPTCHA_PATH . '/' . $component;
		}
	}
}

add_action( 'plugins_loaded', 'hcap_load_modules', - PHP_INT_MAX );

/**
 * Load plugin text domain.
 */
function hcaptcha_wp_load_textdomain() {
	load_plugin_textdomain(
		'hcaptcha-for-forms-and-more',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
}

add_action( 'plugins_loaded', 'hcaptcha_wp_load_textdomain' );
