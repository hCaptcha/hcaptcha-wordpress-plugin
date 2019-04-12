<?php
function hcap_display_bp_register(){
	$hcaptcha_api_key = get_option('hcaptcha_api_key' );
	$hcaptcha_theme 	= get_option("hcaptcha_theme");
	$hcaptcha_size 		= get_option("hcaptcha_size");

    $output = '';
    global $bp;
    if ( ! empty( $bp->signup->errors['hcaptcha_response_verify'] ) ) {
		$output .= '<div class="error">';
		$output .= $bp->signup->errors['hcaptcha_response_verify'];
		$output .= '</div>';
	}
    $output .= '<div class="h-captcha" data-sitekey="'.$hcaptcha_api_key.'" data-theme="'.$hcaptcha_theme.'" data-size="'.$hcaptcha_size.'"></div>';
    
    echo $output;
}

add_action( 'bp_before_registration_submit_buttons', 'hcap_display_bp_register', 10, 0 );

function hcap_verify_bp_register_captcha() {
    global $bp;
	if (isset($_POST['h-captcha-response'])) {
        $get_hcaptcha_response = htmlspecialchars($_POST['h-captcha-response']);

		$hcaptcha_secret_key = get_option('hcaptcha_secret_key');
		$response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
		$response = json_decode($response["body"], true);
		if (true == $response["success"]) {
            return true;
		} else {
            $bp->signup->errors['hcaptcha_response_verify'] = __('Invalid Captcha', 'hcaptcha-wp');
        } 
	} else {
		$bp->signup->errors['hcaptcha_response_verify'] = __('Please verify Captcha', 'hcaptcha-wp');	
	}   
}
add_action( 'bp_signup_validate',  'hcap_verify_bp_register_captcha' ); 