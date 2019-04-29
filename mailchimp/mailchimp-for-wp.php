<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_add_mc4wp_error_message( $messages ) {

    $messages['invalid_hcaptcha'] = array(
        'type' => 'error',
        'text' => 'Please complete the captcha.'
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

    if (isset( $_POST['hcaptcha_mailchimp_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_mailchimp_nonce'], 'hcaptcha_mailchimp' ) && isset($_POST['h-captcha-response'])) {
        global $hcap_status;

        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

        $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
        $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);


        $response = json_decode($response["body"], true);

        if (true == $response["success"]) {
            return 1;
        } else {
            return 'invalid_hcaptcha';
        } 
    } else {
        return 'invalid_captcha';
    }
}
