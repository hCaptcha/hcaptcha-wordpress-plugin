<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'lostpassword_form', 'hcap_wp_lp_form' );

function hcap_wp_lp_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme 	= get_option("hcaptcha_theme");
    $hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_lost_password', 'hcaptcha_lost_password_nonce', true, false );
    
    echo $output;
}

function hcap_verify_lp_captcha($true) {
	if (isset( $_POST['hcaptcha_lost_password_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_lost_password_nonce'], 'hcaptcha_lost_password' ) && isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

		$hcaptcha_secret_key = get_option('hcaptcha_secret_key');
		$response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
		$response = json_decode($response["body"], true);
		if (false == $response["success"]) {
            return new WP_Error("Captcha Invalid", __("<strong>ERROR</strong>: Invalid Captcha"));
		} else {
            return $true;
        }
	} else {
        return new WP_Error("Captcha Invalid", __("<strong>ERROR</strong>: Invalid Captcha"));
	}   
}
add_filter("allow_password_reset", "hcap_verify_lp_captcha");