<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class NF_HCaptchaForNinjaForms_Fields_HCaptchaField
 */
class HCaptchaFieldsForNF extends NF_Fields_recaptcha
{
    protected $_name = 'hcaptcha-for-ninja-forms';

    protected $_type = 'hcaptcha';

    protected $_section = 'misc';

    protected $_icon = 'filter';

    protected $_templates = 'hcaptcha';

    protected $_test_value = '';

    protected $_settings = array( 'label', 'classes' );

    public function __construct()
    {
        parent::__construct();

        $this->_nicename = __( 'hCaptcha', 'ninja-forms' );
        add_filter( 'nf_sub_hidden_field_types', array( $this, 'hide_field_type' ) );
    }

    public function validate( $field, $data ) {
	    if ( empty( $field['value'] ) ) {
		    return __( 'Please complete the captcha.', 'hcaptcha-wp' );
	    }

	    $result = hcaptcha_request_verify( $field['value'] );
	    if ( $result === 'fail' ) {
		    return array( __( 'The Captcha is invalid.', 'hcaptcha-wp' ) );
	    }

    }

    function hide_field_type( $field_types )
    {
        $field_types[] = $this->_name;
        return $field_types;
    }
}
