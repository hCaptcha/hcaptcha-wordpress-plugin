<?php
function hcap_display_bbp_reply(){
	$hcaptcha_api_key = get_option('hcaptcha_api_key' );
	$hcaptcha_theme 	= get_option("hcaptcha_theme");
	$hcaptcha_size 		= get_option("hcaptcha_size");
    $output = '';
    $output .= '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    
    echo $output;
}

add_action( 'bbp_theme_after_reply_form_content', 'hcap_display_bbp_reply', 10, 0 );

function hcap_verify_bbp_reply_captcha() {

	if (isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

		$hcaptcha_secret_key = get_option('hcaptcha_secret_key');
		$response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
		$response = json_decode($response["body"], true);
		if (true == $response["success"]) {
            return true;
		} else {
            bbp_add_error( 'hcap_error', 'Invalid Captcha' );
        } 
	} else {
		bbp_add_error( 'hcap_error', 'Invalid Captcha' );
	}   
}
add_action( 'bbp_new_reply_pre_extras',  'hcap_verify_bbp_reply_captcha' ); 