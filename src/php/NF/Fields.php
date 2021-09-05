<?php
/**
 * Fields class file.
 *
 * @package hcaptcha-wp
 */

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
}
