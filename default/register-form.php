<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'register_form', 'hcap_wp_register_form' );

function hcap_wp_register_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme 	= get_option("hcaptcha_theme");
    $hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_registration', 'hcaptcha_registration_nonce', true, false );
    
    echo $output;
}

function hcap_verify_register_captcha($errors, $sanitized_user_login, $user_email) {
	if (isset( $_POST['hcaptcha_registration_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_registration_nonce'], 'hcaptcha_registration' ) && isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

		$hcaptcha_secret_key = get_option('hcaptcha_secret_key');
		$response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
		$response = json_decode($response["body"], true);
		if (false == $response["success"]) {
            $errors->add( 'invalid_captcha', __( '<strong>ERROR</strong>: Captcha invalid.', 'hcaptcha_wp' ) );
            return $errors;
		} else {
            return $errors;
        }
	} else {
        $errors->add( 'invalid_captcha', __( '<strong>ERROR</strong>: Captcha invalid.', 'hcaptcha_wp' ) );
        return $errors;
	}   
}
add_filter("registration_errors", "hcap_verify_register_captcha", 10, 3);