<?php
/**
 * JetpackTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Jetpack;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test jetpack form file.
 */
class JetpackTest extends HCaptchaWPTestCase {

	/**
	 * Test hcap_hcaptcha_jetpack_form().
	 *
	 * @param string $content  Form content.
	 * @param string $expected Expected content.
	 *
	 * @dataProvider dp_test_hcap_hcaptcha_jetpack_form
	 */
	public function test_hcap_hcaptcha_jetpack_form( $content, $expected ) {
		self::assertSame( $expected, hcap_hcaptcha_jetpack_form( $content ) );
	}

	/**
	 * Data provider for test_hcap_hcaptcha_jetpack_form().
	 *
	 * @return array
	 */
	public function dp_test_hcap_hcaptcha_jetpack_form() {
		$nonce = wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false );

		return [
			'Empty contact form'                 => [ '', '' ],
			'Classic contact form'               => [
				'[contact-form] Some contact form [/contact-form]',
				'[contact-form] Some contact form [hcaptcha]' . $nonce . '[/contact-form]',
			],
			'Classic contact form with hcaptcha' => [
				'[contact-form] Some contact form [hcaptcha][/contact-form]',
				'[contact-form] Some contact form [hcaptcha][/contact-form]' . $nonce,
			],
			'Block contact form'                 => [
				'<form wp-block-jetpack-contact-form </form>',
				'<form wp-block-jetpack-contact-form [hcaptcha]' . $nonce . '</form>',
			],
			'Block contact form with hcaptcha'   => [
				'<form wp-block-jetpack-contact-form [hcaptcha]</form>',
				'<form wp-block-jetpack-contact-form [hcaptcha]</form>' . $nonce,
			],
		];
	}

	/**
	 * Test hcap_hcaptcha_jetpack_verify().
	 */
	public function test_hcap_hcaptcha_jetpack_verify() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );

		self::assertFalse( hcap_hcaptcha_jetpack_verify() );
		self::assertFalse( hcap_hcaptcha_jetpack_verify( false ) );
		self::assertTrue( hcap_hcaptcha_jetpack_verify( true ) );
	}

	/**
	 * Test hcap_hcaptcha_jetpack_verify() not verified.
	 */
	public function test_hcap_hcaptcha_jetpack_verify_not_verified() {
		$error = new WP_Error( 'invalid_hcaptcha', 'The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack', false );

		self::assertEquals( $error, hcap_hcaptcha_jetpack_verify() );
		self::assertSame( 10, has_action( 'hcap_hcaptcha_content', 'hcap_hcaptcha_error_message' ) );
	}
}
