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
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_easy_digital_downloads_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_easy_digital_downloads_register_nonce';

	/**
	 * Requests owned by active native registration handlers.
	 *
	 * @var array[]
	 */
	private array $registration_data = [];

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
		add_action( 'edd_user_register', [ $this, 'begin_registration' ], 9 );
		add_action( 'edd_user_register', [ $this, 'end_registration' ], 11 );
		add_filter( 'edd_errors', [ $this, 'verify' ] );
	}

	/**
	 * Capture the request selected by the native dispatcher.
	 *
	 * @param array $data Native registration data.
	 *
	 * @return void
	 */
	public function begin_registration( array $data ): void {
		$this->registration_data[] = [
			'data'          => $data,
			'verified'      => false,
			'error_message' => null,
		];
	}

	/**
	 * Release the request after the native registration handler returns.
	 *
	 * @return void
	 */
	public function end_registration(): void {
		array_pop( $this->registration_data );
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
		if ( 'edd/register' !== $block['blockName'] || ! did_action( 'edd_register_form_fields_after' ) ) {
			return $block_content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$search = '<div class="edd-blocks-form__group edd-blocks-form__group-submit reset-pass-submit">';

		return str_replace(
			$search,
			'<div class="edd-blocks-form__group">' . HCaptcha::form( $args ) . '</div>' . $search,
			(string) $block_content
		);
	}

	/**
	 * Verify login form.
	 *
	 * @param array|mixed $errors Errors.
	 *
	 * @return array|mixed
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( $errors ) {
		if ( ! $this->registration_data || ! doing_action( 'edd_user_register' ) ) {
			return $errors;
		}

		$registration = &$this->registration_data[ count( $this->registration_data ) - 1 ];

		// Repeated native error reads must not consume this immutable request's proof again.
		if ( ! $registration['verified'] ) {
			$registration['error_message'] = $this->verify_registration( $registration['data'] );
			$registration['verified']      = true;
		}

		$error_message = $registration['error_message'];

		if ( null === $error_message ) {
			return $errors;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		$errors          = $errors ? (array) $errors : [];
		$errors[ $code ] = $error_message;
		edd_set_error( $code, $error_message );

		return $errors;
	}

	/**
	 * Verify proof and email from one native registration request.
	 *
	 * @param array $data Native registration data.
	 *
	 * @return string|null
	 */
	private function verify_registration( array $data ): ?string {
		// Match the native endpoint's sanitation of its slashed email field.
		$email = isset( $data['edd_user_email'] ) && is_scalar( $data['edd_user_email'] ) ? sanitize_email( (string) $data['edd_user_email'] ) : '';

		// Missing proof in one transport must not inherit another transport's fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The API validates the selected request below.
		$previous_post = $_POST;
		$_POST         = $data;

		try {
			return API::verify_post(
				self::NONCE,
				self::ACTION,
				$this->get_expected_id(),
				EntryData::with_emails( [], [ $email ] )
			);
		} finally {
			$_POST = $previous_post;
		}
	}

	/**
	 * Get the expected form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'register',
		];
	}
}
