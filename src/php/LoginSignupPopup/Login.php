<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LoginSignupPopup;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Form ID.
	 */
	private const FORM_ID = 'login';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'xoo_el_form_start', [ $this, 'form_start' ], 10, 2 );
		add_action( 'xoo_el_form_end', [ $this, 'add_login_signup_popup_hcaptcha' ], 10, 2 );
		add_filter( 'xoo_el_process_login_errors', [ $this, 'verify' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Form start.
	 *
	 * @param string $form Form.
	 * @param array  $args Arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function form_start( string $form, array $args ): void {
		if ( self::FORM_ID !== $form ) {
			return;
		}

		ob_start();
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string $form Form.
	 * @param array  $args Arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_login_signup_popup_hcaptcha( string $form, array $args ): void {
		if ( self::FORM_ID !== $form ) {
			return;
		}

		ob_start();
		$this->add_captcha();
		$hcaptcha = ob_get_clean();

		$form = ob_get_clean();

		$search = '<button type="submit"';
		$form   = str_replace( $search, $hcaptcha . "\n" . $search, $form );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Verify form.
	 *
	 * @param WP_Error|mixed $error       Error.
	 * @param array          $credentials Credentials.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $error, array $credentials ): WP_Error {
		if ( ! is_wp_error( $error ) ) {
			$error = new WP_Error();
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $error;
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $error;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.xoo-el-form-container div[data-section="login"] .h-captcha {
		margin-bottom: 25px;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-login-signup-popup',
			HCAPTCHA_URL . "/assets/js/hcaptcha-login-signup-popup$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
