<?php
/**
 * Field class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnused */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\NF;

use HCaptcha\Helpers\API;
use NF_Abstracts_Field;

/**
 * Class Field
 */
class Field extends NF_Abstracts_Field implements Base {

	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Field name.
	 *
	 * @var string
	 */
	protected $_name = self::NAME;

	/**
	 * Filed type.
	 *
	 * @var string
	 */
	protected $_type = self::TYPE;

	/**
	 * Section.
	 *
	 * @var string
	 */
	protected $_section = 'misc';

	/**
	 * Icon.
	 *
	 * @var string
	 */
	protected $_icon = 'hand-paper-o';

	/**
	 * Templates.
	 *
	 * @var string
	 */
	protected $_templates = 'hcaptcha';

	/**
	 * Settings.
	 *
	 * @var string[]
	 */
	protected $_settings = [ 'label', 'classes' ];

	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Fields constructor.
	 *
	 * @noinspection PhpDynamicFieldDeclarationInspection
	 */
	public function __construct() {
		parent::__construct();

		$this->_nicename = __( 'hCaptcha', 'hcaptcha-for-forms-and-more' );

		add_filter( 'nf_sub_hidden_field_types', [ $this, 'hide_field_type' ] );
	}

	/**
	 * Validate form.
	 *
	 * @param array|mixed $field Field.
	 * @param mixed       $data  Data.
	 *
	 * @return null|string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function validate( $field, $data ): ?string {
		$value = $field['value'] ?? '';

		return API::verify_request( $value );
	}

	/**
	 * Hide the field type.
	 *
	 * @param array|mixed $hidden_field_types Field types.
	 *
	 * @return array
	 */
	public function hide_field_type( $hidden_field_types ): array {
		$hidden_field_types   = (array) $hidden_field_types;
		$hidden_field_types[] = $this->_name;

		return $hidden_field_types;
	}
}
