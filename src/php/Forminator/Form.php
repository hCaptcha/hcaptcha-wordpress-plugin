<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Forminator;

use Forminator_CForm_Front;
use Forminator_Front_Action;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {
	/**
	 * Verify action.
	 */
	private const ACTION = 'hcaptcha_forminator';

	/**
	 * Verify nonce.
	 */
	private const NONCE = 'hcaptcha_forminator_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-forminator';

	/**
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-forminator';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaForminatorObject';

	/**
	 * Form id.
	 *
	 * @var int
	 */
	protected $form_id = 0;

	/**
	 * Form has hCaptcha field.
	 *
	 * @var bool
	 */
	protected $has_hcaptcha_field = false;

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
	public function init_hooks(): void {
		add_action( 'forminator_before_form_render', [ $this, 'before_form_render' ], 10, 5 );
		add_filter( 'forminator_render_button_markup', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'forminator_cform_form_is_submittable', [ $this, 'verify' ], 10, 3 );

		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ], 0 );

		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

		add_filter( 'forminator_field_markup', [ $this, 'replace_hcaptcha_field' ], 10, 3 );
	}

	/**
	 * Get form id before render.
	 *
	 * @param int|mixed $id            Form id.
	 * @param string    $form_type     Form type.
	 * @param int       $post_id       Post id.
	 * @param array     $form_fields   Form fields.
	 * @param array     $form_settings Form settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function before_form_render( $id, string $form_type, int $post_id, array $form_fields, array $form_settings ): void {
		$this->has_hcaptcha_field = $this->has_hcaptcha_field( $form_fields );
		$this->form_id            = $id;
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string|mixed $html   Shortcode output.
	 * @param string       $button Shortcode name.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $html, string $button ) {
		if ( $this->has_hcaptcha_field ) {
			return $html;
		}

		return str_replace( '<button ', $this->get_hcaptcha() . '<button ', (string) $html );
	}

	/**
	 * Verify.
	 *
	 * @param array|mixed $can_show      Can show the form.
	 * @param int         $id            Form id.
	 * @param array       $form_settings Form settings.
	 *
	 * @return array|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $can_show, int $id, array $form_settings ) {
		$module_object = Forminator_Front_Action::$module_object;

		foreach ( $module_object->fields as $key => $field ) {
			if ( isset( $field->raw['captcha_provider'] ) && 'hcaptcha' === $field->raw['captcha_provider'] ) {
				// Remove the hCaptcha field from the form to prevent it from verifying by Forminator.
				unset( $module_object->fields[ $key ] );
				break;
			}
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			return [
				'can_submit' => false,
				'error'      => $error_message,
			];
		}

		return $can_show;
	}

	/**
	 * Filter printed hCaptcha scripts status and return true on Forminator form wizard page.
	 *
	 * @param bool|mixed $status Print scripts status.
	 *
	 * @return bool
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		$forminator_api_handle = 'forminator-hcaptcha';

		wp_dequeue_script( $forminator_api_handle );
		wp_deregister_script( $forminator_api_handle );

		if ( $this->has_hcaptcha_field ) {
			return true;
		}

		return $this->is_forminator_admin_page() || $status;
	}

	/**
	 * Enqueue frontend scripts.
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
			HCAPTCHA_URL . "/assets/js/hcaptcha-forminator$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Enqueue script in admin.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		if ( ! $this->is_forminator_admin_page() ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/admin-forminator$min.js",
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

		wp_enqueue_style(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/admin-forminator$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Replace Forminator hCaptcha field.
	 *
	 * @param string|mixed           $html           Field html.
	 * @param array                  $field          Field.
	 * @param Forminator_CForm_Front $front_instance Forminator_CForm_Front instance.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function replace_hcaptcha_field( $html, array $field, Forminator_CForm_Front $front_instance ) {
		if ( ! $this->is_hcaptcha_field( $field ) ) {
			return $html;
		}

		return $this->get_hcaptcha();
	}

	/**
	 * Get hCaptcha.
	 *
	 * @return string
	 */
	private function get_hcaptcha(): string {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		return HCaptcha::form( $args );
	}

	/**
	 * Whether we are on the Forminator admin pages.
	 *
	 * @return bool
	 */
	protected function is_forminator_admin_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			// @codeCoverageIgnoreStart
			return false;
			// @codeCoverageIgnoreEnd
		}

		$forminator_admin_pages = [
			'forminator_page_forminator-cform',
			'forminator_page_forminator-cform-wizard',
			'forminator_page_forminator-settings',
		];

		return in_array( $screen->id, $forminator_admin_pages, true );
	}

	/**
	 * Whether the field is hCaptcha field.
	 *
	 * @param array $field Field.
	 *
	 * @return bool
	 */
	private function is_hcaptcha_field( array $field ): bool {
		return ( 'captcha' === $field['type'] && 'hcaptcha' === $field['captcha_provider'] );
	}

	/**
	 * Whether form has its own hCaptcha field.
	 *
	 * @param array $form_fields Form fields.
	 *
	 * @return bool
	 */
	private function has_hcaptcha_field( array $form_fields ): bool {
		foreach ( $form_fields as $form_field ) {
			if ( $this->is_hcaptcha_field( $form_field ) ) {
				return true;
			}
		}

		return false;
	}
}
