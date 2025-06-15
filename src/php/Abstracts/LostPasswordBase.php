<?php
/**
 * LostPasswordBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class LostPasswordBase
 */
abstract class LostPasswordBase {

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
		add_action( static::ADD_CAPTCHA_ACTION, [ $this, 'add_captcha' ] );
		add_action( 'lostpassword_post', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'lost_password',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify a lost password form.
	 *
	 * @param WP_Error|mixed $errors Error.
	 *
	 * @return void
	 */
	public function verify( $errors ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_value = isset( $_POST[ static::POST_KEY ] ) ?
			sanitize_text_field( wp_unslash( $_POST[ static::POST_KEY ] ) ) :
			'';

		if (
			( ! isset( $_POST[ static::POST_KEY ] ) ) ||
			( static::POST_VALUE && static::POST_VALUE !== $post_value )
		) {
			// This class cannot handle a submitted lost password form.
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$error_message = API::verify_post( static::NONCE, static::ACTION );

		HCaptcha::add_error_message( $errors, $error_message );
	}
}
