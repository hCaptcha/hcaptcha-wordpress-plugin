<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'login_form', 'hcap_wp_login_form' );

function hcap_wp_login_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce', true, false );
    
    echo $output;
}

function hcap_verify_login_captcha($user, $password) {
	$errorMessage = hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
	if ( $errorMessage === null ) {
		return $user;
	}
	return new WP_Error("Invalid Captcha", $errorMessage);
}
add_filter("wp_authenticate_user", "hcap_verify_login_captcha", 10, 2);