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
	 * Test jetpack_form().
	 *
	 * @param string $content  Form content.
	 * @param string $expected Expected content.
	 *
	 * @dataProvider dp_test_jetpack_form
	 */
	public function test_jetpack_form( $content, $expected ) {
		$subject = new JetpackForm();

		self::assertSame( $expected, $subject->jetpack_form( $content ) );
	}

	/**
	 * Data provider for test_hcap_hcaptcha_jetpack_form().
	 *
	 * @return array
	 */
	public function dp_test_jetpack_form() {
		$_SERVER['REQUEST_URI'] = 'http://test.test/';

		$nonce_field = wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false );

		return [
			'Empty contact form'                 => [ '', '' ],
			'Classic contact form'               => [
				'[contact-form] Some contact form [/contact-form]',
				'[contact-form] Some contact form [hcaptcha]' . $nonce_field . '[/contact-form]',
			],
			'Classic contact form with hcaptcha' => [
				'[contact-form] Some contact form [hcaptcha][/contact-form]',
				'[contact-form] Some contact form [hcaptcha]' . $nonce_field . '[/contact-form]',
			],
			'Block contact form'                 => [
				'<form class="wp-block-jetpack-contact-form" <button type="submit">Contact Us</button></form>',
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button>' . $nonce_field . '</form>',
			],
			'Block contact form with hcaptcha'   => [
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button></form>',
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button>' . $nonce_field . '</form>',
			],
			'Block contact form and search form' => [
				'<form class="wp-block-jetpack-contact-form" <button type="submit">Contact Us</button></form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button>' . $nonce_field . '</form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
			],
		];
	}
}
