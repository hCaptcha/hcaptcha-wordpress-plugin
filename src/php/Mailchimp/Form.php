<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Mailchimp;

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
		add_filter( 'mc4wp_valid_form_request', [ $this, 'verify' ], 10, 2 );
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
	public function add_hcap_error_messages( $messages, $form ) {
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
	 * @param string $content Content.
	 * @param string $form    Form.
	 * @param string $element Element.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $content, $form, $element ) {
		return str_replace(
			'<input type="submit"',
			hcap_form( self::ACTION, self::NAME ) .
			'<input type="submit"',
			$content
		);
	}

	/**
	 * Verify MailChimp captcha.
	 *
	 * @param bool  $valid Whether request is valid.
	 * @param array $data  Form data.
	 *
	 * @return null|string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $valid, $data ) {
		$error_message = hcaptcha_verify_post( self::NAME, self::ACTION );
		$error_message = preg_replace( '/(.+: )?/', '', $error_message );
		$error_code    = false;

		if ( null !== $error_message ) {
			$error_code = array_search( $error_message, hcap_get_error_messages(), true );
		}

		return $error_code ?: $valid;
	}
}
