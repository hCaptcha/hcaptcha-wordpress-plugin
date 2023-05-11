<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\GravityForms;

use HCaptcha\GravityForms\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test GravityForms.
 *
 * @group gravityforms
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Form();

		self::assertSame(
			10,
			has_filter( 'gform_submit_button', [ $subject, 'add_captcha' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$form = [
			'id' => 23,
		];

		$subject = new Form();

		$expected = $this->get_hcap_form( HCAPTCHA_ACTION, HCAPTCHA_NONCE, true );

		self::assertSame( $expected, $subject->add_captcha( '', $form ) );
	}
}
