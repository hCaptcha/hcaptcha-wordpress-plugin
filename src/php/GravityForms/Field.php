<?php
/**
 * Field class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GravityForms;

use GF_Field;
use GF_Fields;
use Exception;
use GFCommon;
use GFForms;
use GFFormsModel;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Field.
 */
class Field extends GF_Field {

	/**
	 * Dialog scripts and style handle.
	 */
	public const DIALOG_HANDLE = 'kagg-dialog';

	/**
	 * Admin script handle.
	 */
	public const ADMIN_HANDLE = 'admin-gravity-forms';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaGravityFormsObject';

	/**
	 * Editor screen id.
	 */
	public const EDITOR_SCREEN_ID = 'toplevel_page_gf_edit_forms';

	/**
	 * Settings screen id.
	 */
	public const SETTINGS_SCREEN_ID = 'forms_page_gf_settings';

	/**
	 * Field type.
	 *
	 * @var string
	 */
	public $type = 'hcaptcha';

	/**
	 * Constructor.
	 *
	 * @param array $data Data.
	 */
	public function __construct( $data = [] ) {
		parent::__construct( $data );

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	private function init(): void {
		if ( ! hcaptcha()->settings()->is( 'gravity_status', 'embed' ) ) {
			return;
		}

		$this->label = 'hCaptcha';

		try {
			GF_Fields::register( $this );
			// @codeCoverageIgnoreStart
		} catch ( Exception $e ) {
			return;
			// @codeCoverageIgnoreEnd
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_filter( 'gform_field_groups_form_editor', [ $this, 'add_to_field_groups' ] );
		add_filter( 'gform_duplicate_field_link', [ $this, 'disable_duplication' ] );
		add_action( 'admin_print_footer_scripts-' . self::EDITOR_SCREEN_ID, [ $this, 'enqueue_admin_script' ] );
		add_action( 'admin_print_footer_scripts-' . self::SETTINGS_SCREEN_ID, [ $this, 'enqueue_admin_script' ] );
		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ], 0 );
	}

	/**
	 * Add hCaptcha field to field groups.
	 *
	 * @param array $field_groups Field groups.
	 *
	 * @return array
	 */
	public function add_to_field_groups( array $field_groups ): array {
		$advanced_fields = $field_groups['advanced_fields']['fields'] ?? [];
		$index           = array_search( 'captcha', array_column( $advanced_fields, 'data-type' ), true );

		if ( false === $index ) {
			return $field_groups;
		}

		$advanced_fields = array_merge(
			array_slice( $advanced_fields, 0, $index ),
			[
				[
					'data-type' => 'hcaptcha',
					'value'     => 'hCaptcha',
				],
			],
			array_slice( $advanced_fields, $index )
		);

		$field_groups['advanced_fields']['fields'] = $advanced_fields;

		return $field_groups;
	}

	/**
	 * Get form editor field title.
	 *
	 * @return string
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function get_form_editor_field_title() {
		return esc_attr( 'hCaptcha' );
	}

	/**
	 * Returns the field's form editor description.
	 *
	 * @return string
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function get_form_editor_field_description() {
		return (
			esc_attr__(
				'Adds a hCaptcha field to your form to help protect your website from spam and bot abuse.',
				'hcaptcha-for-forms-and-more'
			) .
			' ' .
			esc_attr__(
				'hCaptcha settings must be modified on the hCaptcha plugin General settings page.',
				'hcaptcha-for-forms-and-more'
			)
		);
	}

	/**
	 * Returns the field's form editor icon.
	 *
	 * This could be an icon url or a gform-icon class.
	 *
	 * @return string
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function get_form_editor_field_icon() {
		return HCAPTCHA_URL . '/assets/images/hcaptcha-icon-black-and-white.svg';
	}

	/**
	 * Get field settings.
	 *
	 * @return array
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function get_form_editor_field_settings() {
		return [
			'label_placement_setting',
			'description_setting',
			'css_class_setting',
		];
	}

	/**
	 * Get field input.
	 *
	 * @param array $form  Form.
	 * @param mixed $value Value.
	 * @param mixed $entry Entry.
	 *
	 * @return string
	 * @noinspection PhpCastIsUnnecessaryInspection
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = (int) $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$id              = (int) $this->id;
		$field_id        = $is_entry_detail || $is_form_editor || 0 === $form_id ? "input_$id" : 'input_' . $form_id . "_$id";
		$hcaptcha_size   = hcaptcha()->settings()->get( 'size' );
		$tabindex        = GFCommon::$tab_index > 0 ? GFCommon::$tab_index++ : 0;
		$tabindex        = 'invisible' === $hcaptcha_size ? -1 : $tabindex;
		$search          = 'class="h-captcha"';

		$args = [
			'action' => Base::ACTION,
			'name'   => Base::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];

		return str_replace(
			$search,
			$search . ' id="' . $field_id . '" data-tabindex="' . $tabindex . '"',
			HCaptcha::form( $args )
		);
	}

	/**
	 * Disable hCaptcha field duplication.
	 *
	 * @param string $duplicate_field_link Duplicate link.
	 *
	 * @return string
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function disable_duplication( string $duplicate_field_link ): string {
		$action = rgpost( 'action' );

		if ( 'rg_add_field' === $action ) {
			$field = json_decode( rgpost( 'field' ), false );
		} else {
			if ( ! preg_match( "/id='gfield_duplicate_(.*)?'/", $duplicate_field_link, $m ) ) {
				return $duplicate_field_link;
			}

			$form_id  = GFForms::get( 'id' );
			$field_id = $m[1];
			$field    = GFFormsModel::get_field( $form_id, $field_id );
		}

		$type = $field->type ?? '';

		return 'hcaptcha' === $type ? '' : $duplicate_field_link;
	}

	/**
	 * Enqueue admin script.
	 *
	 * @return void
	 */
	public function enqueue_admin_script(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/kagg-dialog$min.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/kagg-dialog$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			HCAPTCHA_URL . "/assets/js/admin-gravity-forms$min.js",
			[ 'jquery', 'hcaptcha', self::DIALOG_HANDLE ],
			HCAPTCHA_VERSION,
			true
		);

		$notice = HCaptcha::get_hcaptcha_plugin_notice();

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'onlyOne'           => __( 'Only one hCaptcha field can be added to the form.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText'         => __( 'OK', 'hcaptcha-for-forms-and-more' ),
				'noticeLabel'       => $notice['label'],
				'noticeDescription' => $notice['description'],
			]
		);

		wp_enqueue_style(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/admin-gravity-forms$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Print hCaptcha script on form edit page.
	 *
	 * @param bool|mixed $status Current print status.
	 *
	 * @return bool
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		$status = (bool) $status;

		if ( ! function_exists( 'get_current_screen' ) ) {
			// @codeCoverageIgnoreStart
			return $status;
			// @codeCoverageIgnoreEnd
		}

		$screen    = get_current_screen();
		$screen_id = $screen->id ?? '';

		if ( self::EDITOR_SCREEN_ID === $screen_id ) {
			return true;
		}

		return $status;
	}
}
