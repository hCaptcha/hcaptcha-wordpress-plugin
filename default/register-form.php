<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'register_form', 'hcap_wp_register_form' );

function hcap_wp_register_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_registration', 'hcaptcha_registration_nonce', true, false );
    
    echo $output;
}

function hcap_verify_register_captcha($errors, $sanitized_user_login, $user_email) {
	$errorMessage = hcaptcha_get_verify_message_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );
	if ( $errorMessage === null ) {
		return $errors;
	}
	$errors->add( 'invalid_captcha', $errorMessage );
	return $errors;
}
add_filter("registration_errors", "hcap_verify_register_captcha", 10, 3);