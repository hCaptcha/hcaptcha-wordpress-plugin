<?php
/*
 * Plugin Name: hCaptcha for Forms and More
 * Plugin URI: https://hcaptcha.com/
 * Description: hCaptcha is a new way to monetize your site traffic while keeping out bots and spam. It is a drop-in replacement for reCAPTCHA.
 * Version: 1.4.2
 * Author: hCaptcha
 * Author URI: https://hCaptcha.com/
 * Text Domain: hcaptcha-wp
 * Domain Path: /languages
*/

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

define('hcap_PLUGIN_NAME', 'hCaptcha WP');
define('hcap_PLUGIN_DIR', 'hcaptcha-wp');

// add admin page
include "backend/nav.php";
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
include 'common/request.php';


// get settings
$hcap_api_key_n                       = "hcaptcha_api_key";
$hcap_secret_key_n                    = "hcaptcha_secret_key";
$hcap_theme_n                         = "hcaptcha_theme";
$hcap_size_n                          = "hcaptcha_size";
$hcap_language_n                      = "hcaptcha_language";
$hcap_nf_status_n                     = "hcaptcha_nf_status";
$hcap_cf7_status_n                    = "hcaptcha_cf7_status";
$hcap_lf_status_n                     = "hcaptcha_lf_status";
$hcap_rf_status_n                     = "hcaptcha_rf_status";
$hcap_cmf_status_n                    = "hcaptcha_cmf_status";
$hcap_lpf_status_n                    = "hcaptcha_lpf_status";
$hcap_wc_login_status_n               = "hcaptcha_wc_login_status";
$hcap_wc_reg_status_n                 = "hcaptcha_wc_reg_status";
$hcap_wc_lost_pass_status_n           = "hcaptcha_wc_lost_pass_status";
$hcap_wc_checkout_status_n            = "hcaptcha_wc_checkout_status";
$hcap_bp_reg_status_n                 = "hcaptcha_bp_reg_status";
$hcap_bp_create_group_status_n        = "hcaptcha_bp_create_group_status";
$hcap_bbp_new_topic_status_n          = "hcaptcha_bbp_new_topic_status";
$hcap_bbp_reply_status_n              = "hcaptcha_bbp_reply_status";
$hcap_wpforo_new_topic_status_n       = "hcaptcha_wpforo_new_topic_status";
$hcap_wpforo_reply_status_n           = "hcaptcha_wpforo_reply_status";
$hcap_mc4wp_status_n                  = "hcaptcha_mc4wp_status";
$hcap_jetpack_cf_status_n             = "hcaptcha_jetpack_cf_status";
$hcap_subscribers_status_n            = "hcaptcha_subscribers_status";
$hcaptcha_wc_wl_create_list_status_n  = "hcaptcha_wc_wl_create_list_status";

$hcap_api_key                       = get_option($hcap_api_key_n);
$hcap_secret_key                    = get_option($hcap_secret_key_n);
$hcap_nf_status                     = get_option($hcap_nf_status_n);
$hcap_theme                         = get_option($hcap_theme_n);
$hcap_size                          = get_option($hcap_size_n);
$hcap_language                      = get_option($hcap_language_n);
$hcap_cf7_status                    = get_option($hcap_cf7_status_n);
$hcap_lf_status                     = get_option($hcap_lf_status_n);
$hcap_rf_status                     = get_option($hcap_rf_status_n);
$hcap_cmf_status                    = get_option($hcap_cmf_status_n);
$hcap_lpf_status                    = get_option($hcap_lpf_status_n);
$hcap_wc_login_status               = get_option($hcap_wc_login_status_n);
$hcap_wc_reg_status                 = get_option($hcap_wc_reg_status_n);
$hcap_wc_lost_pass_status           = get_option($hcap_wc_lost_pass_status_n);
$hcap_wc_checkout_status            = get_option($hcap_wc_checkout_status_n);
$hcap_bp_reg_status                 = get_option($hcap_bp_reg_status_n);
$hcap_bp_create_group_status        = get_option($hcap_bp_create_group_status_n);
$hcap_bbp_new_topic_status          = get_option($hcap_bbp_new_topic_status_n);
$hcap_bbp_reply_status              = get_option($hcap_bbp_reply_status_n);
$hcap_wpforo_new_topic_status       = get_option($hcap_wpforo_new_topic_status_n);
$hcap_wpforo_reply_status           = get_option($hcap_wpforo_reply_status_n);
$hcap_mc4wp_status                  = get_option($hcap_mc4wp_status_n);
$hcap_jetpack_cf_status             = get_option($hcap_jetpack_cf_status_n);
$hcap_subscribers_status            = get_option($hcap_subscribers_status_n);
$hcaptcha_wc_wl_create_list_status  = get_option($hcaptcha_wc_wl_create_list_status_n);

