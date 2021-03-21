<?php
/**
 * Lost password hooks file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

add_action( 'lostpassword_form', 'hcaptcha_lost_password_display' );
add_action( 'lostpassword_post', 'hcaptcha_lost_password_verify' );
