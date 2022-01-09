<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\FluentForm;

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
		add_action( 'fluentform_form_element_start', [ $this, 'add_captcha' ], 10, 1 );
		add_action( 'fluentform_before_insert_submission', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Action that fires immediately before the submit button element is displayed.
	 *
	 * @link  https://fluentforms.com/docs/fluentform_after_form_render
	 * @param array $form Form data and settings...
	 */
	public function add_captcha( $form ) {
		hcap_form_display( 'hcaptcha_fluentform', 'hcaptcha_fluentform_nounce' );
	}

	/**
	 * Action that fires during form entry processing after initial field validation.
	 *
	 * @link  https://fluentforms.com/docs/fluentform_before_insert_submission
	 *
	 * @param  array $insertData    Sanitized entry field. values/properties.
	 * @param  array $data          Original $_POST global.
	 * @param  array $form          Form data and settings.
	 *
	 * @return void
	 */
	public function verify( $insertData, $data, $form ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_fluentform_nounce',
			'hcaptcha_fluentform'
		);

		if ( null === $error_message ) {
			return;
		}

		wp_send_json(
			[
				'errors' => [
					'g-recaptcha-response' => [
						$error_message,
					],
				],
			],
			422
		);
	}
}
