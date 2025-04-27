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
use FrmSettings;
use HCaptcha\Helpers\HCaptcha;
use stdClass;

/**
 * Class Form.
 */
class Form {

	/**
	 * Verify action.
	 */
	private const ACTION = 'hcaptcha_formidable_forms';

	/**
	 * Verify nonce.
	 */
	private const NONCE = 'hcaptcha_formidable_forms_nonce';

	/**
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-formidable-forms';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaFormidableFormsObject';

	/**
	 * The hCaptcha field id.
	 *
	 * @var int|string
	 */
	protected $hcaptcha_field_id;

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
	public function init_hooks(): void {
		add_filter( 'option_frm_options', [ $this, 'get_option' ], 10, 2 );
		add_filter( 'frm_replace_shortcodes', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_filter( 'frm_is_field_hidden', [ $this, 'prevent_native_validation' ], 20, 3 );
		add_filter( 'frm_validate_entry', [ $this, 'verify' ], 10, 3 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Use this plugin settings for hCaptcha in Formidable Forms.
	 *
	 * @param mixed|FrmSettings $value  Value of option.
	 * @param string            $option Option name.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_option( $value, string $option ) {
		if (
			! $value ||
			! is_a( $value, FrmSettings::class ) ||
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
	 * Filter field HTML created and add hCaptcha.
	 *
	 * @param string|mixed $html  HTML code of the field.
	 * @param array        $field Field.
	 * @param array        $atts  Attributes.
	 *
	 * @return string|mixed
	 */
	public function add_hcaptcha( $html, array $field, array $atts ) {
		if ( 'captcha' !== $field['type'] ) {
			return $html;
		}

		$frm_settings = FrmAppHelper::get_settings();

		if ( 'hcaptcha' !== $frm_settings->active_captcha ) {
			return $html;
		}

		if ( ! preg_match( '#<div\s+id="(.+)"\s+class="h-captcha" .+></div>#', (string) $html, $m ) ) {
			return $html;
		}

		[ $captcha_div, $div_id ] = $m;

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
	public function enqueue_scripts(): void {
		wp_dequeue_script( 'captcha-api' );
		wp_deregister_script( 'captcha-api' );
	}

	/**
	 * Enqueue script in admin.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		if ( ! $this->is_formidable_forms_admin_page() ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/admin-formidable-forms$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		$notice = HCaptcha::get_hcaptcha_plugin_notice();

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'noticeLabel'       => $notice['label'],
				'noticeDescription' => $notice['description'],
			]
		);
	}

	/**
	 * Whether we are on the Formidable Forms admin pages.
	 *
	 * @return bool
	 */
	protected function is_formidable_forms_admin_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			// @codeCoverageIgnoreStart
			return false;
			// @codeCoverageIgnoreEnd
		}

		$formidable_forms_admin_pages = [
			'formidable_page_formidable-settings',
		];

		return in_array( $screen->id, $formidable_forms_admin_pages, true );
	}
}
