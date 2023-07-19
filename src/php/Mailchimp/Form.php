<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Mailchimp;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_mailchimp';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_mailchimp_nonce';

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
		add_filter( 'mc4wp_form_messages', [ $this, 'add_hcap_error_messages' ], 10, 2 );
		add_action( 'mc4wp_form_content', [ $this, 'add_captcha' ], 20, 3 );
		add_filter( 'mc4wp_form_errors', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add hcaptcha error messages to MailChimp.
	 *
	 * @param array      $messages Messages.
	 * @param MC4WP_Form $form     Form.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcap_error_messages( array $messages, MC4WP_Form $form ): array {
		foreach ( hcap_get_error_messages() as $error_code => $error_message ) {
			$messages[ $error_code ] = [
				'type' => 'error',
				'text' => $error_message,
			];
		}

		return $messages;
	}

	/**
	 * Add hcaptcha to MailChimp form.
	 *
	 * @param string             $content Content.
	 * @param MC4WP_Form         $form    Form.
	 * @param MC4WP_Form_Element $element Element.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( string $content, MC4WP_Form $form, MC4WP_Form_Element $element ): string {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form->ID,
			],
		];

		return str_replace(
			'<input type="submit"',
			HCaptcha::form( $args ) .
			'<input type="submit"',
			$content
		);
	}

	/**
	 * Verify MailChimp captcha.
	 *
	 * @param array      $errors Errors.
	 * @param MC4WP_Form $form   Form.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( array $errors, MC4WP_Form $form ): array {
		$error_message = hcaptcha_verify_post( self::NAME, self::ACTION );

		if ( null !== $error_message ) {
			$error_code = array_search( $error_message, hcap_get_error_messages(), true );
			$error_code = $error_code ?: 'empty';
			$errors[]   = $error_code;
		}

		return $errors;
	}
}
