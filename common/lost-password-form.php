<?php
/**
 * Lost password form file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display on lost password form.
 */
function hcaptcha_lost_password_display() {
	$hcaptcha_api_key = get_option( 'hcaptcha_api_key' );
	$hcaptcha_theme   = get_option( 'hcaptcha_theme' );
	$hcaptcha_size    = get_option( 'hcaptcha_size' );
	?>
	<div
			class="h-captcha"
			data-sitekey="<?php echo esc_html( $hcaptcha_api_key ); ?>"
			data-theme="<?php echo esc_html( $hcaptcha_theme ); ?>"
			data-size="<?php echo esc_html( $hcaptcha_size ); ?>">
	</div>
	<?php
	wp_nonce_field( 'hcaptcha_lost_password', 'hcaptcha_lost_password_nonce' );
}

/**
 * Verify lost password form.
 *
 * @param WP_Error $error Error.
 */
function hcaptcha_lost_password_verify( $error ) {
	$error_message = hcaptcha_get_verify_message_html( 'hcaptcha_lost_password_nonce', 'hcaptcha_lost_password' );
	if ( null !== $error_message ) {
		$error->add( 'invalid_captcha', $error_message );
	}
}
