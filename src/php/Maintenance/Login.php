<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Maintenance;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class Login
 */
class Login extends LoginBase {
	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_maintenance_login';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_maintenance_login_nonce';

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	private $error_message;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'load_custom_style', [ hcaptcha(), 'print_inline_styles' ] );
		add_action( 'load_custom_style', [ $this, 'print_inline_styles' ] );
		add_action( 'after_main_container', [ $this, 'after_main_container' ] );
		add_action( 'load_custom_scripts', [ $this, 'add_hcaptcha' ] );

		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Start the output buffer.
	 *
	 * @return void
	 */
	public function after_main_container(): void {
		ob_start();
	}

	/**
	 * Add hCaptcha.
	 *
	 * @return void
	 */
	public function add_hcaptcha(): void {
		$login_form = ob_get_clean();
		$hcap_form  = $this->get_hcaptcha();
		$search     = '<input type="submit"';
		$login_form = str_replace( $search, "\n" . $hcap_form . "\n" . $search, $login_form );

		if ( $this->error_message && preg_match( '#(<span class="login-error">).*?(</span>)#', $login_form, $m ) ) {
			$login_form = str_replace( $m[0], $m[1] . $this->error_message . $m[2], $login_form );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $login_form;

		do_action( 'wp_print_footer_scripts' );
	}

	/**
	 * Verify a login form.
	 *
	 * @since        1.0
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		// Nonce for this field is checked in Maintenance plugin.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['is_custom_login'] ) ) {
			return $user;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$this->error_message = API::verify_post_html( self::NONCE, self::ACTION );

		if ( null === $this->error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', $this->error_message, 400 );
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
	body.maintenance > .login-form-container {
		min-width: 343px;
		max-width: 343px;
		right: -343px;
	}

	body.maintenance #login-form a.lost-pass {
		margin-bottom: 2em;
	}

	body.maintenance #login-form .h-captcha {
		margin-top: 2em;
	}
';

		HCaptcha::css_display( $css );
	}
}
