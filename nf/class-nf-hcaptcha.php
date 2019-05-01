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
            return __( 'Please complete the captcha', 'ninja-forms' );
        }

        $secret_key = get_option('hcaptcha_secret_key');
        $url = 'https://hcaptcha.com/siteverify?secret=' . $secret_key . '&response='.sanitize_text_field( $field['value'] );
        $resp = wp_remote_get( esc_url_raw( $url ) );

        if ( !is_wp_error( $resp ) ) {
            $body = wp_remote_retrieve_body( $resp );
            $response = json_decode( $body );
            if ( $response->success === false ) {
                return array( __( 'Captcha mismatch. Please enter the correct value in captcha field', 'nf-hcaptcha' ) );
            }
        }
    }

    function hide_field_type( $field_types )
    {
        $field_types[] = $this->_name;
        return $field_types;
    }
}
