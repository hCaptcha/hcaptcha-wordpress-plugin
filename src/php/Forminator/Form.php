<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Forminator;

use Quform_Element_Page;
use Quform_Form;

/**
 * Class Form.
 */
class Form {

	/**
	 * Verify action.
	 */
	const ACTION = 'hcaptcha_forminator';

	/**
	 * Verify nonce.
	 */
	const NONCE = 'hcaptcha_forminator_nonce';

	/**
	 * Quform constructor.
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
		add_action( 'forminator_render_button_markup', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'forminator_cform_form_is_submittable', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string $html   Shortcode output.
	 * @param string $button Shortcode name.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $html, $button ) {
		$hcaptcha = hcap_form( self::ACTION, self::NONCE );

		return str_replace( '<button ', $hcaptcha . '<button ', $html );
	}

	/**
	 * Verify.
	 *
	 * @param array $can_show      Can show the form.
	 * @param int   $id            Form id.
	 * @param array $form_settings Form settings.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $can_show, $id, $form_settings ) {
		$error_message = hcaptcha_get_verify_message( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			return [
				'can_submit' => false,
				'error'      => $error_message,
			];
		}

		return $can_show;
	}
}
