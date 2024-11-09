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
use MC4WP_Form;
use MC4WP_Form_Element;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_mailchimp';

	/**
	 * Nonce name.
	 */
	private const NAME = 'hcaptcha_mailchimp_nonce';

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
		add_filter( 'mc4wp_form_messages', [ $this, 'add_hcap_error_messages' ], 10, 2 );
		add_filter( 'mc4wp_form_content', [ $this, 'add_hcaptcha' ], 20, 3 );
		add_filter( 'mc4wp_form_errors', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add hcaptcha error messages to MailChimp.
	 *
	 * @param array|mixed $messages Messages.
	 * @param MC4WP_Form  $form     Form.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcap_error_messages( $messages, MC4WP_Form $form ): array {
		$messages = (array) $messages;

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
	 * @param string|mixed       $content Content.
	 * @param MC4WP_Form         $form    Form.
	 * @param MC4WP_Form_Element $element Element.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $content, MC4WP_Form $form, MC4WP_Form_Element $element ): string {
		$content = (string) $content;

		if ( false !== strpos( $content, '<h-captcha' ) ) {
			return $content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form->ID,
			],
		];

		return preg_replace(
			'/(<input .*?type="submit")/',
			HCaptcha::form( $args ) . '$1',
			$content
		);
	}

	/**
	 * Verify MailChimp captcha.
	 *
	 * @param array|mixed $errors Errors.
	 * @param MC4WP_Form  $form   Form.
	 *
	 * @return array|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, MC4WP_Form $form ) {
		$content = $form->content ?? '';

		$hcap_shortcode = $this->get_hcap_shortcode( $content );

		if ( $hcap_shortcode ) {
			$hcap_sc           = preg_replace(
				[ '/\s*\[|]\s*/' ],
				[ '' ],
				$hcap_shortcode
			);
			$atts              = shortcode_parse_atts( $hcap_sc );
			$nonce_field_name  = $atts['name'] ?? HCAPTCHA_NONCE;
			$nonce_action_name = $atts  ['action'] ?? HCAPTCHA_ACTION;
		} else {
			$nonce_field_name  = self::NAME;
			$nonce_action_name = self::ACTION;
		}

		$error_message = hcaptcha_verify_post( $nonce_field_name, $nonce_action_name );

		if ( null !== $error_message ) {
			$error_code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'empty';
			$errors     = (array) $errors;
			$errors[]   = $error_code;
		}

		return $errors;
	}

	/**
	 * Get hCaptcha shortcode.
	 *
	 * @param string $content Content.
	 *
	 * @return string
	 */
	private function get_hcap_shortcode( string $content ): string {
		$hcap_sc_regex = get_shortcode_regex( [ 'hcaptcha' ] );

		return preg_match( "/$hcap_sc_regex/", $content, $matches )
			? $matches[0]
			: '';
	}
}
