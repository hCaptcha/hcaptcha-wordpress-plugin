<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_display_bbp_new_topic(){
	$hcaptcha_api_key = get_option('hcaptcha_api_key' );
	$hcaptcha_theme 	= get_option("hcaptcha_theme");
	$hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '';
    $output .= '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
	$output .= wp_nonce_field( 'hcaptcha_bbp_new_topic', 'hcaptcha_bbp_new_topic_nonce', true, false );

    echo $output;
}

add_action( 'bbp_theme_after_topic_form_content', 'hcap_display_bbp_new_topic', 10, 0 );

function hcap_verify_bbp_new_topic_captcha() {

	if (isset( $_POST['hcaptcha_bbp_new_topic_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_bbp_new_topic_nonce'], 'hcaptcha_bbp_new_topic' ) && isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

		$hcaptcha_secret_key = get_option('hcaptcha_secret_key');
		$response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
		$response = json_decode($response["body"], true);
		if (true == $response["success"]) {
            return true;
		} else {
            bbp_add_error( 'hcap_error', 'Invalid Captcha' );
        } 
	} else {
		bbp_add_error( 'hcap_error', 'Invalid Captcha' );
	}   
}
add_action( 'bbp_new_topic_pre_extras',  'hcap_verify_bbp_new_topic_captcha' ); 