<?php
add_filter('ninja_forms_register_fields', function($fields){
    require_once(plugin_dir_path( __FILE__ ).'class-nf-hcaptcha.php');
    $fields['hcaptcha-for-ninja-forms'] = new HCaptchaFieldsForNF();

    return $fields;
});
add_filter( 'ninja_forms_field_template_file_paths', 'hcap_nf_template_file_paths' );
function hcap_nf_template_file_paths( $paths ){
    $paths[] = dirname( __FILE__ ).'/templates/';
    
    return $paths;
}
add_filter( 'ninja_forms_localize_field_hcaptcha-for-ninja-forms', function( $field ){

$field[ 'settings' ][ 'hcaptcha_key' ]      = get_option('hcaptcha_api_key' );
$field[ 'settings' ][ 'hcaptcha_theme' ] 	= get_option("hcaptcha_theme");
$field[ 'settings' ][ 'hcaptcha_size' ] 	= get_option("hcaptcha_size");

return $field;
}, 10, 1);
wp_enqueue_script( 'nf-hcaptcha-js', plugin_dir_url( __FILE__ ) . 'nf-hcaptcha.js', array('nf-front-end' ), '1.0.0' );  