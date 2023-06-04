<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Forminator;

use HCaptcha\Helpers\HCaptcha;
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
	 * Form id.
	 *
	 * @var int
	 */
	private $form_id = 0;

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
		add_action( 'forminator_before_form_render', [ $this, 'before_form_render' ], 10, 5 );
		add_action( 'forminator_render_button_markup', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'forminator_cform_form_is_submittable', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Get form id before render.
	 *
	 * @param int    $id            Form id.
	 * @param string $form_type     Form type.
	 * @param int    $post_id       Post id.
	 * @param array  $form_fields   Form fields.
	 * @param array  $form_settings Form settings.
	 *
	 * @return int
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function before_form_render( $id, $form_type, $post_id, $form_fields, $form_settings ) {
		$this->form_id = $id;

		return $id;
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
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		$hcaptcha = HCaptcha::form( $args );

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
