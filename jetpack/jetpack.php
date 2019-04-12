<?php
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
			return $matches[1] . "[hcaptcha]" . $matches[3];
		}
		return $matches[0];
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

        if (isset($_POST['h-captcha-response'])) {
            global $hcap_status;
    
            $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);
    
            $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
            $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
            $response = json_decode($response["body"], true);
            if (true == $response["success"]) {
                return $is_spam;
            } else {
                $is_spam = new WP_Error();
                $is_spam->add( 'invalid_hcaptcha', "The captcha is invalid." );
                add_filter( 'hcap_hcaptcha_content', 'hcap_hcaptcha_error_message', 10, 1 );
                return $is_spam;
            } 
        } else {
            $is_spam = new WP_Error();
            $is_spam->add( 'invalid_hcaptcha', "The captcha is invalid." );
            add_filter( 'hcap_hcaptcha_content', 'hcap_hcaptcha_error_message', 10, 1 );
            return $is_spam;
        }
	}
} /* end function hcap_hcaptcha_jetpack_verify */
