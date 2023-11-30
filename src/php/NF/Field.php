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

use NF_Abstracts_Field;

/**
 * Class Field
 */
class Field extends NF_Abstracts_Field {

	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Name.
	 *
	 * @var string
	 */
	protected $_name = 'hcaptcha-for-ninja-forms';

	/**
	 * Type.
	 *
	 * @var string
	 */
	protected $_type = 'hcaptcha';

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
	protected $_icon = '';

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

		$this->_nicename = __( 'hCaptcha', 'ninja-forms' );
	}

	/**
	 * Validate form.
	 *
	 * @param array|mixed $field Field.
	 * @param mixed       $data  Data.
	 *
	 * @return null|string
	 */
	public function validate( $field, $data ) {
		$value = $field['value'] ?? '';

		return hcaptcha_request_verify( $value );
	}
}
