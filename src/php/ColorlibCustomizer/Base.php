<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ColorlibCustomizer;

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
	 */
	protected function init_hooks() {
		add_action( 'login_head', [ $this, 'login_head' ] );
	}

	/**
	 * Print styles to fit hcaptcha widget to the login form.
	 *
	 * @return void
	 */
	public function login_head() {
		$hcaptcha_size = hcaptcha()->settings()->get( 'size' );

		if ( 'invisible' === $hcaptcha_size ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_style( $hcaptcha_size );
	}

	/**
	 * Get style.
	 *
	 * @param string $hcaptcha_size hCaptcha widget size.
	 *
	 * @return string
	 */
	abstract protected function get_style( string $hcaptcha_size ): string;
}
