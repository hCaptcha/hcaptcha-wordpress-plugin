<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'woocommerce_lostpassword_form', 'hcaptcha_display_captcha', 10, 0 );

if ( ! has_action( 'lostpassword_post', 'hcaptcha_verify_captcha' ) ) {
    add_action( 'lostpassword_post', 'hcaptcha_verify_captcha' );
}