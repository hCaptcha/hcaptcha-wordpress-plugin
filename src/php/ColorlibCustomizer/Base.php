<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ColorlibCustomizer;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Login
 */
abstract class Base {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'login_head', [ $this, 'login_head' ] );
	}

	/**
	 * Print styles to fit hcaptcha widget to the login form.
	 *
	 * @return void
	 */
	public function login_head(): void {
		$hcaptcha_size = hcaptcha()->settings()->get( 'size' );

		if ( 'invisible' === $hcaptcha_size ) {
			return;
		}

		HCaptcha::css_display( $this->get_style( $hcaptcha_size ) );
	}

	/**
	 * Get style.
	 *
	 * @param string $hcaptcha_size hCaptcha widget size.
	 *
	 * @return string
	 * @noinspection CssUnusedSymbol
	 */
	protected function get_style( string $hcaptcha_size ): string {
		static $style_shown;

		if ( $style_shown ) {
			return '';
		}

		$style_shown = true;
		$css         = '';

		if ( 'normal' === $hcaptcha_size ) {
			$css = <<<CSS
	.ml-container #login {
		min-width: 350px;
	}
CSS;
		}

		return $css;
	}
}
