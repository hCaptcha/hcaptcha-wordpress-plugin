<?php
/**
 * JetpackBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Jetpack;

use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use WP_Error;

/**
 * Class JetpackBaseTest.
 *
 * @group jetpack
 */
class JetpackBaseTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks.
	 */
	public function test_init_hooks() {
		$subject = new JetpackForm();

		self::assertSame(
			10,
			has_filter( 'the_content', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			0,
			has_filter( 'widget_text', [ $subject, 'add_captcha' ] )
		);

		self::assertSame(
			10,
			has_filter( 'widget_text', 'shortcode_unautop' )
		);
		self::assertSame(
			10,
			has_filter( 'widget_text', 'do_shortcode' )
		);

		self::assertSame(
			100,
			has_filter( 'jetpack_contact_form_is_spam', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test jetpack_verify().
	 */
	public function test_jetpack_verify() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );

		$subject = new JetpackForm();

		self::assertFalse( $subject->verify() );
		self::assertTrue( $subject->verify( true ) );
	}

	/**
	 * Test jetpack_verify() not verified.
	 */
	public function test_jetpack_verify_not_verified() {
		$error = new WP_Error( 'invalid_hcaptcha', 'The hCaptcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack', false );

		$subject = new JetpackForm();

		self::assertEquals( $error, $subject->verify() );
		self::assertSame( 10, has_action( 'hcap_hcaptcha_content', [ $subject, 'error_message' ] ) );
	}

	/**
	 * Test error_message().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_error_message() {
		$hcaptcha_content = 'some content';
		$error_message    = 'some error message';

		$subject = new JetpackForm();

		self::assertSame( $hcaptcha_content, $subject->error_message( $hcaptcha_content ) );

		$this->set_protected_property( $subject, 'error_message', $error_message );

		$expected = '<p id="hcap_error" class="error hcap_error">' . $error_message . '</p>' . $hcaptcha_content;

		self::assertSame( $expected, $subject->error_message( $hcaptcha_content ) );
	}
}
