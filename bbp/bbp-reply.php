<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

function hcap_display_bbp_reply(){
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '';
    $output .= '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_bbp_reply', 'hcaptcha_bbp_reply_nonce', true, false );

    echo $output;
}

add_action( 'bbp_theme_after_reply_form_content', 'hcap_display_bbp_reply', 10, 0 );

function hcap_verify_bbp_reply_captcha() {
	$errorMessage = hcaptcha_get_verify_message( 'hcaptcha_bbp_reply_nonce', 'hcaptcha_bbp_reply' );
	if ( $errorMessage === null ) {
		return true;
	}
	bbp_add_error( 'hcap_error', $errorMessage );
}
add_action( 'bbp_new_reply_pre_extras',  'hcap_verify_bbp_reply_captcha' ); 