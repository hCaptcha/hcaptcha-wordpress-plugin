<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'hcaptcha_options_nav' );
function hcaptcha_options_nav() {
	add_options_page( "hCaptcha Settings", "hCaptcha", "manage_options", "hcaptcha-options", "hcaptcha_options" );
}

function hcaptcha_options(){
	if ( !current_user_can( 'manage_options' ) )  {
		
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		
	}
	
	include dirname(__FILE__)."/settings.php";
}