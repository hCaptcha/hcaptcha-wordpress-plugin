<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_display_wc_checkout(){
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_wc_checkout', 'hcaptcha_wc_checkout_nonce', true, false );
    
    echo $output;
}

add_action( 'woocommerce_after_checkout_billing_form', 'hcap_display_wc_checkout', 10, 0 );


function hcap_verify_wc_checkout_captcha($validation_error) {
	$errorMessage = hcaptcha_get_verify_message( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout' );
	if ( $errorMessage !== null ) {
		wc_add_notice( $errorMessage, 'error' );
	}
}
add_action( 'woocommerce_checkout_process',  'hcap_verify_wc_checkout_captcha' );