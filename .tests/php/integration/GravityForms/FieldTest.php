<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\GravityForms;

use HCaptcha\GravityForms\Base;
use HCaptcha\GravityForms\Field;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test GravityForms Field class.
 *
 * @group gravityforms
 */
class FieldTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_POST['action'], $_POST['field'], $_GET['id'] );
		parent::tearDown();
	}

	/**
	 * Test constructor, init, and init_hooks.
	 *
	 * @param bool $mode_embed Embed mode.
	 *
	 * @dataProvider dp_test_constructor_init_and_init_hooks
	 */
	public function test_constructor_init_and_init_hooks( bool $mode_embed ) {
		if ( $mode_embed ) {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'embed' ] ] );
		} else {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [] ] );
		}

		hcaptcha()->init_hooks();

		$subject = new Field();

		if ( $mode_embed ) {
			self::assertSame( 10, has_filter( 'gform_field_groups_form_editor', [ $subject, 'add_to_field_groups' ] ) );
			self::assertSame( 10, has_filter( 'gform_duplicate_field_link', [ $subject, 'disable_duplication' ] ) );
			self::assertSame(
				10,
				has_action(
					'admin_print_footer_scripts-' . Field::EDITOR_SCREEN_ID,
					[ $subject, 'enqueue_admin_script' ]
				)
			);
			self::assertSame( 10, has_action( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
		} else {
			self::assertFalse( has_filter( 'gform_field_groups_form_editor', [ $subject, 'add_to_field_groups' ] ) );
			self::assertFalse( has_filter( 'gform_duplicate_field_link', [ $subject, 'disable_duplication' ] ) );
			self::assertFalse(
				has_action(
					'admin_print_footer_scripts-' . Field::EDITOR_SCREEN_ID,
					[ $subject, 'enqueue_admin_script' ]
				)
			);
			self::assertFalse( has_action( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
		}
	}

	/**
	 * Data provider for test_constructor_init_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_constructor_init_and_init_hooks(): array {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * Test add_to_field_groups().
	 *
	 * @return void
	 */
	public function test_add_to_field_groups() {
		$expected = [
			'advanced_fields' => [
				'fields' => [
					[
						'data-type' => 'hcaptcha',
						'value'     => 'hCaptcha',
					],
				],
			],
		];

		$subject = new Field();

		self::assertSame( $expected, $subject->add_to_field_groups( [] ) );
	}

	/**
	 * Test get_form_editor_field_title().
	 *
	 * @return void
	 */
	public function test_get_form_editor_field_title() {
		$subject = new Field();

		self::assertSame( 'hCaptcha', $subject->get_form_editor_field_title() );
	}

	/**
	 * Test get_form_editor_field_description().
	 *
	 * @return void
	 */
	public function test_get_form_editor_field_description() {
		$expected =
			'Adds a hCaptcha field to your form to help protect your website from spam and bot abuse.' .
			' ' .
			'hCaptcha settings must be modified on the hCaptcha plugin General settings page.';

		$subject = new Field();

		self::assertSame( $expected, $subject->get_form_editor_field_description() );
	}

	/**
	 * Test get_form_editor_field_icon().
	 *
	 * @return void
	 */
	public function test_get_form_editor_field_icon() {
		$expected = HCAPTCHA_URL . '/assets/images/hcaptcha-icon-black-and-white.svg';

		$subject = new Field();

		self::assertSame( $expected, $subject->get_form_editor_field_icon() );
	}

	/**
	 * Test get_form_editor_field_settings().
	 *
	 * @return void
	 */
	public function test_get_form_editor_field_settings() {
		$expected = [
			'label_placement_setting',
			'description_setting',
			'css_class_setting',
		];

		$subject = new Field();

		self::assertSame( $expected, $subject->get_form_editor_field_settings() );
	}

	/**
	 * Test get_field_input().
	 *
	 * @return void
	 */
	public function test_get_field_input() {
		$form_id  = 23;
		$field_id = 'input_0';
		$tabindex = 0;
		$form     = [
			'id' => $form_id,
		];
		$value    = '';
		$entry    = null;
		$args     = [
			'action' => Base::ACTION,
			'name'   => Base::NONCE,
			'id'     => [
				'source'  => [ 'gravityforms/gravityforms.php' ],
				'form_id' => $form_id,
			],
		];
		$search   = 'class="h-captcha"';
		$expected = str_replace(
			$search,
			$search . ' id="' . $field_id . '" data-tabindex="' . $tabindex . '"',
			$this->get_hcap_form( $args )
		);

		$subject = new Field();

		self::assertSame( $expected, $subject->get_field_input( $form, $value, $entry ) );
	}

	/**
	 * Test disable_duplication().
	 *
	 * @return void
	 */
	public function test_disable_duplication() {
		$duplicate_field_link = '#some-link';
		$field_id             = 55;
		$field                = (object) [];

		FunctionMocker::replace(
			'rgpost',
			static function ( $name ) {
				// phpcs:ignore
				return $_POST[ $name ] ?? '';
			}
		);
		FunctionMocker::replace( 'GFFormsModel::get_field', $field );

		$subject = new Field();

		// Wrong action, wrong link.
		self::assertSame( $duplicate_field_link, $subject->disable_duplication( $duplicate_field_link ) );

		// Action is rg_add_field, no field type.
		$_POST['action'] = 'rg_add_field';
		$_POST['field']  = '{}';

		self::assertSame( $duplicate_field_link, $subject->disable_duplication( $duplicate_field_link ) );

		// Action is rg_add_field, field type is hcaptcha.
		$_POST['action'] = 'rg_add_field';
		$_POST['field']  = '{"type":"hcaptcha"}';

		self::assertSame( '', $subject->disable_duplication( $duplicate_field_link ) );

		// Action is not rg_add_field, wrong link.
		$_POST['action'] = 'some';
		$_POST['field']  = '{}';

		self::assertSame( $duplicate_field_link, $subject->disable_duplication( $duplicate_field_link ) );

		// Action is not rg_add_field, proper link, no field type.
		$duplicate_field_link = "<a href='#' id='gfield_duplicate_$field_id'>some text</a>";

		$_GET['id'] = 5;

		self::assertSame( $duplicate_field_link, $subject->disable_duplication( $duplicate_field_link ) );

		// Action is not rg_add_field, proper link, field type is hcaptcha.
		$field = (object) [ 'type' => 'hcaptcha' ];
		FunctionMocker::replace( 'GFFormsModel::get_field', $field );

		self::assertSame( '', $subject->disable_duplication( $duplicate_field_link ) );
	}

	/**
	 * Test enqueue_admin_script().
	 *
	 * @return void
	 */
	public function test_enqueue_admin_script() {
		$params = [
			'onlyOne'   => 'Only one hCaptcha field can be added to the form.',
			'OKBtnText' => 'OK',
		];

		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaGravityFormsObject = ' . json_encode( $params ) . ';',
		];

		self::assertFalse( wp_script_is( Field::ADMIN_HANDLE ) );

		$subject = new Field();

		$subject->enqueue_admin_script();

		self::assertTrue( wp_script_is( Field::ADMIN_HANDLE ) );

		$script = wp_scripts()->registered[ Field::DIALOG_HANDLE ];
		self::assertSame( HCAPTCHA_URL . '/assets/js/kagg-dialog.min.js', $script->src );
		self::assertSame( [], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( [ 'group' => 1 ], $script->extra );

		$style = wp_styles()->registered[ Field::DIALOG_HANDLE ];
		self::assertSame( HCAPTCHA_URL . '/assets/css/kagg-dialog.min.css', $style->src );
		self::assertSame( [], $style->deps );
		self::assertSame( HCAPTCHA_VERSION, $style->ver );

		$script = wp_scripts()->registered[ Field::ADMIN_HANDLE ];
		self::assertSame( HCAPTCHA_URL . '/assets/js/admin-gravity-forms.min.js', $script->src );
		self::assertSame( [ Field::DIALOG_HANDLE ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );
	}

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 */
	public function test_print_hcaptcha_scripts() {
		$subject = new Field();

		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->print_hcaptcha_scripts( true ) );

		set_current_screen( Field::EDITOR_SCREEN_ID );

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->print_hcaptcha_scripts( true ) );
	}
}
