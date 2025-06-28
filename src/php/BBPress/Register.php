<?php
/**
 * Register class' file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register.
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_bbp_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_bbp_register_nonce';

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
	protected function init_hooks(): void {
		add_filter( 'do_shortcode_tag', [ $this, 'add_captcha' ], 10, 4 );

		if ( hcaptcha()->settings()->is( 'bbp_status', 'register' ) ) {
			add_filter( 'registration_errors', [ $this, 'verify' ], 10, 3 );
		} else {
			add_filter( 'hcap_protect_form', [ $this, 'hcap_protect_form' ], 10, 3 );
		}
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attribute array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $output, string $tag, $attr, array $m ) {
		if ( 'bbp-register' !== $tag || is_user_logged_in() ) {
			return $output;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$pattern     = '/(<button type="submit")/';
		$replacement = $hcaptcha . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}

	/**
	 * Verify register captcha.
	 *
	 * @param WP_Error|mixed $errors               A WP_Error object containing any errors encountered during
	 *                                             registration.
	 * @param string         $sanitized_user_login User's username after it has been sanitized.
	 * @param string         $user_email           User's email.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, string $sanitized_user_login, string $user_email ): WP_Error {
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		return HCaptcha::add_error_message( $errors, $error_message );
	}

	/**
	 * Protect form filter.
	 * We need it to ignore auto verification of the Register form when its option is off.
	 *
	 * @param bool|mixed $value   The protection status of a form.
	 * @param string[]   $source  The source of the form (plugin, theme, WordPress Core).
	 * @param int|string $form_id Form id.
	 *
	 * @return bool
	 */
	public function hcap_protect_form( $value, array $source, $form_id ): bool {
		if (
			'register' === $form_id &&
			HCaptcha::get_class_source( __CLASS__ ) === $source
		) {
			return false;
		}

		return (bool) $value;
	}
}
