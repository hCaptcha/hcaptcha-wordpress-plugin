<?php

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

add_action('comment_form_after_fields', 'hcap_wp_comment_form');

add_filter('comment_form_field_comment', 'hcap_wp_login_comment_form', 10, 1);


function hcap_wp_comment_form()
{
    $hcaptcha_api_key = get_option('hcaptcha_api_key');
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="' . $hcaptcha_api_key . '" data-theme="' . $hcaptcha_theme . '" data-size="' . $hcaptcha_size . '"></div>';
    $output .= wp_nonce_field('hcaptcha_comment_form', 'hcaptcha_comment_form_nonce', true, false);
    echo $output;
}

function hcap_wp_login_comment_form($field)
{
    if (is_user_logged_in()) {
        $hcaptcha_api_key = get_option('hcaptcha_api_key');
        $hcaptcha_theme     = get_option("hcaptcha_theme");
        $hcaptcha_size      = get_option("hcaptcha_size");
        $output = $field;
        $output .= '<div class="h-captcha" data-sitekey="' . $hcaptcha_api_key . '" data-theme="' . $hcaptcha_theme . '" data-size="' . $hcaptcha_size . '"></div>';
        $output .= wp_nonce_field('hcaptcha_comment_form', 'hcaptcha_comment_form_nonce', true, false);
        return $output;
    } else {
        return $field;
    }
}

function hcap_verify_comment_captcha($commentdata)
{

    if (is_admin()) {
        return $commentdata;
    } else {
        if (isset($_POST['hcaptcha_comment_form_nonce']) && wp_verify_nonce($_POST['hcaptcha_comment_form_nonce'], 'hcaptcha_comment_form') && isset($_POST['h-captcha-response'])) {
            $get_hcaptcha_response = htmlspecialchars(sanitize_text_field($_POST['h-captcha-response']));

            $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
            $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
            $response = json_decode($response["body"], true);
            if (true == $response["success"]) {
                return $commentdata;
            } else {
	            $message = __('<strong>Error</strong>: The Captcha is invalid.', 'hcaptcha-wp');
                wp_die($message, $message, array('back_link' => true));
            }
        } else {
	        $message = __('<strong>Error</strong>: Please complete the captcha.', 'hcaptcha-wp');
            wp_die($message, $message, array('back_link' => true));
        }
    }
}
add_filter("preprocess_comment", "hcap_verify_comment_captcha");
