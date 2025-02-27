<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ColorlibCustomizer;

/**
 * Class Login
 */
class Login extends Base {

	/**
	 * Get login style.
	 *
	 * @param string $hcaptcha_size hCaptcha widget size.
	 *
	 * @return string
	 * @noinspection CssUnusedSymbol
	 */
	protected function get_style( string $hcaptcha_size ): string {
		$css = parent::get_style( $hcaptcha_size );

		switch ( $hcaptcha_size ) {
			case 'compact':
			case 'normal':
				/* language=CSS */
				$css .= '
	.ml-container #loginform {
		height: unset;
	}
';
				break;
			case 'invisible':
			default:
				break;
		}

		return $css;
	}
}
