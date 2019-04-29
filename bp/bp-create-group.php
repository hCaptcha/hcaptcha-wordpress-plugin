<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'bp_after_group_details_creation_step', 'hcap_bp_group_form' );
function hcap_bp_group_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme 	= get_option("hcaptcha_theme");
	$hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_bp_create_group', 'hcaptcha_bp_create_group_nonce', true, false );

    //return $content . $output;
    printf( '<div class="hcap_buddypress_group_form">%s</div>', $output );
}

add_action( 'groups_group_before_save', 'hcap_hcaptcha_bp_group_verify' );

if ( ! function_exists( 'hcap_hcaptcha_bp_group_verify' ) ) {
	function hcap_hcaptcha_bp_group_verify( $bp_group ) {

        if ( ! bp_is_group_creation_step( 'group-details' ) )
			return false;

        if (isset( $_POST['hcaptcha_bp_create_group_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_bp_create_group_nonce'], 'hcaptcha_bp_create_group' ) && isset($_POST['h-captcha-response'])) {
            $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);
    
            $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
            $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
            $response = json_decode($response["body"], true);
            if (true == $response["success"]) {
                return false;
            } else {
                bp_core_add_message( "Invalid Captcha", 'error' );
                bp_core_redirect( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/create/step/group-details/' );
            } 
        } else {
            bp_core_add_message( "Invalid Captcha", 'error' );
			bp_core_redirect( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/create/step/group-details/' );
        }
	}
}