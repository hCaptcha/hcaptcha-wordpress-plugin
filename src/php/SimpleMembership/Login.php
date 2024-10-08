<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SimpleMembership;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'swpm_validate_login_form_submission', [ $this, 'verify' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hCaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, string $tag, $attr, array $m ) {
		if ( 'swpm_login_form' !== $tag ) {
			return $output;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $output;
		}

		ob_start();

		$this->add_captcha();
		$hcaptcha = (string) ob_get_clean();

		ob_start();

		/**
		 * Display hCaptcha signature.
		 */
		do_action( 'hcap_signature' );

		$signatures = (string) ob_get_clean();

		$pattern     = '/(<div class="swpm-login-submit")/';
		$replacement = $hcaptcha . $signatures . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}

	/**
	 * Verify a login form.
	 *
	 * @param string|mixed $error_message Error message.
	 *
	 * @return string
	 */
	public function verify( $error_message ): string {
		if ( ! $this->is_login_limit_exceeded() ) {
			return (string) $error_message;
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		return (string) $error_message;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	#swpm-login-form .h-captcha {
		margin: 10px 0;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
