<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Mailchimp;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
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
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-mailchimp';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaMailchimpObject';

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
		add_action( 'wp_print_footer_scripts', [ $this, 'preview_scripts' ], 9 );
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
			$name  = self::NAME;
			$value = wp_create_nonce( self::ACTION );

			// Force nonce name.
			return preg_replace( '/id=".+?" name=".+?" value=".+?"/', "id=\"$name\" name=\"$name\" value=\"$value\"", $content );
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
		// Do not allow modification of the nonce field in the shortcode.
		// During preview, we cannot recalculate the nonce field.
		$error_message = API::verify_post( self::NAME, self::ACTION );

		if ( null !== $error_message ) {
			$error_code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'empty';
			$errors     = (array) $errors;
			$errors[]   = $error_code;
		}

		return $errors;
	}

	/**
	 * Enqueue a script in admin to preview the form.
	 *
	 * @return void
	 */
	public function preview_scripts(): void {
		$form_id = (int) Request::filter_input( INPUT_GET, 'mc4wp_preview_form' );

		if ( ! $form_id ) {
			return;
		}

		$min = hcap_min_suffix();
		$id  = [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $form_id,
		];

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/admin-mailchimp$min.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'action'     => self::ACTION,
				'name'       => self::NAME,
				'nonceField' => wp_nonce_field( self::ACTION, self::NAME, true, false ),
				'widget'     => HCaptcha::get_widget( $id ),
			]
		);
	}
}
