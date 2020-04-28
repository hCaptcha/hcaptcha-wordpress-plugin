<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'lostpassword_form', 'hcaptcha_display_captcha' );
add_action( 'lostpassword_post', 'hcaptcha_verify_captcha' );