<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_add_mc4wp_error_message( $messages ) {

    $messages['invalid_hcaptcha'] = array(
        'type' => 'error',
        'text' => __('The Captcha is invalid.', 'hcaptcha-wp')
    );

    return $messages;
}

add_filter( 'mc4wp_form_messages', 'hcap_add_mc4wp_error_message' );

if ( ! function_exists( 'hcap_mailchimp_wp_form' ) ) {
    function hcap_mailchimp_wp_form( $content = '', $form = '', $element = '' ) {
        $content = str_replace( '<input type="submit"', hcap_display_hcaptcha() . wp_nonce_field( 'hcaptcha_mailchimp', 'hcaptcha_mailchimp_nonce', true, false ) . '<input type="submit"', $content );
        return $content;
    }
}

add_action( 'mc4wp_form_content', 'hcap_mailchimp_wp_form', 20, 3 );

add_filter( 'mc4wp_valid_form_request', 'hcap_mc4wp_error', 10, 2 );

function hcap_mc4wp_error($errors = ''){

	$errorMessage = hcaptcha_verify_POST( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );
	if ( $errorMessage !== 'success' ) {
		return 'invalid_hcaptcha';
	}
	return 1;
}
