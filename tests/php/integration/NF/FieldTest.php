<?php
/**
 * FieldTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\NF\Field;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test Field class.
 *
 * @requires PHP >= 7.4
 *
 * @group nf
 */
class FieldTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

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
	 */
	public function test_validate(): void {
		$field = [ 'value' => 'some value' ];
		$this->prepare_verify_request( $field['value'] );

		$subject = new Field();

		self::assertNull( $subject->validate( $field, null ) );
	}

	/**
	 * Test validate() without a field.
	 */
	public function test_validate_without_field(): void {
		$this->prepare_verify_request( '', false );

		$subject = new Field();

		self::assertSame( 'Please complete the hCaptcha.', $subject->validate( [], null ) );
	}

	/**
	 * Test validate() when not validated.
	 */
	public function test_validate_not_validated(): void {
		$field = [ 'value' => 'some value' ];
		$this->prepare_verify_request( $field['value'], false );

		$subject = new Field();

		self::assertSame( 'The hCaptcha is invalid.', $subject->validate( $field, null ) );
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
}
