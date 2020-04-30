<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sbscrbr_add_field', 'hcap_subscriber_form', 10, 0 );

function hcap_subscriber_form() {

    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_subscriber_form', 'hcaptcha_subscriber_form_nonce', true, false );
    
    return $output;
}

add_filter( 'sbscrbr_check', 'hcap_subscriber_verify' );

/* check google captcha in subscriber */
if ( ! function_exists( 'hcap_subscriber_verify' ) ) {
    function hcap_subscriber_verify( $check_result = true ) {
	    $errorMessage = hcaptcha_get_verify_message( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );
	    if ( $errorMessage === null ) {
		    return $check_result;
	    }
	    return $errorMessage;
    }
}