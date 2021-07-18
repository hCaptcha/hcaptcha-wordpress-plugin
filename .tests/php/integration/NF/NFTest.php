<?php
/**
 * NFTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\NF\Fields;
use HCaptcha\NF\NF;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test ninja-forms-hcaptcha.php file.
 *
 * Cannot activate Ninja Forms plugin with php 8.0
 * due to some bug with uksort() in \Ninja_Forms::plugins_loaded()
 * caused by antecedent/patchwork.
 *
 * @requires PHP < 8.0
 */
class NFTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks() {
		$subject = new NF();

		self::assertSame(
			10,
			has_filter( 'ninja_forms_register_fields', [ $subject, 'register_fields' ] )
		);
		self::assertSame(
			10,
			has_filter( 'ninja_forms_field_template_file_paths', [ $subject, 'template_file_paths' ] )
		);
		self::assertSame(
			10,
			has_filter( 'ninja_forms_localize_field_hcaptcha-for-ninja-forms', [ $subject, 'localize_field' ] )
		);
		self::assertSame( 10, has_action( 'wp_enqueue_scripts', [ $subject, 'nf_captcha_script' ] ) );
	}

	/**
	 * Test register_fields.
	 */
	public function test_register_fields() {
		$fields = [ 'some field' ];

		$subject = new NF();

		$fields = $subject->register_fields( $fields );

		self::assertInstanceOf( Fields::class, $fields['hcaptcha-for-ninja-forms'] );
	}

	/**
	 * Test template_file_paths().
	 */
	public function test_template_file_paths() {
		$paths    = [ 'some path' ];
		$expected = array_merge( $paths, [ str_replace( '\\', '/', HCAPTCHA_PATH . '/src/php/NF/templates/' ) ] );

		$subject = new NF();

		$paths = $subject->template_file_paths( $paths );
		array_walk(
			$paths,
			static function ( &$item ) {
				$item = str_replace( '\\', '/', $item );
			}
		);

		self::assertSame( $expected, $paths );
	}

	/**
	 * Test localize_field().
	 */
	public function test_localize_field() {
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

		$subject = new NF();

		self::assertSame( $expected, $subject->localize_field( $field ) );
	}

	/**
	 * Test nf_captcha_script().
	 */
	public function test_nf_captcha_script() {
		$expected = 'setTimeout(function(){window.hcaptcha.render("nf-hcaptcha")}, 1000);';

		$subject = new NF();

		$subject->nf_captcha_script();

		self::assertTrue( wp_script_is( 'nf-hcaptcha', 'enqueued' ) );
		self::assertSame( $expected, $GLOBALS['wp_scripts']->registered['nf-hcaptcha']->extra['after'][1] );
	}
}