// add the hcaptcha script to footer
function hcap_captcha_script()
{
    global $hcap_language;
    $dir = plugin_dir_url(__FILE__);
    wp_enqueue_style('hcaptcha-style', $dir . 'style.css', [], false, 'all');
    wp_enqueue_script('hcaptcha-script', '//hcaptcha.com/1/api.js?hl=' . $hcap_language, array(), false, true);
}

add_action('wp_enqueue_scripts', 'hcap_captcha_script');
add_action('login_enqueue_scripts', 'hcap_captcha_script');

function hcap_display_hcaptcha($content = '')
{
    $hcaptcha_api_key   = get_option('hcaptcha_api_key');
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");

    $hcaptcha = '<div class="h-captcha" data-sitekey="' . $hcaptcha_api_key . '" data-theme="' . $hcaptcha_theme . '" data-size="' . $hcaptcha_size . '"></div>';

    $hcaptcha = apply_filters('hcap_hcaptcha_content', $hcaptcha);

    return $content . $hcaptcha;
}

add_shortcode('hcaptcha', 'hcap_display_hcaptcha');

if (!function_exists('hcap_hcaptcha_error_message')) {
    function hcap_hcaptcha_error_message($hcaptcha_content = '')
    {
        $hcaptcha_content = sprintf('<p id="hcap_error" class="error hcap_error">%s</p>', __('The Captcha is invalid.', 'hcaptcha-wp')) . $hcaptcha_content;
        return $hcaptcha_content;
    }
}

// contact form 7
if (!empty($hcap_cf7_status) && $hcap_cf7_status === "on") {
    // check if the other plugin is active
    if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
        require_once("cf7/hcaptcha-cf7.php");
    }
}

// ninja forms
if (!empty($hcap_nf_status) && $hcap_nf_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('ninja-forms/ninja-forms.php')) {
        require_once("nf/ninja-forms-hcaptcha.php");
    }
}

if (!empty($hcap_lf_status) && $hcap_lf_status == "on") {
    require_once("default/login-form.php");
}

if (!empty($hcap_rf_status) && $hcap_rf_status == "on") {
    require_once("default/register-form.php");
}

if (!empty($hcap_cmf_status) && $hcap_cmf_status == 'on') {
    require_once("default/comment-form.php");
}

if (!empty($hcap_lpf_status) && $hcap_lpf_status == "on") {
    require_once("common/lost-password-form.php");
    require_once("default/lost-password.php");
}

if (!empty($hcap_wc_login_status) && $hcap_wc_login_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once("wc/wc-login.php");
    }
}

if (!empty($hcap_wc_reg_status) && $hcap_wc_reg_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once("wc/wc-register.php");
    }
}

if (!empty($hcap_wc_lost_pass_status) && $hcap_wc_lost_pass_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once("common/lost-password-form.php");
        require_once("wc/wc-lost-password.php");
    }
}

if (!empty($hcap_wc_checkout_status) && $hcap_wc_checkout_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        require_once("wc/wc-checkout.php");
    }
}

if (!empty($hcap_bp_reg_status) && $hcap_bp_reg_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('buddypress/bp-loader.php')) {
        require_once("bp/bp-register.php");
    }
}

