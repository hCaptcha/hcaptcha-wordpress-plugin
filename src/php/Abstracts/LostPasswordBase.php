<?php
/**
 * LostPasswordBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

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
	 */
	protected function init_hooks() {
		add_action( static::ADD_CAPTCHA_ACTION, [ $this, 'add_captcha' ] );
		add_action( 'lostpassword_post', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha() {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'lost_password',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify lost password form.
	 *
	 * @param WP_Error|mixed $error Error.
	 *
	 * @return void
	 */
	public function verify( $error ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_value = isset( $_POST[ static::POST_KEY ] ) ?
			sanitize_text_field( wp_unslash( $_POST[ static::POST_KEY ] ) ) :
			'';

		if (
			( ! isset( $_POST[ static::POST_KEY ] ) ) ||
			( static::POST_VALUE && static::POST_VALUE !== $post_value )
		) {
			// Submitted lost password form cannot be handled by this class.
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$error_message = hcaptcha_verify_post(
			static::NONCE,
			static::ACTION
		);

		if ( null === $error_message ) {
			return;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		$error = is_wp_error( $error ) ? $error : new WP_Error();

		$error->add( $code, $error_message );
	}
}
