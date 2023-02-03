<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use HCaptcha\Abstracts\LoginBase;

/**
 * Class Login.
 */
class Login extends LoginBase {

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
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_filter( self::TAG . '_shortcode_output', [ $this, 'add_captcha' ], 10, 2 );
	}

	/**
	 * Add hCaptcha to the login form.
	 *
	 * @param string|string[] $output      Module output.
	 * @param string          $module_slug Module slug.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function add_captcha( $output, $module_slug ) {
		if ( et_core_is_fb_enabled() ) {
			// Do not add captcha in frontend builder.

			return $output;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $output;
		}

		$pattern     = '/(<p>[\s]*?<button)/';
		$replacement = hcap_form( self::ACTION, self::NONCE ) . "\n" . '$1';

		// Insert hcaptcha.
		return preg_replace( $pattern, $replacement, $output );
	}
}
