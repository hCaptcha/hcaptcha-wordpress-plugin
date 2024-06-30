<?php
/**
 * NFTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\NF\Field;
use HCaptcha\NF\NF;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test ninja-forms-hcaptcha.php file.
 *
 * Ninja Forms requires PHP 7.2.
 *
 * @requires PHP <= 8.2
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
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'nf_captcha_script' ] ) );
	}

	/**
	 * Test register_fields.
	 */
	public function test_register_fields() {
		$fields = [ 'some field' ];

		$fields = ( new NF() )->register_fields( $fields );

		self::assertInstanceOf( Field::class, $fields['hcaptcha-for-ninja-forms'] );
	}

	/**
	 * Test template_file_paths().
	 */
	public function test_template_file_paths() {
		$paths    = [ 'some path' ];
		$expected = array_merge( $paths, [ str_replace( '\\', '/', HCAPTCHA_PATH . '/src/php/NF/templates/' ) ] );

		$paths = ( new NF() )->template_file_paths( $paths );
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
		$form_id  = 1;
		$field_id = 5;
		$field    = [
			'id'       => $field_id,
			'settings' => [],
		];

		$hcaptcha_site_key = 'some key';
		$hcaptcha_theme    = 'some theme';
		$hcaptcha_size     = 'some size';
		$uniqid            = 'hcaptcha-nf-625d3b9b318fc0.86180601';

		update_option(
			'hcaptcha_settings',
			[
				'site_key' => $hcaptcha_site_key,
				'theme'    => $hcaptcha_theme,
				'size'     => $hcaptcha_size,
			]
		);

		hcaptcha()->init_hooks();

		FunctionMocker::replace(
			'uniqid',
			static function ( $prefix, $more_entropy ) use ( $uniqid ) {
				if ( 'hcaptcha-nf-' === $prefix && $more_entropy ) {
					return $uniqid;
				}

				return null;
			}
		);

		$expected                         = $field;
		$hcap_widget                      = $this->get_hcap_widget(
			[
				'source'  => [ 'ninja-forms/ninja-forms.php' ],
				'form_id' => $form_id,
			]
		);
		$expected['settings']['hcaptcha'] =
			$hcap_widget . "\n" . '				<div id="' . $uniqid . '" data-fieldId="' . $field_id . '"
				class="h-captcha"
				data-sitekey="some key"
				data-theme="some theme"
				data-size="some size"
				data-auto="false"
				data-force="false">
		</div>
		';

		$subject = new NF();
		$subject->set_form_id( $form_id );

		self::assertSame( $expected, $subject->localize_field( $field ) );
	}

	/**
	 * Test nf_captcha_script().
	 */
	public function test_nf_captcha_script() {
		$subject = new NF();

		$subject->nf_captcha_script();

		self::assertTrue( wp_script_is( 'hcaptcha-nf' ) );
	}
}
