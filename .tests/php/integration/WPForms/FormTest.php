<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WPForms;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WPForms\Form;

/**
 * Test Forms class.
 *
 * @group wpforms
 */
class FormTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'wpforms-lite/wpforms.php';

	/**
	 * Tests add_captcha().
	 */
	public function test_add_captcha() {
		$form_data = [ 'id' => 5 ];
		$expected  =
			$this->get_hcap_form() .
			wp_nonce_field(
				'hcaptcha_wpforms',
				'hcaptcha_wpforms_nonce',
				true,
				false
			);
		$subject   = new Form();

		ob_start();

		$subject->add_captcha( $form_data );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify() {
		$fields    = [ 'some field' ];
		$form_data = [ 'id' => 5 ];
		$subject   = new Form();

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms' );

		wpforms()->objects();
		wpforms()->get( 'process' )->errors = [];

		$subject->verify( $fields, [], $form_data );

		self::assertSame( [], wpforms()->get( 'process' )->errors );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified() {
		$fields    = [ 'some field' ];
		$form_data = [ 'id' => 5 ];
		$subject   = new Form();

		$expected = 'The hCaptcha is invalid.';

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms', false );

		wpforms()->objects();
		wpforms()->get( 'process' )->errors = [];

		$subject->verify( $fields, [], $form_data );

		self::assertSame( $expected, wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] );
	}
}
