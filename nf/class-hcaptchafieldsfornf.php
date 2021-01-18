<?php
/**
 * Ninja Forms class file.
 *
 * @package hcaptcha-wp
 */

/**
 * Class HCaptchaFieldsForNF
 */
class HCaptchaFieldsForNF extends NF_Fields_recaptcha {

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
	protected $_icon = 'filter';

	/**
	 * Templates.
	 *
	 * @var string
	 */
	protected $_templates = 'hcaptcha';

	/**
	 * Test value.
	 *
	 * @var string
	 */
	protected $_test_value = '';

	/**
	 * Settings.
	 *
	 * @var string[]
	 */
	protected $_settings = array( 'label', 'classes' );

	/**
	 * HCaptchaFieldsForNF constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->_nicename = __( 'hCaptcha', 'ninja-forms' );
		add_filter( 'nf_sub_hidden_field_types', array( $this, 'hide_field_type' ) );
	}

	/**
	 * Validate form.
	 *
	 * @param array $field Field.
	 * @param mixed $data  Data.
	 *
	 * @return array|mixed|string|void
	 */
	public function validate( $field, $data ) {
		if ( empty( $field['value'] ) ) {
			return __( 'Please complete the captcha.', 'hcaptcha-for-forms-and-more' );
		}

		$result = hcaptcha_request_verify( $field['value'] );
		if ( 'fail' === $result ) {
			return array( __( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ) );
		}

	}

	/**
	 * Hide hCaptcha field type.
	 *
	 * @param array $field_types Field types.
	 *
	 * @return mixed
	 */
	public function hide_field_type( $field_types ) {
		$field_types[] = $this->_name;

		return $field_types;
	}
}
