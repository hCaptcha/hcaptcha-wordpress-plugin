<?php
/**
 * PasswordProtected class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\HCaptcha;
use WP_Post;

/**
 * Class PasswordProtected.
 */
class PasswordProtected {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_password_protected';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_password_protected_nonce';

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
	public function init_hooks(): void {
		add_filter( 'the_password_form', [ $this, 'add_captcha' ], PHP_INT_MAX, 2 );
		add_action( 'login_form_postpass', [ $this, 'verify' ] );
	}

	/**
	 * Filters the template created by the Download Manager plugin and adds hcaptcha.
	 *
	 * @param string|mixed $output The password form's HTML output.
	 * @param WP_Post      $post   Post object.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $output, WP_Post $post ): string {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'password_protected',
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		return (string) preg_replace( '/(<\/form>)/', $hcaptcha . '$1', (string) $output );
	}

	/**
	 * Verify request.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify(): void {
		$result = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null === $result ) {
			return;
		}

		wp_die(
			esc_html( $result ),
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 303,
			]
		);
	}
}
