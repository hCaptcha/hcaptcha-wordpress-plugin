<?php
/**
 * `Reply` class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\Request;

/**
 * Class Reply.
 */
class Reply extends Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_bbp_reply';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_bbp_reply_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'bbp_theme_after_reply_form_content';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'bbp_new_reply_pre_extras';

	/**
	 * Verify captcha.
	 *
	 * @return bool
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): bool {
		$error_message = API::verify( $this->get_entry() );

		if ( null !== $error_message ) {
			bbp_add_error( 'hcap_error', $error_message );

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
		$data = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $_POST as $key => $value ) {
			$value = sanitize_text_field( wp_unslash( $value ) );

			if ( 0 === strpos( $key, 'bbp_' ) ) {
				$data[ str_replace( 'bbp_', '', $key ) ] = $value;
			}
		}

		$topic_id = (int) $data['topic_id'];
		$topic    = get_post( $topic_id );

		return [
			'nonce_name'         => self::NAME,
			'nonce_action'       => self::ACTION,
			'h-captcha-response' => Request::filter_input( INPUT_POST, 'h-captcha-response' ),
			'form_date_gmt'      => $topic->post_modified_gmt ?? null,
			'data'               => $data,
		];
	}
}
