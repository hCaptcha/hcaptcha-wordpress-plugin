<?php
/**
 * FieldsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\NF\Fields;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test Fields class.
 */
class FieldsTest extends HCaptchaPluginWPTestCase {

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
	public function test_constructor() {
		$subject = new Fields();

		self::assertSame( 'hCaptcha', $subject->get_nicename() );
	}

	/**
	 * Test validate().
	 */
	public function test_validate() {
		$field = [ 'value' => 'some value' ];
		$this->prepare_hcaptcha_request_verify( $field['value'] );

		$subject = new Fields();

		self::assertNull( $subject->validate( $field, null ) );
	}

	/**
	 * Test validate() without field.
	 */
	public function test_validate_without_field() {
		$subject = new Fields();

		self::assertSame( 'Please complete the hCaptcha.', $subject->validate( [], null ) );
	}

	/**
	 * Test validate() when not validated.
	 */
	public function test_validate_not_validated() {
		$field = [ 'value' => 'some value' ];
		$this->prepare_hcaptcha_request_verify( $field['value'], false );

		$subject = new Fields();

		self::assertSame( 'The hCaptcha is invalid.', $subject->validate( $field, null ) );
	}
}
