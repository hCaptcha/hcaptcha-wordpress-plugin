<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Avada;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;

/**
 * Class Form.
 */
class Form {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-avada';

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
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
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
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_data = isset( $_POST['formData'] )
			? wp_parse_args( html_entity_decode( wp_unslash( $_POST['formData'] ) ) )
			: [];
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$_POST['hcaptcha-widget-id'] = $form_data['hcaptcha-widget-id'] ?? '';
		$_POST['hcap_fst_token']     = $form_data['hcap_fst_token'] ?? '';
		$hp_name                     = API::get_hp_name( $form_data );
		$_POST[ $hp_name ]           = $form_data[ $hp_name ] ?? '';
		$_POST['hcap_hp_sig']        = $form_data['hcap_hp_sig'] ?? '';

		$result = API::verify( $this->get_entry( $form_data ) );

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

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-avada$min.js",
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Get entry.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_entry( array $form_data ): array {
		$form_id = 0;

		foreach ( $form_data as $key => $value ) {
			if ( 0 === strpos( $key, 'fusion-form-nonce-' ) ) {
				$form_id = (int) str_replace( 'fusion-form-nonce-', '', $key );

				break;
			}
		}

		$form = get_post( $form_id );

		$entry = [
			'h-captcha-response' => $form_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $form->post_modified_gmt ?? null,
			'data'               => [],
		];

		$field_types = json_decode( Request::filter_input( INPUT_POST, 'field_types' ), true );

		foreach ( $form_data as $key => $value ) {
			$type = $field_types[ $key ] ?? '';

			if ( ! in_array( $type, [ 'text', 'email', 'textarea' ], true ) ) {
				continue;
			}

			$entry['data'][ $key ] = $value;
		}

		return $entry;
	}
}
