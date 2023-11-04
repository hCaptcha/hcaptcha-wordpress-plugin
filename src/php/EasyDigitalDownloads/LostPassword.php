<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\EasyDigitalDownloads;

use HCaptcha\Helpers\HCaptcha;
use WP_Block;

/**
 * Class Form.
 */
class LostPassword {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_easy_digital_downloads_lostpassword';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_easy_digital_downloads_lostpassword_nonce';

	/**
	 * The hCaptcha error message.
	 *
	 * @var string|null
	 */
	private $error_message;

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'render_block', [ $this, 'add_captcha' ], 10, 3 );
		add_filter( 'edd_errors', [ $this, 'verify' ] );
	}

	/**
	 * Add hcaptcha to MailPoet form.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $block_content, array $block, WP_Block $instance ): string {
		if ( 'edd/login' !== $block['blockName'] || ! did_action( 'edd_lost_password_fields_after' ) ) {
			return $block_content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'lost_password',
			],
		];

		return preg_replace(
			'#(<div class="edd-blocks-form__group edd-blocks-form__group-submit">[\s\S]*?<input id="edd_lost_password_submit")#',
			'<div class="edd-blocks-form__group">' . HCaptcha::form( $args ) . '</div>$1',
			(string) $block_content
		);
	}

	/**
	 * Verify login form.
	 *
	 * @param array|mixed $errors Errors.
	 *
	 * @return array|mixed
	 */
	public function verify( $errors ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_value = isset( $_POST['edd_action'] ) ?
			sanitize_text_field( wp_unslash( $_POST['edd_action'] ) ) :
			'';

		if ( 'user_lost_password' !== $post_value ) {
			// Submitted lost password form cannot be handled by this class.
			return $errors;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->error_message = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null === $this->error_message ) {
			return $errors;
		}

		// Prevent lost password action.
		remove_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' );

		$code = array_search( $this->error_message, hcap_get_error_messages(), true ) ?: 'fail';

		$errors          = $errors ? (array) $errors : [];
		$errors[ $code ] = $this->error_message;

		return $errors;
	}
}
