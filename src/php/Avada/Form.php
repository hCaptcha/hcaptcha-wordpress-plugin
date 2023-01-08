<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Avada;

/**
 * Class Form.
 */
class Form {

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'fusion_element_button_content', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'fusion_form_demo_mode', [ $this, 'verify' ] );
	}

	/**
	 * Filters the Avada Form button and adds hcaptcha.
	 *
	 * @param string $html Button html.
	 * @param array  $args Arguments.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $html, $args ) {
		if ( false === strpos( $html, '<button type="submit"' ) ) {
			return $html;
		}

		$hcaptcha = hcap_form();

		return $hcaptcha . $html;
	}

	/**
	 * Verify request.
	 *
	 * @param bool $demo_mode Demo mode.
	 *
	 * @return bool|void
	 */
	public function verify( $demo_mode ) {

		// Nonce is checked by Avada.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Missing
		$form_data = isset( $_POST['formData'] ) ?
			filter_var( wp_unslash( $_POST['formData'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			[];

		$form_data         = wp_parse_args( str_replace( '&amp;', '&', $form_data ) );
		$hcaptcha_response = isset( $form_data['h-captcha-response'] ) ? $form_data['h-captcha-response'] : '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Missing

		$result = hcaptcha_request_verify( $hcaptcha_response );

		if ( null === $result ) {
			return $demo_mode;
		}

		die(
			wp_json_encode(
				[
					'status' => 'error',
					'info'   => [ 'hcaptcha' => $result ],
				]
			)
		);
	}
}
