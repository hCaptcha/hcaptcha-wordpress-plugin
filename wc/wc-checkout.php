<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_display_wc_checkout(){
	$hcaptcha_api_key = get_option('hcaptcha_api_key' );
	$hcaptcha_theme 	= get_option("hcaptcha_theme");
    $hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_wc_checkout', 'hcaptcha_wc_checkout_nonce', true, false );
    
    echo $output;
}

add_action( 'woocommerce_after_checkout_billing_form', 'hcap_display_wc_checkout', 10, 0 );


function hcap_verify_wc_checkout_captcha($validation_error) {
	if (isset( $_POST['hcaptcha_wc_checkout_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_wc_checkout_nonce'], 'hcaptcha_wc_checkout' ) && isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

		$hcaptcha_secret_key = get_option('hcaptcha_secret_key');
		$response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
		$response = json_decode($response["body"], true);
		if (false == $response["success"]) {
            $error_message = 'Error: Invalid Captcha';
			wc_add_notice( $error_message, 'error' );
        }
	} else {
		$error_message = 'Error: Invalid Captcha';
		wc_add_notice( $error_message, 'error' );	
	}   
}
add_action( 'woocommerce_checkout_process',  'hcap_verify_wc_checkout_captcha' );