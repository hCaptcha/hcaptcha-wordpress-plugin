<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GravityForms;

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
		return hcap_form( HCAPTCHA_ACTION, HCAPTCHA_NONCE, true ) . $button_input;
	}
}
