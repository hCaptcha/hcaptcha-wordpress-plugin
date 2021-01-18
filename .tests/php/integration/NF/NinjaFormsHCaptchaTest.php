<?php
/**
 * NinjaFormsHCaptchaTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test ninja-forms-hcaptcha.php file.
 */
class NinjaFormsHCaptchaTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

	/**
	 * Test ninja_forms_register_fields filter.
	 */
	public function test_ninja_forms_register_fields_filter() {
		$fields = [ 'some field' ];

		$fields = hcap_ninja_forms_register_fields( $fields );

		self::assertInstanceOf( 'HCaptchaFieldsForNF', $fields['hcaptcha-for-ninja-forms'] );
	}

	/**
	 * Test hcap_nf_template_file_paths().
	 */
	public function test_hcap_nf_template_file_paths() {
		$paths    = [ 'some path' ];
		$expected = array_merge( $paths, [ str_replace( '\\', '/', HCAPTCHA_PATH . '/nf/templates/' ) ] );

		$paths = hcap_nf_template_file_paths( $paths );
		array_walk(
			$paths,
			function ( &$item ) {
				$item = str_replace( '\\', '/', $item );
			}
		);

		self::assertSame( $expected, $paths );
	}

	/**
	 * Test ninja_forms_localize_field_hcaptcha_for_ninja_forms_filter().
	 */
	public function test_ninja_forms_localize_field_hcaptcha_for_ninja_forms_filter() {
		$field = [ 'some' ];

		$hcaptcha_key   = 'some key';
		$hcaptcha_theme = 'some theme';
		$hcaptcha_size  = 'some size';
		$nonce          = wp_nonce_field(
			'hcaptcha_nf',
			'hcaptcha_nf_nonce',
			true,
			false
		);

		update_option( 'hcaptcha_api_key', $hcaptcha_key );
		update_option( 'hcaptcha_theme', $hcaptcha_theme );
		update_option( 'hcaptcha_size', $hcaptcha_size );

		$expected = $field;

		$expected['settings']['hcaptcha_key']         = $hcaptcha_key;
		$expected['settings']['hcaptcha_theme']       = $hcaptcha_theme;
		$expected['settings']['hcaptcha_size']        = $hcaptcha_size;
		$expected['settings']['hcaptcha_nonce_field'] = $nonce;

		self::assertSame( $expected, ninja_forms_localize_field_hcaptcha_for_ninja_forms_filter( $field ) );
	}

	/**
	 * Test hcap_nf_captcha_script().
	 */
	public function test_hcap_nf_captcha_script() {
		$expected = 'setTimeout(function(){window.hcaptcha.render("nf-hcaptcha")}, 1000);';

		hcap_nf_captcha_script();

		self::assertTrue( wp_script_is( 'nf-hcaptcha-js', 'enqueued' ) );
		self::assertSame( $expected, $GLOBALS['wp_scripts']->registered['nf-hcaptcha-js']->extra['after'][1] );
	}
}
