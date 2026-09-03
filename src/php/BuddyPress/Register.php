<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BuddyPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Register.
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_bp_register';

	/**
	 * Nonce name.
	 */
	private const NAME = 'hcaptcha_bp_register_nonce';

	/**
	 * Register constructor.
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
		add_action( 'bp_before_registration_submit_buttons', [ $this, 'add_captcha' ] );
		add_action( 'bp_signup_validate', [ $this, 'verify' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add captcha to the register form.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		global $bp;

		echo '<div class="hcap_buddypress_register_form">';

		if ( ! empty( $bp->signup->errors['hcaptcha_response_verify'] ) ) {
			$output = '<div class="error">';

			$output .= $bp->signup->errors['hcaptcha_response_verify'];
			$output .= '</div>';

			echo wp_kses_post( $output );
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		HCaptcha::form_display( $args );

		echo '</div>';
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
	#buddypress .standard-form .hcap_buddypress_register_form {
		clear: both;
		margin-inline-start: 52%;
		width: 48%;
	}

	@media screen and (max-width: 46.8em) {
		#buddypress .standard-form .hcap_buddypress_register_form {
			margin-inline-start: 0;
			width: 100%;
		}
	}
';
		HCaptcha::css_display( $css );
	}

	/**
	 * Verify register form captcha.
	 *
	 * @return bool
	 */
	public function verify(): bool {
		global $bp;

		$error_message = API::verify( $this->get_entry() );

		if ( null !== $error_message ) {
			$bp->signup->errors['hcaptcha_response_verify'] = $error_message;

			return false;
		}

		return true;
	}

	/**
	 * Get entry.
	 *
	 * @return array
	 */
	private function get_entry(): array {
		return [
			'nonce_name'   => self::NAME,
			'nonce_action' => self::ACTION,
			'expected_id'  => $this->get_expected_id(),
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'register',
		];
	}
}
