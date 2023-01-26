<?php
/**
 * Functions file.
 *
 * @package hcaptcha-wp
 */

/**
 * Get hCaptcha form.
 *
 * @param string $action Action name for wp_nonce_field.
 * @param string $name   Nonce name for wp_nonce_field.
 * @param bool   $auto   This form has to be auto-verified.
 *
 * @return false|string
 */
function hcap_form( $action = '', $name = '', $auto = false ) {
	ob_start();
	hcap_form_display( $action, $name, $auto );

	return ob_get_clean();
}

/**
 * Display hCaptcha form.
 *
 * @param string $action Action name for wp_nonce_field.
 * @param string $name   Nonce name for wp_nonce_field.
 * @param bool   $auto   This form has to be auto-verified.
 */
function hcap_form_display( $action = '', $name = '', $auto = false ) {
	$settings          = hcaptcha()->settings();
	$hcaptcha_site_key = $settings->get_site_key();
	$hcaptcha_theme    = $settings->get( 'theme' );
	$hcaptcha_size     = $settings->get( 'size' );

	$callback = 'invisible' === $hcaptcha_size ? 'data-callback="hCaptchaSubmit"' : '';

	?>
	<div
			class="h-captcha"
			data-sitekey="<?php echo esc_attr( $hcaptcha_site_key ); ?>"
			data-theme="<?php echo esc_attr( $hcaptcha_theme ); ?>"
			data-size="<?php echo esc_attr( $hcaptcha_size ); ?>"
			<?php echo wp_kses_post( $callback ); ?>
			data-auto="<?php echo $auto ? 'true' : 'false'; ?>">
	</div>
	<?php

	if ( ! empty( $action ) && ! empty( $name ) ) {
		wp_nonce_field( $action, $name );
	}

	hcaptcha()->form_shown = true;
}

/**
 * Display hCaptcha shortcode.
 *
 * @param array|string $atts hcaptcha shortcode attributes.
 *
 * @return string
 */
function hcap_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => false,
		],
		$atts
	);

	$atts['auto'] = filter_var( $atts['auto'], FILTER_VALIDATE_BOOLEAN );

	/**
	 * Filters the content of the hcaptcha form.
	 *
	 * @param string $form The hcaptcha form.
	 */
	return apply_filters( 'hcap_hcaptcha_content', hcap_form( $atts['action'], $atts['name'], $atts['auto'] ) );
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
function hcap_min_suffix() {
	return defined( 'SCRIPT_DEBUG' ) && constant( 'SCRIPT_DEBUG' ) ? '' : '.min';
}
