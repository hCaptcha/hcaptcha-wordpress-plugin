<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'comment_form', 'hcap_wp_comment_form' );

function hcap_wp_comment_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme     = get_option("hcaptcha_theme");
    $hcaptcha_size      = get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    $output .= wp_nonce_field( 'hcaptcha_comment_form', 'hcaptcha_comment_form_nonce', true, false );
    echo $output;
}

function hcap_verify_comment_captcha($commentdata) {

    if (isset( $_POST['hcaptcha_comment_form_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_comment_form_nonce'], 'hcaptcha_comment_form' ) && isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars( sanitize_text_field( $_POST['h-captcha-response'] ) );

        $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
        $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
        $response = json_decode($response["body"], true);
        if (true == $response["success"]) {
            return $commentdata;
        } else {
            wp_die( __( '<strong>ERROR</strong>: Invalid Captcha', 'hcaptcha_wp' ), __( '<strong>ERROR</strong>: Invalid Captcha', 'hcaptcha_wp' ), array('back_link' => true)  );
        } 
    } else {
        wp_die( __( '<strong>ERROR</strong>: Invalid Captcha', 'hcaptcha_wp' ), __( '<strong>ERROR</strong>: Invalid Captcha', 'hcaptcha_wp' ), array('back_link' => true)  );
    }   
}
add_filter("preprocess_comment", "hcap_verify_comment_captcha");