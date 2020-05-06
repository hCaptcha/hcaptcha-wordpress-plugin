<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_wpforo_reply_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_wpforo_reply', 'hcaptcha_wpforo_reply_nonce', true, false );
    
    echo $output;
}

add_action( 'wpforo_reply_form_buttons_hook', 'hcap_wpforo_reply_form', 99, 0 );
add_filter( 'wpforo_add_post_data_filter', 'hcap_verify_wpforo_reply_captcha', 10, 1 );
if ( ! function_exists( 'hcap_verify_wpforo_reply_captcha' ) ) {
    function hcap_verify_wpforo_reply_captcha( $data ) {
        global $wpforo;
	    $errorMessage = hcaptcha_get_verify_message( 'hcaptcha_wpforo_reply_nonce', 'hcaptcha_wpforo_reply' );
	    if ( $errorMessage === null ) {
		    return $data;
	    }
	    $wpforo->notice->add( $errorMessage, 'error');
	    return false;
    }
}