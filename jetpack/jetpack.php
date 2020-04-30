<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

/* Add hCAPTCHA to the Jetpack Contact Form */
if ( ! function_exists( 'hcap_hcaptcha_jetpack_form' ) ) {
    function hcap_hcaptcha_jetpack_form( $content ) {
        return preg_replace_callback( "~(\[contact-form([\s\S]*)?\][\s\S]*)(\[\/contact-form\])~U", "hcaptcha_jetpack_cf_callback", $content );
    }
} /* end function hcap_hcaptcha_jetpack_form */

/* Add reCAPTCHA shortcode to the provided shortcode for Jetpack contact form */
if ( ! function_exists( 'hcaptcha_jetpack_cf_callback' ) ) {
    function hcaptcha_jetpack_cf_callback( $matches ) {

        if ( ! preg_match( "~\[hcaptcha\]~", $matches[0] ) ) {
            return $matches[1] . "[hcaptcha]" . wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false ) . $matches[3];
        }
        return $matches[0] . wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false );
    }
} /* end function hcaptcha_jetpack_cf_callback */

add_filter( 'the_content', 'hcap_hcaptcha_jetpack_form' );
add_filter( 'widget_text', 'hcap_hcaptcha_jetpack_form', 0 );
add_filter( 'widget_text', 'shortcode_unautop' );
add_filter( 'widget_text', 'do_shortcode' );
add_filter( 'jetpack_contact_form_is_spam', 'hcap_hcaptcha_jetpack_verify', 11, 2 );

/* check reCAPTCHA answer from the Jetpack Contact Form */
if ( ! function_exists( 'hcap_hcaptcha_jetpack_verify' ) ) {
    function hcap_hcaptcha_jetpack_verify( $is_spam = false ) {
	    $errorMessage = hcaptcha_get_verify_message( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );
	    if ( $errorMessage === null ) {
		    return $is_spam;
	    }
	    $is_spam = new WP_Error();
	    $is_spam->add( 'invalid_hcaptcha', $errorMessage );
	    add_filter( 'hcap_hcaptcha_content', 'hcap_hcaptcha_error_message', 10, 1 );
	    return $is_spam;
    }
} /* end function hcap_hcaptcha_jetpack_verify */
