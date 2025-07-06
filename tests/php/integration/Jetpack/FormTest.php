<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Jetpack;

use HCaptcha\Jetpack\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Class FormTest.
 *
 * @group jetpack
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test add_captcha().
	 *
	 * @param string $content  Form content.
	 * @param string $expected Expected content.
	 *
	 * @dataProvider dp_test_add_captcha
	 */
	public function test_add_captcha( string $content, string $expected ): void {
		$subject = new Form();

		self::assertSame( $expected, $subject->add_hcaptcha( $content ) );
	}

	/**
	 * Data provider for test_add_captcha().
	 *
	 * @return array
	 * @noinspection HtmlUnknownAttribute
	 */
	public function dp_test_add_captcha(): array {
		$_SERVER['REQUEST_URI'] = 'http://test.test/';

		$hash       = 'some hash';
		$hash_input = "<input name='contact-form-hash' value='$hash'>";
		$args       = [
			'action' => 'hcaptcha_jetpack',
			'name'   => 'hcaptcha_jetpack_nonce',
			'id'     => [
				'source'  => [ 'jetpack/jetpack.php' ],
				'form_id' => 'contact_' . $hash,
			],
		];
		$hcaptcha   = $this->get_hcap_form( $args );

		return [
			'Empty contact form'                 => [ '', '' ],
			'Classic contact form'               => [
				'<form class=\'contact-form\' <button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
				'<form class=\'contact-form\' <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
			],
			'Classic contact form with hcaptcha' => [
				'[contact-form] Some contact form [hcaptcha][/contact-form]',
				'[contact-form] Some contact form [hcaptcha][/contact-form]',
			],
			'Block contact form'                 => [
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>',
				'<form class="wp-block-jetpack-contact-form" <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>',
			],
			'Block contact form with hcaptcha'   => [
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button></form>',
				'<form class="wp-block-jetpack-contact-form" [hcaptcha]<button type="submit">Contact Us</button></form>',
			],
			'Block contact form and search form' => [
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
				'<form class="wp-block-jetpack-contact-form" <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
			],
		];
	}
}
