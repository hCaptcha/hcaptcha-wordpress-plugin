<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPForms;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wpforms';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_wpforms_nonce';

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
		add_filter( 'wpforms_display_submit_before', [ $this, 'add_captcha' ] );
		add_action( 'wpforms_process', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Action that fires immediately before the submit button element is displayed.
	 *
	 * @link         https://wpforms.com/developers/wpforms_display_submit_before/
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $form_data ) {
		hcap_form_display( self::ACTION, self::NAME );
	}

	/**
	 * Action that fires during form entry processing after initial field validation.
	 *
	 * @link         https://wpforms.com/developers/wpforms_process/
	 *
	 * @param array $fields    Sanitized entry field. values/properties.
	 * @param array $entry     Original $_POST global.
	 * @param array $form_data Form data and settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $fields, $entry, $form_data ) {
		$error_message = hcaptcha_get_verify_message(
			self::NAME,
			self::ACTION
		);

		if ( null !== $error_message ) {
			wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] = $error_message;
		}
	}
}
