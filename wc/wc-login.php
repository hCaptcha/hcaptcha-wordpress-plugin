<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_display_wc_login(){
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce', true, false );
    
    echo $output;
}

add_action( 'woocommerce_login_form', 'hcap_display_wc_login', 10, 0 );

function hcap_verify_wc_login_captcha($validation_error) {
	$errorMessage = hcaptcha_get_verify_message( 'hcaptcha_login_nonce', 'hcaptcha_login' );
	if ( $errorMessage === null ) {
		return $validation_error;
	}
	$validation_error->add( 'hcaptcha_error' ,  $errorMessage );
	return $validation_error;
}
apply_filters( 'woocommerce_process_login_errors',  'hcap_verify_wc_login_captcha' ); 