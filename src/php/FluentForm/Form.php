<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\FluentForm;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form
 */
class Form {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_fluentform';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_fluentform_nonce';

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
		add_action( 'fluentform_render_item_submit_button', [ $this, 'add_captcha' ] );
		add_action( 'fluentform_before_insert_submission', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Action that fires immediately before the submit button element is displayed.
	 *
	 * @link         https://fluentforms.com/docs/fluentform_after_form_render
	 *
	 * @param array $form Form data and settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $form ) {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Action that fires during form entry processing after initial field validation.
	 *
	 * @link         https://fluentforms.com/docs/fluentform_before_insert_submission
	 *
	 * @param array $insert_data Sanitized entry field. values/properties.
	 * @param array $data        Original $_POST global.
	 * @param array $form        Form data and settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $insert_data, $data, $form ) {
		$_POST[ self::NONCE ]        = $data[ self::NONCE ];
		$_POST['h-captcha-response'] = $data['h-captcha-response'];

		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return;
		}

		wp_send_json(
			[
				'errors' => [
					'g-recaptcha-response' => [ $error_message ],
				],
			],
			422
		);
	}
}
