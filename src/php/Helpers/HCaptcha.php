<?php
/**
 * HCaptcha class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Class HCaptcha.
 */
class HCaptcha {

	/**
	 * Get hCaptcha form.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	public static function form( $args = [] ) {
		ob_start();
		self::form_display( $args );

		return ob_get_clean();
	}

	/**
	 * Display hCaptcha form.
	 *
	 * @param array $args Arguments.
	 */
	public static function form_display( $args = [] ) {
		$settings          = hcaptcha()->settings();
		$hcaptcha_site_key = $settings->get_site_key();
		$hcaptcha_theme    = $settings->get( 'theme' );
		$hcaptcha_size     = $settings->get( 'size' );
		$allowed_sizes     = [ 'normal', 'compact', 'invisible' ];

		$args = wp_parse_args(
			$args,
			[
				'action' => '', // Action name for wp_nonce_field.
				'name'   => '', // Nonce name for wp_nonce_field.
				'auto'   => false, // Whether a form has to be auto-verified.
				'size'   => $hcaptcha_size, // The hCaptcha widget size.
			]
		);

		$args['auto'] = filter_var( $args['auto'], FILTER_VALIDATE_BOOLEAN );
		$args['size'] = in_array( $args['size'], $allowed_sizes, true ) ? $args['size'] : $hcaptcha_size;
		$callback     = 'invisible' === $args['size'] ? 'data-callback="hCaptchaSubmit"' : '';

		?>
		<div
			class="h-captcha"
			data-sitekey="<?php echo esc_attr( $hcaptcha_site_key ); ?>"
			data-theme="<?php echo esc_attr( $hcaptcha_theme ); ?>"
			data-size="<?php echo esc_attr( $args['size'] ); ?>"
			<?php echo wp_kses_post( $callback ); ?>
			data-auto="<?php echo $args['auto'] ? 'true' : 'false'; ?>">
		</div>
		<?php

		if ( ! empty( $args['action'] ) && ! empty( $args['name'] ) ) {
			wp_nonce_field( $args['action'], $args['name'] );
		}

		hcaptcha()->form_shown = true;
	}
}
