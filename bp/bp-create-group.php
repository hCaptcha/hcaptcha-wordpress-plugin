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

		$errorMessage = hcaptcha_get_verify_message( 'hcaptcha_bp_create_group_nonce', 'hcaptcha_bp_create_group' );
		if ( $errorMessage === null ) {
			return false;
		}
		bp_core_add_message( $errorMessage, 'error' );
		bp_core_redirect( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/create/step/group-details/' );
    }
}