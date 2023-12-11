<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\FormidableForms;

use FrmAppHelper;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Verify action.
	 */
	const ACTION = 'hcaptcha_formidable_forms';

	/**
	 * Verify nonce.
	 */
	const NONCE = 'hcaptcha_formidable_forms_nonce';

	/**
	 * The hCaptcha field id.
	 *
	 * @var int|string
	 */
	private $hcaptcha_field_id;

	/**
	 * Class constructor.
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
		add_filter( 'transient_frm_options', [ $this, 'get_transient' ], 10, 2 );
		add_filter( 'frm_replace_shortcodes', [ $this, 'add_captcha' ], 10, 3 );
		add_filter( 'frm_is_field_hidden', [ $this, 'prevent_native_validation' ], 10, 3 );
		add_filter( 'frm_validate_entry', [ $this, 'verify' ], 10, 3 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Use this plugin settings for hcaptcha in Formidable Forms.
	 *
	 * @param mixed  $value     Value of transient.
	 * @param string $transient Transient name.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_transient( $value, string $transient ) {
		if (
			! $value ||
			( isset( $value->active_captcha ) && 'hcaptcha' !== $value->active_captcha )
		) {
			return $value;
		}

		$settings                = hcaptcha()->settings();
		$value->hcaptcha_pubkey  = $settings->get_site_key();
		$value->hcaptcha_privkey = $settings->get_secret_key();

		return $value;
	}

	/**
	 * Filter field html created and add hcaptcha.
	 *
	 * @param string|mixed $html  Html code of the field.
	 * @param array        $field Field.
	 * @param array        $atts  Attributes.
	 *
	 * @return string|mixed
	 */
	public function add_captcha( $html, array $field, array $atts ) {
		if ( 'captcha' !== $field['type'] ) {
			return $html;
		}

		$frm_settings = FrmAppHelper::get_settings();

		if ( 'recaptcha' === $frm_settings->active_captcha ) {
			return $html;
		}

		// <div id="field_5l59" class="h-captcha" data-sitekey="ead4f33b-cd8a-49fb-aa16-51683d9cffc8"></div>

		if ( ! preg_match( '#<div id="(.+)" class="h-captcha" .+></div>#', (string) $html, $m ) ) {
			return $html;
		}

		list( $captcha_div, $div_id ) = $m;

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => (int) $atts['form']->id,
			],
		];

		$class = 'class="h-captcha"';
		$form  = str_replace( $class, 'id="' . $div_id . '"' . $class, HCaptcha::form( $args ) );

		return str_replace( $captcha_div, $form, (string) $html );
	}

	/**
	 * Prevent native validation.
	 *
	 * @param bool|mixed $is_field_hidden Whether the field is hidden.
	 * @param stdClass   $field           Field.
	 * @param array      $post            wp_unslash( $_POST ) content.
	 *
	 * @return bool|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_native_validation( $is_field_hidden, stdClass $field, array $post ): bool {
		if ( 'captcha' !== $field->type ) {
			return $is_field_hidden;
		}

		$frm_settings = FrmAppHelper::get_settings();

		if ( 'recaptcha' === $frm_settings->active_captcha ) {
			return $is_field_hidden;
		}

		$this->hcaptcha_field_id = $field->id;

		// Prevent validation of hCaptcha in Formidable Forms.
		return true;
	}

	/**
	 * Verify.
	 *
	 * @param array|mixed $errors        Errors data.
	 * @param array       $values        Value data of the form.
	 * @param array       $validate_args Custom arguments. Contains `exclude` and `posted_fields`.
	 *
	 * @return array|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, array $values, array $validate_args ) {
		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $errors;
		}

		$errors = (array) $errors;

		$field_id                      = $this->hcaptcha_field_id ?: 1;
		$errors[ 'field' . $field_id ] = $error_message;

		return $errors;
	}

	/**
	 * Dequeue hCaptcha script by Formidable Forms.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_dequeue_script( 'captcha-api' );
		wp_deregister_script( 'captcha-api' );
	}
}
