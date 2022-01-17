<?php
/**
 * Divi class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use WP_Error;

/**
 * Class Login
 */
class Login {

	/**
	 * Login form shortcode tag.
	 */
	const TAG = 'et_pb_login';

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_login';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_login_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( self::TAG . '_shortcode_output', [ $this, 'add_captcha' ], 10, 2 );
	}

	/**
	 * Add hCaptcha to the Login form.
	 *
	 * @param string|string[] $output      Module output.
	 * @param string          $module_slug Module slug.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $output, $module_slug ) {
		if ( et_core_is_fb_enabled() ) {
			// Do not add captcha in frontend builder.

			return $output;
		}

		$pattern     = '/(<p>[\s]*?<button)/';
		$replacement = hcap_form( self::ACTION, self::NONCE ) . "\n" . '$1';

		// Insert hcaptcha.
		return preg_replace( $pattern, $replacement, $output );
	}

	/**
	 * Verify login form.
	 *
	 * @param WP_Error $errors      WP Error object.
	 * @param string   $redirect_to Redirect destination URL.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, $redirect_to ) {
		$error_message = hcaptcha_get_verify_message_html( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $errors;
		}

		$errors->add( 'invalid_hcaptcha', __( '<strong>Error</strong>: Invalid Captcha', 'hcaptcha-for-forms-and-more' ) );

		return $errors;
	}
}
