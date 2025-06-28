<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Formidable;

use FrmSettings;
use HCaptcha\FormidableForms\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use stdClass;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Formidable Forms.
 *
 * @group formidable
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 10, has_filter( 'option_frm_options', [ $subject, 'get_option' ] ) );
		self::assertSame( 10, has_filter( 'frm_replace_shortcodes', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 20, has_filter( 'frm_is_field_hidden', [ $subject, 'prevent_native_validation' ] ) );
		self::assertSame( 10, has_filter( 'frm_validate_entry', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_action( 'admin_enqueue_scripts', [ $subject, 'admin_enqueue_scripts' ] ) );
	}

	/**
	 * Test get_option().
	 */
	public function test_get_option(): void {
		$option                     = 'frm_options';
		$public_key                 = 'some public key';
		$private_key                = 'some secret key';
		$expected                   = new FrmSettings();
		$expected->active_captcha   = 'hcaptcha';
		$expected->hcaptcha_pubkey  = $public_key;
		$expected->hcaptcha_privkey = $private_key;

		$subject = new Form();

		$value = null;

		self::assertSame( $value, $subject->get_option( $value, $option ) );

		$value = new stdClass();

		self::assertSame( $value, $subject->get_option( $value, $option ) );

		$value                 = new FrmSettings();
		$value->active_captcha = 'recaptcha';

		self::assertSame( $value, $subject->get_option( $value, $option ) );

		$value->active_captcha = 'hcaptcha';

		add_filter(
			'hcap_site_key',
			static function () use ( $public_key ) {
				return $public_key;
			}
		);
		add_filter(
			'hcap_secret_key',
			static function () use ( $private_key ) {
				return $private_key;
			}
		);

		self::assertEquals( $expected, $subject->get_option( $value, $option ) );
	}

	/**
	 * Test add_hcaptcha().
	 */
	public function test_add_hcaptcha(): void {
		$form_id                      = 5;
		$hcap_form                    = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_formidable_forms',
				'name'   => 'hcaptcha_formidable_forms_nonce',
				'id'     => [
					'source'  => [ 'formidable/formidable.php' ],
					'form_id' => $form_id,
				],
			]
		);
		$class                        = 'class="h-captcha"';
		$div_id                       = 'some_id';
		$hcap_form                    = str_replace( $class, 'id="' . $div_id . '"' . $class, $hcap_form );
		$hcaptcha_div                 = '<div id="' . $div_id . '" class="h-captcha" data-sitekey="some_site_key"></div>';
		$html_with_hcaptcha           = <<<HTML
<form>
	Some content
	$hcaptcha_div
	<button>Submit</button>
