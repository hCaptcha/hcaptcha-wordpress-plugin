<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\EasyDigitalDownloads;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use WP_Block;

/**
 * Class Form.
 */
class LostPassword {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_easy_digital_downloads_lostpassword';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_easy_digital_downloads_lostpassword_nonce';

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
		add_action( 'edd_user_lost_password', [ $this, 'verify' ], 9 );
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
			'id'     => $this->get_expected_id(),
		];

		return preg_replace(
			'#(<div class="edd-blocks-form__group edd-blocks-form__group-submit">[\s\S]*?<input id="edd_lost_password_submit")#',
			'<div class="edd-blocks-form__group">' . HCaptcha::form( $args ) . '</div>$1',
			(string) $block_content
		);
	}

	/**
	 * Verify the native recovery request before EDD retrieves a password.
	 *
	 * @param array|mixed $data Request selected by the EDD router.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( $data ): void {
		// Match the native handler's method and nonce gates before checking CAPTCHA.
		if (
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Match the native server method exactly.
			'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ||
			! is_array( $data ) ||
			empty( $data['edd_lost-password_nonce'] ) ||
			! is_scalar( $data['edd_lost-password_nonce'] ) ||
			! wp_verify_nonce( $data['edd_lost-password_nonce'], 'edd-lost-password-nonce' )
		) {
			return;
		}

		$entry         = EntryData::with_emails( [ 'data' => EntryData::from_array( wp_unslash( $data ), [] ) ], [] );
		$error_message = API::verify_post( self::NONCE, self::ACTION, $this->get_expected_id(), $entry );

		if ( null === $error_message ) {
			return;
		}

		// Prevent lost password action.
		remove_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' );

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';
		edd_set_error( $code, $error_message );
	}

	/**
	 * Get the password recovery form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'lost_password',
		];
	}
}
