<?php
/**
 * 'Protect' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\PasswordProtected;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Protect
 */
class Protect {

	/**
	 * Verify action.
	 */
	private const ACTION = 'hcaptcha_password_protected';

	/**
	 * Verify nonce.
	 */
	private const NONCE = 'hcaptcha_password_protected_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_filter( 'password_protected_below_password_field', [ $this, 'add_hcaptcha' ] );
		add_action( 'password_protected_verify_recaptcha', [ $this, 'verify' ] );
		add_action( 'password_protected_login_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @return void
	 */
	public function add_hcaptcha(): void {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'protect',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param WP_Error|null|mixed $errors Errors.
	 *
	 * @return WP_Error|null
	 */
	public function verify( $errors ): ?WP_Error {
		$errors = is_wp_error( $errors ) ? $errors : new WP_Error();

		$error_message = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $errors;
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
	body.login-password-protected #loginform {
		min-width: 302px;
	}
	body.login-password-protected p.submit + div {
		margin-bottom: 15px;
	}
';

		HCaptcha::css_display( $css );
	}
}
