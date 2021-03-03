<?php
/**
 * HCaptchaFieldsForNFTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\NF;

use HCaptchaFieldsForNF;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test HCaptchaFieldsForNF class.
 *
 * Cannot activate Ninja Forms plugin with php 8.0
 * due to some bug with uksort() in \Ninja_Forms::plugins_loaded()
 * caused by antecedent/patchwork.
 *
 * @requires PHP < 8.0
 */
class HCaptchaFieldsForNFTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

	/**
	 * Test __construct().
	 */
	public function test_constructor() {
		$subject = new HCaptchaFieldsForNF();

		self::assertSame( 10, has_filter( 'nf_sub_hidden_field_types', [ $subject, 'hide_field_type' ] ) );
	}

	/**
	 * Test validate().
	 */
	public function test_validate() {
		$field = [ 'value' => 'some value' ];
		$this->prepare_hcaptcha_request_verify( $field['value'] );

		$subject = new HCaptchaFieldsForNF();

		self::assertNull( $subject->validate( $field, null ) );
	}

	/**
	 * Test validate() without field.
	 */
	public function test_validate_without_field() {
		$subject = new HCaptchaFieldsForNF();

		self::assertSame( 'Please complete the captcha.', $subject->validate( [], null ) );
	}

	/**
	 * Test validate() when not validated.
	 */
	public function test_validate_not_validated() {
		$field = [ 'value' => 'some value' ];
		$this->prepare_hcaptcha_request_verify( $field['value'], false );

		$subject = new HCaptchaFieldsForNF();

		self::assertSame( [ 'The Captcha is invalid.' ], $subject->validate( $field, null ) );
	}

	/**
	 * Test hide_field_type().
	 */
	public function test_hide_field_type() {
		$field_types = [ 'some type' ];
		$expected    = array_merge( $field_types, [ 'hcaptcha-for-ninja-forms' ] );

		$subject = new HCaptchaFieldsForNF();

		self::assertSame( $expected, $subject->hide_field_type( $field_types ) );
	}
}
