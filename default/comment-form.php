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

	    $errorMessage = hcaptcha_get_verify_message_html( 'hcaptcha_comment_form_nonce', 'hcaptcha_comment_form' );
	    if ( $errorMessage === null ) {
		    return $commentdata;
	    }
	    wp_die($errorMessage, $errorMessage, array('back_link' => true));
    }
}
add_filter("preprocess_comment", "hcap_verify_comment_captcha");
