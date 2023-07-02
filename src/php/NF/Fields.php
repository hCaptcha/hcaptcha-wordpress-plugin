<?php
/**
 * Fields class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\NF;

use NF_Fields_Recaptcha;

/**
 * Class Fields
 */
class Fields extends NF_Fields_recaptcha {

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
	 * Templates.
	 *
	 * @var string
	 */
	protected $_templates = 'hcaptcha';

	/**
	 * Nice name of the field.
	 *
	 * @var string
	 */
	protected $_nicename;

	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Fields constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->_nicename = __( 'hCaptcha', 'ninja-forms' );
	}

	/**
	 * Validate form.
	 *
	 * @param array $field Field.
	 * @param mixed $data  Data.
	 *
	 * @return null|string
	 */
	public function validate( $field, $data ) {
		$value = isset( $field['value'] ) ? $field['value'] : '';

		return hcaptcha_request_verify( $value );
	}
}
