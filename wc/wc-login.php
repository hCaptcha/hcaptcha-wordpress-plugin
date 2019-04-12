<?php
function hcap_display_wc_login(){
	$hcaptcha_api_key = get_option('hcaptcha_api_key' );
	$hcaptcha_theme 	= get_option("hcaptcha_theme");
    $hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';

    
    echo $output;
}

add_action( 'woocommerce_login_form', 'hcap_display_wc_login', 10, 0 );

function hcap_verify_wc_login_captcha($validation_error) {
	if (isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

		$hcaptcha_secret_key = get_option('hcaptcha_secret_key');
		$response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
		$response = json_decode($response["body"], true);
		if (true == $response["success"]) {
            return $validation_error;
		} else {
            $validation_error->add( 'hcaptcha_error' ,  __("The Captcha is invalid.",'hcaptcha-wp') );
            return $validation_error;
        } 
	} else {
		$validation_error->add( 'hcaptcha_error' ,  __("The Captcha is invalid.",'hcaptcha-wp') );
        return $validation_error;		
	}   
}
apply_filters( 'woocommerce_process_login_errors',  'hcap_verify_wc_login_captcha' ); 