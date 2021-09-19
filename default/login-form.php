<?php
/**
 * Login form file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Print styles to fit hcaptcha widget to the login form.
 */
function hcaptcha_login_head() {
	?>
	<style>
		.h-captcha {
			display: flex;
			justify-content: center;
		}
		.h-captcha[data-size="normal"] iframe {
			transform: scale( 0.89 );
		}
	</style>
	<?php
}

add_action( 'login_head', 'hcaptcha_login_head' );

/**
 * Login form.
 */
function hcap_wp_login_form() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce' );
}

add_filter( 'login_form', 'hcap_wp_login_form' );

/**
 * Verify login captcha.
 *
 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous
 *                                   callback failed authentication.
 * @param string           $password Password to check against the user.
 *
 * @return WP_User|WP_Error
 */
function hcap_verify_login_captcha( $user, $password ) {
	$error_message = hcaptcha_get_verify_message_html(
		'hcaptcha_login_nonce',
		'hcaptcha_login'
	);

	if ( null === $error_message ) {
		return $user;
	}

	return new WP_Error( __( 'Invalid Captcha', 'hcaptcha-for-forms-and-more' ), $error_message );
}

add_filter( 'wp_authenticate_user', 'hcap_verify_login_captcha', 10, 2 );
