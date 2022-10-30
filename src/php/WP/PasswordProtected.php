<?php
/**
 * PasswordProtected class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use WP_Post;

/**
 * Class PasswordProtected.
 */
class PasswordProtected {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_password_protected';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_password_protected_nonce';

	/**
	 * PasswordProtected constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'the_password_form', [ $this, 'add_hcaptcha' ], PHP_INT_MAX, 2 );
		add_action( 'login_form_postpass', [ $this, 'verify' ], 10 );
	}

	/**
	 * Filters the template created by the Download Manager plugin and adds hcaptcha.
	 *
	 * @param string  $output The password form HTML output.
	 * @param WP_Post $post   Post object.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, $post ) {
		$hcaptcha = hcap_form( self::ACTION, self::NONCE );

		return preg_replace( '/(<\/form>)/', $hcaptcha . '$1', $output );
	}

	/**
	 * Verify request.
	 *
	 * @param array|null $package Result of the hCaptcha verification.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify( $package ) {

		$result = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null === $result ) {
			return;
		}

		wp_die(
			esc_html( $result ),
			esc_html__( 'hCaptcha error', 'hcaptcha-for-forms-and-more' ),
			[
				'back_link' => true,
				'response'  => 303,
			]
		);
	}
}
