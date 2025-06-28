<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\EasyDigitalDownloads;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Block;

/**
 * Class Form.
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_easy_digital_downloads_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_easy_digital_downloads_register_nonce';

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
	private function init_hooks(): void {
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
		if ( 'edd/register' !== $block['blockName'] || ! did_action( 'edd_register_form_fields_after' ) ) {
			return $block_content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		$search = '<div class="edd-blocks-form__group edd-blocks-form__group-submit reset-pass-submit">';

		return str_replace(
			$search,
			'<div class="edd-blocks-form__group">' . HCaptcha::form( $args ) . '</div>' . $search,
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

		if ( 'user_register' !== $post_value ) {
			return $errors;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $errors;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		$errors          = $errors ? (array) $errors : [];
		$errors[ $code ] = $error_message;

		return $errors;
	}
}
