<?php
/**
 * NFTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\NF\Base;
use HCaptcha\NF\Field;
use HCaptcha\NF\NF;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Ninja_Forms;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the ninja-forms-hcaptcha.php file.
 *
 * @requires PHP >= 7.4
 *
 * @group nf
 */
class NFTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_GET['form_id'] );

		parent::tearDown();
	}

	/**
	 * Test init() and init_hooks().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_and_init_hooks(): void {
		$subject = new NF();

		self::assertSame(
			str_replace( '\\', '/', HCAPTCHA_PATH . '/src/php/NF/templates/' ),
			str_replace( '\\', '/', $this->get_protected_property( $subject, 'templates_dir' ) )
		);

		self::assertSame(
			11,
			has_filter( 'toplevel_page_ninja-forms', [ $subject, 'admin_template' ] )
		);
		self::assertSame(
			10,
			has_filter( 'nf_admin_enqueue_scripts', [ $subject, 'nf_admin_enqueue_scripts' ] )
		);
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
			has_filter( 'nf_get_form_id', [ $subject, 'set_form_id' ] )
		);
		self::assertSame(
			10,
			has_filter( 'ninja_forms_localize_field_hcaptcha-for-ninja-forms', [ $subject, 'localize_field' ] )
		);
		self::assertSame(
			10,
			has_filter( 'ninja_forms_localize_field_hcaptcha-for-ninja-forms_preview', [ $subject, 'localize_field' ] )
		);
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'nf_captcha_script' ] ) );
	}

	/**
	 * Test admin_template().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_template(): void {
		$subject = new NF();

		ob_start();
		$subject->admin_template();
		self::assertSame( '', ob_get_clean() );

		$_GET['form_id'] = 'some';
		$templates_dir   = $this->get_protected_property( $subject, 'templates_dir' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$template = file_get_contents( $templates_dir . 'fields-hcaptcha.html' );
		$expected = str_replace(
			'tmpl-nf-field-' . Base::TYPE,
			'tmpl-nf-field-' . Base::NAME,
			$template
		);

		ob_start();
		$subject->admin_template();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test nf_admin_enqueue_scripts().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_nf_admin_enqueue_scripts(): void {
		global $wp_scripts;

		$subject = new NF();

		$subject->nf_admin_enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-nf' ) );

		$field_id    = 27;
		$hcaptcha_id = 'hcaptcha-nf-625d3b9b318fc0.86180601';
		$form_data   = <<<JSON
{
  "preloadedFormData": {
    "id": 1,
    "fields": [
      {
        "objectType": "Field",
        "objectDomain": "fields",
        "editActive": false,
        "order": 5,
        "idAttribute": "id",
        "type": "hcaptcha-for-ninja-forms",
        "label": "hCaptcha",
        "container_class": "",
        "element_class": "",
        "key": "hcaptcha-for-ninja-forms_1724338923491",
        "drawerDisabled": false,
        "id": "$field_id"
      },
      {
        "objectType": "Field",
        "objectDomain": "fields",
        "editActive": false,
        "order": 6,
        "idAttribute": "id",
        "type": "submit",
        "label": "Submit",
        "processing_label": "Processing",
        "container_class": "",
        "element_class": "",
        "key": "submit_1630502490417",
        "drawerDisabled": false,
        "admin_label": "",
        "id": "7"
      }
    ]
  }
}
JSON;

		$form_id       = 1;
		$form_data_arr = json_decode( $form_data, true );
		$expected      = json_decode( $form_data, true );

		$args = [
			'id' => [
				'source'  => 'ninja-forms/ninja-forms.php',
				'form_id' => $form_id,
			],
		];

		$hcaptcha = $this->get_hcap_form( $args );
		$hcaptcha = str_replace(
			'<h-captcha',
			'<h-captcha id="' . $hcaptcha_id . '" data-fieldId="' . $field_id . '"',
			$hcaptcha
		);
		$search   = 'class="h-captcha"';

		$expected['preloadedFormData']['fields'][0]['hcaptcha'] = str_replace(
			$search,
			$search . ' style="z-index: 2;"',
			$hcaptcha
		);

		wp_register_script(
			'nf-builder',
			'',
			[],
			'1.0',
			false
		);

		wp_localize_script(
			'nf-builder',
			'nfDashInlineVars',
			$form_data_arr
		);

		$this->set_protected_property( $subject, 'form_id', $form_id );

		FunctionMocker::replace( 'uniqid', $hcaptcha_id );

		$subject->nf_admin_enqueue_scripts();

		$data = $wp_scripts->registered['nf-builder']->extra['data'];

		preg_match( '/var nfDashInlineVars = (.+);/', $data, $m );

		self::assertSame( $expected, json_decode( $m[1], true ) );

		self::assertTrue( wp_script_is( 'kagg-dialog' ) );
		self::assertTrue( wp_style_is( 'kagg-dialog' ) );
		self::assertTrue( wp_script_is( 'admin-nf' ) );

		$data = $wp_scripts->registered['admin-nf']->extra['data'];

		preg_match( '/var HCaptchaAdminNFObject = ({.+});/', $data, $m );

		$admin_nf_obj = json_decode( $m[1], true );
		self::assertSame(
			[
				'onlyOne'   => 'Only one hCaptcha field allowed.',
				'OKBtnText' => 'OK',
			],
			$admin_nf_obj
		);
	}

	/**
	 * Test register_fields.
	 */
	public function test_register_fields(): void {
		new NF();

		$nf_instance = Ninja_Forms::instance();

		$nf_instance->instantiateTranslatableObjects();

		$fields = $nf_instance->fields;

		self::assertInstanceOf( Field::class, $fields['hcaptcha-for-ninja-forms'] );

		$hcap_index  = array_search( Base::NAME, array_keys( $fields ), true );
		$recap_index = array_search( 'recaptcha', array_keys( $fields ), true );

		self::assertSame( $recap_index, $hcap_index + 1 );
	}

	/**
	 * Test template_file_paths().
	 */
	public function test_template_file_paths(): void {
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
	 * Test set_form_id().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_set_form_id(): void {
		$form_id = 23;

		$subject = new NF();

		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );

		$subject->set_form_id( $form_id );

		self::assertSame( $form_id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test localize_field().
	 */
	public function test_localize_field(): void {
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

		$hp_name  = 'hcap_hp_test';
		$hp_sig   = wp_create_nonce( $hp_name );
		$hp_field = <<<HTML
		<label for="$hp_name"></label>
		<input
				type="text" id="$hp_name" name="$hp_name" value=""
				readonly inputmode="none" autocomplete="new-password" tabindex="-1" aria-hidden="true"
				style="position:absolute; left:-9999px; top:auto; height:0; width:0; opacity:0;"/>
		<input type="hidden" name="hcap_hp_sig" value="$hp_sig"/>
		
HTML;

		$expected                         = $field;
		$hcap_widget                      = $this->get_hcap_widget(
			[
				'source'  => [ 'ninja-forms/ninja-forms.php' ],
				'form_id' => $form_id,
			]
		);
		$expected['settings']['hcaptcha'] =
			$hcap_widget . "\n" . '				<h-captcha id="' . $uniqid . '" data-fieldId="' . $field_id . '"
			class="h-captcha"
			data-sitekey="some key"
			data-theme="some theme"
			data-size="some size"
			data-auto="false"
			data-ajax="false"
			data-force="false">
		</h-captcha>
		' . $hp_field;

		$subject = new NF();
		$subject->set_form_id( $form_id );

		self::assertSame( $expected, $subject->localize_field( $field ) );
	}

	/**
	 * Test nf_captcha_script().
	 */
	public function test_nf_captcha_script(): void {
		$subject = new NF();

		$subject->nf_captcha_script();

		self::assertTrue( wp_script_is( 'hcaptcha-nf' ) );
	}
}
