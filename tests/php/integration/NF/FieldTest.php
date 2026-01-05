<?php
/**
 * FieldTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore  Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\NF\Field;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use NF_Database_Migrations;

/**
 * Test Field class.
 *
 * @requires PHP >= 7.4
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
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST['formData'] );

		parent::tearDown();
	}

	/**
	 * Start transaction.
	 *
	 * @return void
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function start_transaction() {
		parent::start_transaction();

		// Disable temporary tables creating.
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
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

		$subject = new Field();

		self::assertSame( 'The hCaptcha is invalid.', $subject->validate( $field, $data ) );
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
	 * Create a Ninja form.
	 *
	 * @return int
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	protected function create_ninja_form(): int {
		global $wpdb;

		static $form_id;

		if ( $form_id ) {
			return $form_id;
		}

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );

		return $form_id;
	}
}
