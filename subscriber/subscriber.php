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

        if (isset( $_POST['hcaptcha_subscriber_form_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_subscriber_form_nonce'], 'hcaptcha_subscriber_form' ) && isset($_POST['h-captcha-response'])) {
            $get_hcaptcha_response = htmlspecialchars( sanitize_text_field( $_POST['h-captcha-response'] ) );
            
            $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
            $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
            $response = json_decode($response["body"], true);
            if (true == $response["success"]) {
                return true;
            } else {
                $check_result = "Please complete captcha";
                return $check_result;
            } 
        } else {
            $check_result = "Please complete captcha";
            return $check_result;
        }
    }
}