</form>
HTML;
		$html                         = str_replace( $hcaptcha_div, '', $html_with_hcaptcha );
		$field                        = [ 'type' => 'some' ];
		$atts                         = [ 'form' => (object) [ 'id' => $form_id ] ];
		$frm_settings                 = new FrmSettings();
		$frm_settings->active_captcha = 'recaptcha';
		$expected                     = str_replace( $hcaptcha_div, $hcap_form, $html_with_hcaptcha );

		FunctionMocker::replace(
			'FrmAppHelper::get_settings',
			static function () use ( &$frm_settings ) {
				return $frm_settings;
			}
		);

		$subject = new Form();

		// Field type is not captcha.
		self::assertSame( $html, $subject->add_hcaptcha( $html, $field, $atts ) );

		// Active captcha is not hCaptcha.
		$field['type'] = 'captcha';

		self::assertSame( $html, $subject->add_hcaptcha( $html, $field, $atts ) );

		// No hCaptcha div in $html.
		$frm_settings->active_captcha = 'hcaptcha';

		self::assertSame( $html, $subject->add_hcaptcha( $html, $field, $atts ) );

		// Success path.
		self::assertSame( $expected, $subject->add_hcaptcha( $html_with_hcaptcha, $field, $atts ) );
	}

	/**
	 * Test prevent_native_validation().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_prevent_native_validation(): void {
		$field_id                     = 5;
		$field                        = (object) [
			'id'   => $field_id,
			'type' => 'some',
		];
		$post                         = [ 'some past data' ];
		$frm_settings                 = new FrmSettings();
		$frm_settings->active_captcha = 'recaptcha';

		FunctionMocker::replace(
			'FrmAppHelper::get_settings',
			static function () use ( &$frm_settings ) {
				return $frm_settings;
			}
		);

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->prevent_native_validation( false, $field, $post ) );

		$field->type = 'captcha';

		self::assertFalse( $subject->prevent_native_validation( false, $field, $post ) );

		$frm_settings->active_captcha = 'hcaptcha';

		self::assertTrue( $subject->prevent_native_validation( false, $field, $post ) );
		self::assertSame( $field_id, $this->get_protected_property( $subject, 'hcaptcha_field_id' ) );
	}

	/**
	 * Test verify() with bad response.
	 *
	 * @return void
	 */
	public function test_verify_no_success(): void {
		$errors        = [ 'some error' => 'some message' ];
		$values        = [ 'some values' ];
		$validate_args = [ 'some args' ];
		$error_message = 'The hCaptcha is invalid.';
		$expected      = array_merge( $errors, [ 'field1' => $error_message ] );

		$this->prepare_verify_post(
			'hcaptcha_formidable_forms_nonce',
			'hcaptcha_formidable_forms',
			false
		);

		$subject = new Form();

		self::assertSame( $expected, $subject->verify( $errors, $values, $validate_args ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$errors        = [ 'some error' => 'some message' ];
		$values        = [ 'some values' ];
		$validate_args = [ 'some args' ];

		$this->prepare_verify_post(
			'hcaptcha_formidable_forms_nonce',
			'hcaptcha_formidable_forms'
		);

		$subject = new Form();

		self::assertSame( $errors, $subject->verify( $errors, $values, $validate_args ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$handle = 'captcha-api';

		wp_enqueue_script( $handle, 'some url', [], '1.0', true );

		$subject = new Form();

		self::assertTrue( wp_script_is( $handle ) );

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( $handle ) );
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts(): void {
		$admin_handle   = 'admin-formidable-forms';
		$notice         = HCaptcha::get_hcaptcha_plugin_notice();
		$params         = [
			'noticeLabel'       => $notice['label'],
			'noticeDescription' => html_entity_decode( $notice['description'] ),
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaFormidableFormsObject = ' . json_encode( $params ) . ';',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_formidable_forms_admin_page' )->andReturn( true );

		self::assertFalse( wp_script_is( $admin_handle ) );

		$subject->admin_enqueue_scripts();

		self::assertTrue( wp_script_is( $admin_handle ) );

		$script = wp_scripts()->registered[ $admin_handle ];

		self::assertSame( HCAPTCHA_URL . '/assets/js/admin-formidable-forms.min.js', $script->src );
		self::assertSame( [ 'jquery' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );
	}

	/**
	 * Test admin_enqueue_scripts() when not on Formidable Forms page.
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts_not_on_formidable_forms_page(): void {
		$admin_handle = 'admin-formidable-forms';

		wp_dequeue_script( $admin_handle );
		wp_deregister_script( $admin_handle );

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_formidable_forms_admin_page' )->andReturn( false );

		self::assertFalse( wp_script_is( $admin_handle ) );

		$subject->admin_enqueue_scripts();

		self::assertFalse( wp_script_is( $admin_handle ) );
	}

	/**
	 * Test is_formidable_forms_admin_page().
	 *
	 * @return void
	 * @noinspection DisconnectedForeachInstructionInspection
	 */
	public function test_is_formidable_forms_admin_page(): void {
		$formidable_admin_pages = [
			'formidable_page_formidable-settings',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Not admin page.
		self::assertFalse( $subject->is_formidable_forms_admin_page() );

		// Not Formidable admin page.
		set_current_screen( 'some' );

		self::assertFalse( $subject->is_formidable_forms_admin_page() );

		// Success path. Formidable admin pages.
		foreach ( $formidable_admin_pages as $page ) {
			set_current_screen( $page );

			self::assertTrue( $subject->is_formidable_forms_admin_page() );
		}
	}
}
