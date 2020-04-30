<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcaptcha_display_captcha()
{
    $hcaptcha_api_key = get_option( 'hcaptcha_api_key' );
    $hcaptcha_theme = get_option( 'hcaptcha_theme' );
    $hcaptcha_size = get_option( 'hcaptcha_size' );
    $output = '<div class="h-captcha" data-sitekey="' . $hcaptcha_api_key . '" data-theme="' . $hcaptcha_theme . '" data-size="' . $hcaptcha_size . '"></div>';
    $output .= wp_nonce_field( 'hcaptcha_lost_password', 'hcaptcha_lost_password_nonce', true, false );

    echo $output;
}

function hcaptcha_verify_captcha($error)
{
    if ( ! isset( $_POST['hcaptcha_lost_password_nonce'] )
        || ! wp_verify_nonce( $_POST['hcaptcha_lost_password_nonce'], 'hcaptcha_lost_password' )
        || ! isset( $_POST['h-captcha-response'] ) ) {
        $error->add( 'invalid_captcha', __('<strong>Error</strong>: Please complete the captcha.', 'hcaptcha-wp') );
        return;
    }
    $get_hcaptcha_response = htmlspecialchars( sanitize_text_field( $_POST['h-captcha-response'] ) );

    $hcaptcha_secret_key = get_option( 'hcaptcha_secret_key' );
    $response = wp_remote_get( 'https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response );
    $response = json_decode( $response['body'], true );
    if ( false == $response['success'] ) {
        $error->add( 'invalid_captcha', __( '<strong>Error</strong>: The Captcha is invalid.', 'hcaptcha-wp' ) );
    }
}