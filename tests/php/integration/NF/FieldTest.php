<?php
/**
 * FieldTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore  Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\NF\Base;
use HCaptcha\NF\Field;
use HCaptcha\NF\NF;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Ninja_Forms;
use ReflectionClass;

/**
 * Test Field class.
 *
 * @group    nf
 */
class FieldTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

	/**
	 * Hooks to replay after loading the plugin.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Test rendering an hCaptcha field through the live Ninja Forms frontend.
	 */
	public function test_live_form_render(): void {
		$ninja_forms_file = wp_normalize_path( ( new ReflectionClass( Ninja_Forms::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/ninja-forms/' ), $ninja_forms_file );

		new NF();

		Ninja_Forms::instance()->instantiateTranslatableObjects();

		$form_id = $this->create_ninja_form( true );
		$html    = do_shortcode( '[ninja_form id="' . $form_id . '"]' );

		self::assertStringContainsString( 'id="nf-form-' . $form_id . '-cont"', $html );
		self::assertStringContainsString( '"type":"' . Base::NAME . '"', $html );
		self::assertStringContainsString( 'class=\"h-captcha\"', $html );
		self::assertStringContainsString( 'hcaptcha-widget-id', $html );
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST['formData'], $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

		parent::tearDown();
	}

	/**
	 * Test __construct().
	 *
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function test_constructor(): void {
		$subject = new Field();

		self::assertSame( 'hCaptcha', $subject->get_nicename() );
		self::assertSame( 10, has_filter( 'nf_sub_hidden_field_types', [ $subject, 'hide_field_type' ] ) );
	}

	/**
	 * Test validate().
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function test_validate(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => 'some value',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( $field['value'] );
		$this->prepare_widget_id( $form_id );

		$subject = new Field();

		self::assertNull( $subject->validate( $field, $data ) );
	}

	/**
	 * Test validate() without a field.
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function test_validate_without_field(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => '',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( '', false );
		$this->prepare_widget_id( $form_id );

		$subject = new Field();

		self::assertSame( 'Please complete the hCaptcha.', $subject->validate( $field, $data ) );
	}

	/**
	 * Test validate() when not validated.
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function test_validate_not_validated(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => 'some value',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( $field['value'], false );
		$this->prepare_widget_id( $form_id );

		$subject = new Field();

		self::assertSame( 'The hCaptcha is invalid.', $subject->validate( $field, $data ) );
	}

	/**
	 * Test validate() when widget id is missing.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_validate_missing_widget_id(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => 'some value',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( $field['value'] );

		$subject = new Field();

		self::assertSame( 'Bad hCaptcha signature!', $subject->validate( $field, $data ) );
	}

	/**
	 * Test hide_field_type().
	 *
	 * @return void
	 */
	public function test_hide_field_type(): void {
		$hidden_field_types = [ 'some type' ];
		$expected           = [ 'some type', 'hcaptcha-for-ninja-forms' ];

		$subject = new Field();

		self::assertSame( $expected, $subject->hide_field_type( $hidden_field_types ) );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int   $form_id Form id.
	 * @param array $id      Widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( int $form_id, array $id = [] ): void {
		$id = array_merge(
			[
				'source'  => [ 'ninja-forms/ninja-forms.php' ],
				'form_id' => $form_id,
			],
			$id
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Create a Ninja form.
	 *
	 * @param bool $with_hcaptcha Whether to add an hCaptcha field.
	 *
	 * @return int
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	protected function create_ninja_form( bool $with_hcaptcha = false ): int {
		// Create a Ninja form.
		$form = Ninja_Forms()->form()->get();

		$form->update_setting( 'title', 'Test Form' );
		$form->update_setting( 'publish_state', 1 );
		$form->save();

		$form_id = $form->get_id();

		// Add a name field.
		$field = Ninja_Forms()->form( $form_id )->field()->get();
		$field->update_setting( 'label', 'Name' );
		$field->update_setting( 'key', 'name' );
		$field->update_setting( 'type', 'textbox' );
		$field->update_setting( 'order', 1 );
		$field->save();

		if ( $with_hcaptcha ) {
			$hcaptcha_field = Ninja_Forms()->form( $form_id )->field()->get();
			$hcaptcha_field->update_setting( 'label', 'hCaptcha' );
			$hcaptcha_field->update_setting( 'key', 'hcaptcha' );
			$hcaptcha_field->update_setting( 'type', Base::NAME );
			$hcaptcha_field->update_setting( 'order', 3 );
			$hcaptcha_field->save();
		}

		// Add an email field.
		$field = Ninja_Forms()->form( $form_id )->field()->get();
		$field->update_setting( 'label', 'Email' );
		$field->update_setting( 'key', 'email' );
		$field->update_setting( 'type', 'email' );
		$field->update_setting( 'order', 2 );
		$field->save();

		// Add a `submit` field.
		$submit_field = Ninja_Forms()->form( $form_id )->field()->get();
		$submit_field->update_setting( 'label', 'Отправить' );
		$submit_field->update_setting( 'type', 'submit' );
		$submit_field->update_setting( 'order', 999 );
		$submit_field->save();

		return $form_id;
	}
}
