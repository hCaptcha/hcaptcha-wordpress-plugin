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
		self::assertSame( 10, has_filter( 'frm_replace_shortcodes', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_filter( 'frm_is_field_hidden', [ $subject, 'prevent_native_validation' ] ) );
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
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
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
		$captcha_div                  = '';
		$html                         = <<<HTML
<form>
	Some content
	$captcha_div
	<button>Submit</button>
</form>
HTML;
		$field                        = [ 'type' => 'some' ];
		$atts                         = [];
		$frm_settings                 = new FrmSettings();
		$frm_settings->active_captcha = 'recaptcha';

		FunctionMocker::replace(
			'FrmAppHelper::get_settings',
			static function () use ( &$frm_settings ) {
				return $frm_settings;
			}
		);

		$subject = new Form();

		self::assertSame( $html, $subject->add_captcha( $html, $field, $atts ) );

		$field['type'] = 'captcha';

		self::assertSame( $html, $subject->add_captcha( $html, $field, $atts ) );

		$frm_settings->active_captcha = 'hcaptcha';

		self::assertSame( $html, $subject->add_captcha( $html, $field, $atts ) );

		$captcha_div = '<div id="some_id" class="h-captcha" data-sitekey="some_site_key"></div>';
		$expected    = str_replace( $captcha_div, $hcap_form, $html );

		self::assertSame( $expected, $subject->add_captcha( $html, $field, $atts ) );
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

		$this->prepare_hcaptcha_get_verify_message(
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

		$this->prepare_hcaptcha_get_verify_message(
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
	public function is_formidable_forms_admin_page(): void {
		$forminator_admin_pages = [
			'formidable_page_formidable-settings',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->is_formidable_forms_admin_page() );

		set_current_screen( 'some' );

		self::assertFalse( $subject->is_formidable_forms_admin_page() );

		foreach ( $forminator_admin_pages as $page ) {
			set_current_screen( $page );

			self::assertTrue( $subject->is_formidable_forms_admin_page() );
		}
	}
}
