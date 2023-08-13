<?php
/**
 * Functions file.
 *
 * @package hcaptcha-wp
 */

use HCaptcha\Helpers\HCaptcha;

/**
 * Get hCaptcha form.
 *
 * @param string $action Action name for wp_nonce_field.
 * @param string $name   Nonce name for wp_nonce_field.
 * @param bool   $auto   This form has to be auto-verified.
 *
 * @return string
 * @deprecated 2.7.0 Use \HCaptcha\Helpers\HCaptcha::form()
 */
function hcap_form( string $action = '', string $name = '', bool $auto = false ): string {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	_deprecated_function( __FUNCTION__, '2.7.0', HCaptcha::class . '::form()' );

	$args = [
		'action' => $action,
		'name'   => $name,
		'auto'   => $auto,
	];

	return HCaptcha::form( $args );
}

/**
 * Display hCaptcha form.
 *
 * @param string $action Action name for wp_nonce_field.
 * @param string $name   Nonce name for wp_nonce_field.
 * @param bool   $auto   This form has to be auto-verified.
 *
 * @deprecated 2.7.0 Use \HCaptcha\Helpers\HCaptcha::form_display()
 */
function hcap_form_display( string $action = '', string $name = '', bool $auto = false ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	_deprecated_function( __FUNCTION__, '2.7.0', HCaptcha::class . '::form_display()' );

	$args = [
		'action' => $action,
		'name'   => $name,
		'auto'   => $auto,
	];

	HCaptcha::form_display( $args );
}

/**
 * Display hCaptcha shortcode.
 *
 * @param array|string $atts hcaptcha shortcode attributes.
 *
 * @return string
 */
function hcap_shortcode( $atts ): string {
	/**
	 * Do not set default size here.
	 * If size is not normal|compact|invisible, it will be taken from plugin settings in HCaptcha::form().
	 */
	$atts = shortcode_atts(
		[
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => false,
			'size'   => '',
		],
		$atts
	);

	$atts['auto'] = filter_var( $atts['auto'], FILTER_VALIDATE_BOOLEAN );

	/**
	 * Filters the content of the hcaptcha form.
	 *
	 * @param string $form The hcaptcha form.
	 */
	return (string) apply_filters( 'hcap_hcaptcha_content', HCaptcha::form( $atts ) );
}

add_shortcode( 'hcaptcha', 'hcap_shortcode' );

// @codeCoverageIgnoreStart
if ( ! function_exists( 'wp_doing_ajax' ) ) :
	/**
	 * Determines whether the current request is a WordPress Ajax request.
	 *
	 * @since 4.7.0
	 *
	 * @return bool True if it's a WordPress Ajax request, false otherwise.
	 */
	function wp_doing_ajax() {
		/**
		 * Filters whether the current request is a WordPress Ajax request.
		 *
		 * @since 4.7.0
		 *
		 * @param bool $wp_doing_ajax Whether the current request is a WordPress Ajax request.
		 */
		return apply_filters( 'wp_doing_ajax', defined( 'DOING_AJAX' ) && DOING_AJAX );
	}
endif;
// @codeCoverageIgnoreEnd

/**
 * Get min suffix.
 *
 * @return string
 */
function hcap_min_suffix(): string {
	return defined( 'SCRIPT_DEBUG' ) && constant( 'SCRIPT_DEBUG' ) ? '' : '.min';
}
