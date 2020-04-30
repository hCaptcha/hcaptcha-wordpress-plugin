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
	if (isset( $_POST['hcaptcha_login_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_login_nonce'], 'hcaptcha_login' ) && isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars( sanitize_text_field( $_POST['h-captcha-response'] ) );

        $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
        $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
        $response = json_decode($response["body"], true);
        if (true == $response["success"]) {
            return $user;
        } else {
            return new WP_Error("Invalid Captcha", __( '<strong>Error</strong>: The Captcha is invalid.', 'hcaptcha-wp' ));
        } 
    } else {
        return new WP_Error("Invalid Captcha", __('<strong>Error</strong>: Please complete the captcha.', 'hcaptcha-wp'));
    }   
}
add_filter("wp_authenticate_user", "hcap_verify_login_captcha", 10, 2);