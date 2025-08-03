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

namespace HCaptcha\Tests\Integration\Forminator;

use Forminator_Front_Action;
use HCaptcha\Forminator\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;

/**
 * Test Forminator Form.
 *
 * @group forminator
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

		self::assertSame( 10, has_action( 'forminator_before_form_render', [ $subject, 'before_form_render' ] ) );
		self::assertSame( 10, has_filter( 'forminator_render_button_markup', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'forminator_cform_form_is_submittable', [ $subject, 'verify' ] ) );

		self::assertSame( 0, has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );

		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_action( 'admin_enqueue_scripts', [ $subject, 'admin_enqueue_scripts' ] ) );

		self::assertSame( 10, has_filter( 'forminator_field_markup', [ $subject, 'replace_hcaptcha_field' ] ) );
	}

	/**
	 * Test before_form_render().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_before_form_render(): void {
		$id            = 5;
		$form_type     = 'some form type';
		$post_id       = 123;
		$form_fields   = [
			[
				'type' => 'some',
			],
			[
				'type'             => 'captcha',
				'captcha_provider' => 'hcaptcha',
			],
		];
		$form_settings = [ 'some form settings' ];

		$subject = new Form();

		$subject->before_form_render( $id, $form_type, $post_id, $form_fields, $form_settings );

		self::assertTrue( $this->get_protected_property( $subject, 'has_hcaptcha_field' ) );
		self::assertSame( $id, $this->get_protected_property( $subject, 'form_id' ) );

		$subject->before_form_render( $id, $form_type, $post_id, [], $form_settings );

		self::assertFalse( $this->get_protected_property( $subject, 'has_hcaptcha_field' ) );
		self::assertSame( $id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test add_hcaptcha().
	 */
	public function test_add_hcaptcha(): void {
		$form_id   = 5;
		$hcap_form = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_forminator',
				'name'   => 'hcaptcha_forminator_nonce',
				'id'     => [
					'source'  => [ 'forminator/forminator.php' ],
					'form_id' => $form_id,
				],
			]
		);
		$html      = '<form>Some content<button>Submit</button></form>';
		$button    = 'Some button';
		$expected  = str_replace( '<button ', $hcap_form . '<button ', $html );

		$subject = new Form();

		$this->set_protected_property( $subject, 'form_id', $form_id );

		self::assertSame( $html, $subject->add_hcaptcha( $html, $button ) );

		$this->set_protected_property( $subject, 'has_hcaptcha_field', true );

		self::assertSame( $expected, $subject->add_hcaptcha( $html, $button ) );
	}

	/**
	 * Test verify() with bad response.
	 *
	 * @return void
	 */
	public function test_verify_no_success(): void {
		$id            = 5;
		$form_settings = [ 'some form settings' ];
		$error_message = 'The hCaptcha is invalid.';
		$module_object = (object) [
			'fields' => [
				(object) [
					'raw' => [
						'captcha_provider' => 'hcaptcha',
					],
				],
			],
		];
		$expected      = [
			'can_submit' => false,
			'error'      => $error_message,
		];

		$this->prepare_verify_post(
			'hcaptcha_forminator_nonce',
			'hcaptcha_forminator',
			false
		);

		Forminator_Front_Action::$module_object = $module_object;

		$subject = new Form();

		self::assertSame( $expected, $subject->verify( true, $id, $form_settings ) );
		self::assertEquals( (object) [ 'fields' => [] ], $module_object );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$id            = 5;
		$form_settings = [ 'some form settings' ];
		$module_object = (object) [
			'fields' => [
				(object) [
					'raw' => [
						'captcha_provider' => 'hcaptcha',
					],
				],
			],
		];

		$this->prepare_verify_post(
			'hcaptcha_forminator_nonce',
			'hcaptcha_forminator'
		);

		Forminator_Front_Action::$module_object = $module_object;

		$subject = new Form();

		self::assertTrue( $subject->verify( true, $id, $form_settings ) );
		self::assertEquals( (object) [ 'fields' => [] ], $module_object );
	}

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_print_hcaptcha_scripts(): void {
		wp_enqueue_script(
			'forminator-hcaptcha',
			'forminator-hcaptcha.js',
			[],
			'1.0.0',
			true
		);

		self::assertTrue( wp_script_is( 'forminator-hcaptcha' ) );
		self::assertTrue( wp_script_is( 'forminator-hcaptcha', 'registered' ) );

		$subject = new Form();

		$this->set_protected_property( $subject, 'has_hcaptcha_field', true );

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );

		self::assertFalse( wp_script_is( 'forminator-hcaptcha' ) );
		self::assertFalse( wp_script_is( 'forminator-hcaptcha', 'registered' ) );

		$this->set_protected_property( $subject, 'has_hcaptcha_field', false );

		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );

		set_current_screen( 'forminator_page_forminator-cform' );

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$handle = 'hcaptcha-forminator';

		$subject = new Form();

		self::assertFalse( wp_script_is( $handle ) );

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( $handle ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( $handle ) );
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts(): void {
		$admin_handle   = 'admin-forminator';
		$notice         = HCaptcha::get_hcaptcha_plugin_notice();
		$params         = [
			'noticeLabel'       => $notice['label'],
			'noticeDescription' => html_entity_decode( $notice['description'] ),
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaForminatorObject = ' . json_encode( $params ) . ';',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_forminator_admin_page' )->andReturn( true );

		self::assertFalse( wp_script_is( $admin_handle ) );

		$subject->admin_enqueue_scripts();

		self::assertTrue( wp_script_is( $admin_handle ) );

		$script = wp_scripts()->registered[ $admin_handle ];

		self::assertSame( HCAPTCHA_URL . '/assets/js/admin-forminator.min.js', $script->src );
		self::assertSame( [ 'jquery' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );

		self::assertTrue( wp_style_is( $admin_handle ) );

		$style = wp_styles()->registered[ $admin_handle ];

		self::assertSame( HCAPTCHA_URL . '/assets/css/admin-forminator.min.css', $style->src );
		self::assertSame( [], $style->deps );
		self::assertSame( HCAPTCHA_VERSION, $style->ver );
	}

	/**
	 * Test admin_enqueue_scripts() when not on Forminator page.
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts_not_on_forminator_page(): void {
		$admin_handle = 'admin-forminator';

		wp_dequeue_script( $admin_handle );
		wp_deregister_script( $admin_handle );

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_forminator_admin_page' )->andReturn( false );

		self::assertFalse( wp_script_is( $admin_handle ) );

		$subject->admin_enqueue_scripts();

		self::assertFalse( wp_script_is( $admin_handle ) );
	}

	/**
	 * Test replace_hcaptcha_field().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_replace_hcaptcha_field(): void {
		$form_id        = 5;
		$hcap_form      = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_forminator',
				'name'   => 'hcaptcha_forminator_nonce',
				'id'     => [
					'source'  => [ 'forminator/forminator.php' ],
					'form_id' => $form_id,
				],
			]
		);
		$html           = 'some html';
		$some_field     = [
			'type' => 'some',
		];
		$hcaptcha_field = [
			'type'             => 'captcha',
			'captcha_provider' => 'hcaptcha',
		];
		$front_instance = Mockery::mock( 'Forminator_CForm_Front' );

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'form_id', $form_id );

		self::assertSame( $html, $subject->replace_hcaptcha_field( $html, $some_field, $front_instance ) );
		self::assertSame( $hcap_form, $subject->replace_hcaptcha_field( $html, $hcaptcha_field, $front_instance ) );
	}

	/**
	 * Test is_forminator_admin_page().
	 *
	 * @return void
	 * @noinspection DisconnectedForeachInstructionInspection
	 */
	public function test_is_forminator_admin_page(): void {
		$forminator_admin_pages = [
			'forminator_page_forminator-cform',
			'forminator_page_forminator-cform-wizard',
			'forminator_page_forminator-settings',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->is_forminator_admin_page() );

		set_current_screen( 'some' );

		self::assertFalse( $subject->is_forminator_admin_page() );

		foreach ( $forminator_admin_pages as $page ) {
			set_current_screen( $page );

			self::assertTrue( $subject->is_forminator_admin_page() );
		}
	}
}
