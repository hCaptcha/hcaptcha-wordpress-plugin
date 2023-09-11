<?php
/**
 * JetpackFormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Jetpack;

use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Class JetpackFormTest.
 *
 * @group jetpack
 */
class JetpackFormTest extends HCaptchaWPTestCase {

	/**
	 * Test add_captcha().
	 *
	 * @param string $content  Form content.
	 * @param string $expected Expected content.
	 *
	 * @dataProvider dp_test_add_captcha
	 */
	public function test_add_captcha( string $content, string $expected ) {
		$subject = new JetpackForm();

		self::assertSame( $expected, $subject->add_captcha( $content ) );
	}

	/**
	 * Data provider for test_add_captcha().
	 *
	 * @return array
	 * @noinspection HtmlUnknownAttribute
	 */
	public function dp_test_add_captcha(): array {
		$_SERVER['REQUEST_URI'] = 'http://test.test/';

		$nonce_field = wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false );

		$hcaptcha = '		<div
			class="h-captcha"
			data-sitekey=""
			data-theme=""
			data-size=""
			data-auto="false">
		</div>
		' . $nonce_field;

		return [
			'Empty contact form'                 => [ '', '' ],
			'Classic contact form'               => [
				'[contact-form] Some contact form [/contact-form]',
				'[contact-form] Some contact form ' . $hcaptcha . '[/contact-form]',
			],
			'Classic contact form with hcaptcha' => [
				'[contact-form] Some contact form [hcaptcha][/contact-form]',
				'[contact-form] Some contact form [hcaptcha][/contact-form]',
			],
			'Block contact form'                 => [
				'<form class="wp-block-jetpack-contact-form" <button type="submit">Contact Us</button></form>',
				'<form class="wp-block-jetpack-contact-form" ' . $hcaptcha . '<button type="submit">Contact Us</button></form>',
			],
			'Block contact form with hcaptcha'   => [
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button></form>',
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button></form>',
			],
			'Block contact form and search form' => [
				'<form class="wp-block-jetpack-contact-form" <button type="submit">Contact Us</button></form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
				'<form class="wp-block-jetpack-contact-form" ' . $hcaptcha . '<button type="submit">Contact Us</button></form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
			],
		];
	}
}
