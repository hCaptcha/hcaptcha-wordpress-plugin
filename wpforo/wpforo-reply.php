<?php
function hcap_wpforo_reply_form() {
    $hcaptcha_api_key = get_option('hcaptcha_api_key' );
    $hcaptcha_theme 	= get_option("hcaptcha_theme");
    $hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    
    echo $output;
}

add_action( 'wpforo_reply_form_buttons_hook', 'hcap_wpforo_reply_form', 99, 0 );
add_filter( 'wpforo_add_post_data_filter', 'hcap_verify_wpforo_reply_captcha', 10, 1 );
if ( ! function_exists( 'hcap_verify_wpforo_reply_captcha' ) ) {
    function hcap_verify_wpforo_reply_captcha( $data ) {
        global $wpforo;

        if (isset($_POST['h-captcha-response'])) {
            $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);
    
            $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
            $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
            $response = json_decode($response["body"], true);
            if (true == $response["success"]) {
                return $data;
            } else {
                $error_message = "Invalid Captcha";
                $wpforo->notice->add( $error_message, 'error');
                return false;
            } 
        } else {
            $error_message = "Invalid Captcha";
            $wpforo->notice->add( $error_message, 'error');
            return false;
        }
    }
}