<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wpcf7_init', 'tnc_hcap_add_cf7_tag' );

function tnc_hcap_add_cf7_tag() {
    wpcf7_add_form_tag( array( 'hcaptcha' ), 'tnc_hcap_cf7_tag_cb', array( 'name-attr' => true ) );
}

function tnc_hcap_cf7_tag_cb(){
    $hcaptcha_api_key       = get_option('hcaptcha_api_key');
    $hcaptcha_secret_key    = get_option( 'hcaptcha_secret_key' );

    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'"></div>
    <script src="https://hcaptcha.com/1/api.js" async defer></script>
    </script>';

    return $output;
}

// add_action( 'wpcf7_init', 'custom_add_form_tag_clock' );

// function custom_add_form_tag_clock() {
//     wpcf7_add_form_tag( 'clock', 'custom_clock_form_tag_handler' ); // "clock" is the type of the form-tag
// }

// function custom_clock_form_tag_handler( $tag ) {
//     return date_i18n( get_option( 'time_format' ) );
// }