<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Avada;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Form id.
	 *
	 * @var int
	 */
	private $form_id = 0;

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
	public function init_hooks(): void {
		add_action( 'fusion_form_after_open', [ $this, 'form_after_open' ], 10, 2 );
		add_filter( 'fusion_builder_form_submission_data', [ $this, 'submission_data' ] );
		add_action( 'fusion_element_form_content', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'fusion_form_demo_mode', [ $this, 'verify' ] );
	}

	/**
	 * Store form id after form open.
	 *
	 * @param array $args   Argument.
	 * @param array $params Parameters.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function form_after_open( array $args, array $params ): void {
		$this->form_id = isset( $params['id'] ) ? (int) $params['id'] : 0;
	}

	/**
	 * Filter submission data.
	 *
	 * @param array|mixed $data Submission data.
	 *
	 * @return array
	 */
	public function submission_data( $data ): array {
		$data = (array) $data;

		unset(
			$data['data']['hcaptcha-widget-id'],
			$data['data']['h-captcha-response'],
			$data['data']['g-recaptcha-response']
		);

		return $data;
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
	public function add_hcaptcha( string $html, array $args ): string {
		// Find the last occurrence of the 'submit' button.
		// There are several submit buttons on a multistep form.
		$last_pos = strrpos( $html, '<button type="submit"' );

		if ( false === $last_pos ) {
			return $html;
		}

		// Split the HTML into two parts at the position of the last 'submit' button.
		$first_part  = substr( $html, 0, $last_pos );
		$second_part = substr( $html, $last_pos );

		$hcap_args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		$hcaptcha = HCaptcha::form( $hcap_args );

		return $first_part . $hcaptcha . $second_part;
	}

	/**
	 * Verify request.
	 *
	 * @param bool|mixed $demo_mode Demo mode.
	 *
	 * @return bool|mixed|void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify( $demo_mode ) {

		// Nonce is checked by Avada.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Missing
		$form_data = isset( $_POST['formData'] ) ?
			filter_var( wp_unslash( $_POST['formData'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			[];

		$form_data                   = wp_parse_args( str_replace( '&amp;', '&', $form_data ) );
		$hcaptcha_response           = $form_data['h-captcha-response'] ?? '';
		$_POST['hcaptcha-widget-id'] = $form_data['hcaptcha-widget-id'] ?? '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Missing

		$result = hcaptcha_request_verify( $hcaptcha_response );

		if ( null === $result ) {
			return $demo_mode;
		}

		wp_die(
			wp_json_encode(
				[
					'status' => 'error',
					'info'   => [ 'hcaptcha' => $result ],
				]
			)
		);
	}
}
