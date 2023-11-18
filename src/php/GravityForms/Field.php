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
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Field.
 */
class Field extends GF_Field {

	/**
	 * Field type.
	 *
	 * @var string
	 */
	public $type = 'hcaptcha';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	private function init() {
		try {
			GF_Fields::register( $this );
		} catch ( Exception $e ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'gform_field_groups_form_editor', [ $this, 'add_to_field_groups' ] );
	}

	/**
	 * Add hCaptcha field to field groups.
	 *
	 * @param array $field_groups Field groups.
	 *
	 * @return array
	 */
	public function add_to_field_groups( array $field_groups ): array {
		$field_groups['advanced_fields']['fields'][] = [
			'data-type' => 'hcaptcha',
			'value'     => 'hCaptcha',
		];

		return $field_groups;
	}

	/**
	 * Get form editor field title.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title(): string {
		return esc_attr( 'hCaptcha' );
	}

	/**
	 * Returns the field's form editor description.
	 *
	 * @return string
	 */
	public function get_form_editor_field_description(): string {
		return esc_attr__(
			'Adds a hCaptcha field to your form to help protect your website from spam and bot abuse.',
			'hcaptcha-for-forms-and-more'
		);
	}

	/**
	 * Returns the field's form editor icon.
	 *
	 * This could be an icon url or a gform-icon class.
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon(): string {
		return HCAPTCHA_URL . '/assets/images/hcaptcha-icon-black-and-white.svg';
	}

	/**
	 * Get field settings.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings(): array {
		return [];
	}

	/**
	 * Get field input.
	 *
	 * @param array $form  Form.
	 * @param mixed $value Value.
	 * @param mixed $entry Entry.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ): string {
		$form_id         = (int) $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$id              = $this->id;
		$field_id        = $is_entry_detail || $is_form_editor || 0 === $form_id ? "input_$id" : 'input_' . $form_id . "_$id";
		$hcaptcha_size   = hcaptcha()->settings()->get( 'size' );
		$tabindex        = GFCommon::$tab_index > 0 ? GFCommon::$tab_index++ : 0;
		$tabindex        = 'invisible' === $hcaptcha_size ? -1 : $tabindex;
		$search          = 'class="h-captcha"';

		$args = [
			'action' => Form::ACTION,
			'name'   => Form::NONCE,
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
}
