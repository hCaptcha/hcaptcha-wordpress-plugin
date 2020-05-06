<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcaptcha_lost_password_display()
{
    $hcaptcha_api_key = get_option( 'hcaptcha_api_key' );
    $hcaptcha_theme = get_option( 'hcaptcha_theme' );
    $hcaptcha_size = get_option( 'hcaptcha_size' );
    $output = '<div class="h-captcha" data-sitekey="' . $hcaptcha_api_key . '" data-theme="' . $hcaptcha_theme . '" data-size="' . $hcaptcha_size . '"></div>';
    $output .= wp_nonce_field( 'hcaptcha_lost_password', 'hcaptcha_lost_password_nonce', true, false );

    echo $output;
}

function hcaptcha_lost_password_verify($error)
{
	$errorMessage = hcaptcha_get_verify_message_html( 'hcaptcha_lost_password_nonce', 'hcaptcha_lost_password' );
	if ( $errorMessage !== null ) {
		$error->add( 'invalid_captcha', $errorMessage );
	}
}