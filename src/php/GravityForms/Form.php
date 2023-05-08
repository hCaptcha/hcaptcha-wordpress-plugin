<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GravityForms;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form
 */
class Form {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( 'gform_submit_button', [ $this, 'add_captcha' ], 10, 2 );
		add_filter( 'hcap_verify_request', [ $this, 'verify_request' ], 10, 2 );
	}

	/**
	 * Filter the submit button element HTML.
	 *
	 * @param string $button_input Button HTML.
	 * @param array  $form         Form data and settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $button_input, $form ) {
		$args = [
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => true,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => isset( $form['id'] ) ? $form['id'] : 0,
			],
		];

		return HCaptcha::form( $args ) . $button_input;
	}

	/**
	 * Verify request filter.
	 *
	 * @param string|null $result      Result of the hCaptcha verification.
	 * @param array       $error_codes Error codes.
	 *
	 * @return string|null
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_request( $result, $error_codes ) {
		// Nonce is checked in the hcaptcha_verify_post().

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['gform_submit'] ) ) {
			// We are not in the Gravity Form submit.
			return $result;
		}

		$form_id     = (int) $_POST['gform_submit'];
		$target_page = "gform_target_page_number_$form_id";

		if ( isset( $_POST[ $target_page ] ) && 0 !== (int) $_POST[ $target_page ] ) {
			// Do not verify hCaptcha and return success when switching between form pages.
			return null;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $result;
	}
}