if (!empty($hcap_bp_create_group_status) && $hcap_bp_create_group_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('buddypress/bp-loader.php')) {
        require_once("bp/bp-create-group.php");
    }
}

if (!empty($hcap_bbp_new_topic_status) && $hcap_bbp_new_topic_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('bbpress/bbpress.php')) {
        require_once("bbp/bbp-new-topic.php");
    }
}

if (!empty($hcap_bbp_reply_status) && $hcap_bbp_reply_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('bbpress/bbpress.php')) {
        require_once("bbp/bbp-reply.php");
    }
}

if (!empty($hcap_wpforo_new_topic_status) && $hcap_wpforo_new_topic_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('wpforo/wpforo.php')) {
        require_once("wpforo/wpforo-new-topic.php");
    }
}

//echo $hcap_wpforo_reply_status . '---';die;

if (!empty($hcap_wpforo_reply_status) && $hcap_wpforo_reply_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('wpforo/wpforo.php')) {
        require_once("wpforo/wpforo-reply.php");
    }
}

if (!empty($hcap_mc4wp_status) && $hcap_mc4wp_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('mailchimp-for-wp/mailchimp-for-wp.php')) {
        require_once("mailchimp/mailchimp-for-wp.php");
    }
}

if (!empty($hcap_jetpack_cf_status) && $hcap_jetpack_cf_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('jetpack/jetpack.php')) {
        require_once("jetpack/jetpack.php");
    }
}

if (!empty($hcap_subscribers_status) && $hcap_subscribers_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('subscriber/subscriber.php')) {
        require_once("subscriber/subscriber.php");
    }
}

if (!empty($hcaptcha_wc_wl_create_list_status) && $hcaptcha_wc_wl_create_list_status == "on") {
    // check if the other plugin is active
    if (is_plugin_active('woocommerce-wishlists/woocommerce-wishlists.php')) {
        require_once("wc_wl/wc-wl-create-list.php");
    }
}


register_activation_hook(__FILE__, 'hcap_activation');
register_deactivation_hook(__FILE__, 'hcap_deactivation');
function hcap_activation()
{
    add_option('hcaptcha_api_key', '', '', 'yes');
    add_option('hcaptcha_nf_status', '', '', 'yes');
    add_option('hcaptcha_cf7_status', '', '', 'yes');
    add_option('hcaptcha_lf_status', '', '', 'yes');
    add_option('hcaptcha_rf_status', '', '', 'yes');
    add_option('hcaptcha_cmf_status', '', '', 'yes');
    add_option('hcaptcha_lpf_status', '', '', 'yes');
    add_option('hcaptcha_wc_login_status', '', '', 'yes');
    add_option('hcaptcha_wc_reg_status', '', '', 'yes');
    add_option('hcaptcha_wc_lost_pass_status', '', '', 'yes');
    add_option('hcaptcha_wc_checkout_status', '', '', 'yes');
    add_option('hcaptcha_bp_reg_status', '', '', 'yes');
    add_option('hcaptcha_bp_create_group_status', '', '', 'yes');
    add_option('hcaptcha_bbp_new_topic_status', '', '', 'yes');
    add_option('hcaptcha_bbp_reply_status', '', '', 'yes');
    add_option('hcaptcha_wpforo_new_topic_status', '', '', 'yes');
    add_option('hcaptcha_wpforo_reply_status', '', '', 'yes');
    add_option('hcaptcha_mc4wp_status', '', '', 'yes');
    add_option('hcaptcha_jetpack_cf_status', '', '', 'yes');
    add_option('hcaptcha_subscribers_status', '', '', 'yes');
    add_option('hcaptcha_wc_wl_create_list_status', '', '', 'yes');
    // do some other things on plugin activation
}
function hcap_deactivation()
{
    // Do something on deactivation
}

function hcaptcha_wp_load_textdomain() {
	load_plugin_textdomain( 'hcaptcha-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action('plugins_loaded', 'hcaptcha_wp_load_textdomain');
