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

		$hash             = 'some hash';
		$hash_input       = "<input name='contact-form-hash' value='$hash'>";
		$args             = [
			'action' => 'hcaptcha_jetpack',
			'name'   => 'hcaptcha_jetpack_nonce',
			'id'     => [
				'source'  => [ 'jetpack/jetpack.php' ],
				'form_id' => 'contact_' . $hash,
			],
		];
		$hcaptcha         = $this->get_hcap_form( $args );
		$compact_hcaptcha = $this->get_hcap_form(
			array_merge(
				$args,
				[
					'size' => 'compact',
				]
			)
		);
		$wrong_hcaptcha   = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_action',
				'name'   => 'hcaptcha_nonce',
				'id'     => [
					'source'  => [],
					'form_id' => 0,
				],
				'size'   => 'compact',
			]
		);

		return [
			'Empty contact form'                           => [ '', '' ],
			'Classic contact form'                         => [
				'<form class=\'contact-form\' <button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
				'<form class=\'contact-form\' <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
			],
			'Classic contact form with hcaptcha shortcode' => [
				'<form class=\'contact-form\' <div class=\'manual\'>[hcaptcha size="compact"]</div><button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
				'<form class=\'contact-form\' <div class=\'manual\'><div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $compact_hcaptcha . '</div></div><button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
			],
			'Classic contact form with hcaptcha markup'    => [
				'<form class=\'contact-form\' <div class=\'manual\'>' . $wrong_hcaptcha . '</div><button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
				'<form class=\'contact-form\' <div class=\'manual\'><div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $compact_hcaptcha . '</div></div><button type=\'submit\'>Contact Us</button>' . $hash_input . '</form>',
			],
			'Block contact form'                           => [
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>',
				'<form class="wp-block-jetpack-contact-form" <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>',
			],
			'Block contact form with core button'          => [
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-button" <button class="wp-block-button__link wp-element-button" type="submit">Contact Us</button>' . $hash_input . '</form>',
				'<form class="wp-block-jetpack-contact-form" <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><div class="wp-block-button" <button class="wp-block-button__link wp-element-button" type="submit">Contact Us</button>' . $hash_input . '</form>',
			],
			'Block contact form with submit button classes' => [
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-button form-button-submit is-submit" <button type="submit" class="wp-block-button__link wp-element-button">Contact Us</button>' . $hash_input . '</form>',
				'<form class="wp-block-jetpack-contact-form" <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><div class="wp-block-button form-button-submit is-submit" <button type="submit" class="wp-block-button__link wp-element-button">Contact Us</button>' . $hash_input . '</form>',
			],
			'Block contact form with hcaptcha shortcode'   => [
				'<form class="wp-block-jetpack-contact-form" <div class="manual">[hcaptcha]</div><div class="wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>',
				'<form class="wp-block-jetpack-contact-form" <div class="manual"><div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div></div><div class="wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>',
			],
			'Block contact form with hcaptcha markup'      => [
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-button" <button type="submit">Contact Us</button></div><div class="manual">' . $wrong_hcaptcha . '</div>' . $hash_input . '</form>',
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-button" <button type="submit">Contact Us</button></div><div class="manual"><div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $compact_hcaptcha . '</div></div>' . $hash_input . '</form>',
			],
			'Block contact form and search form'           => [
				'<form class="wp-block-jetpack-contact-form" <div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
				'<form class="wp-block-jetpack-contact-form" <div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $hcaptcha . '</div><div class="wp-block-jetpack-button wp-block-button" <button type="submit">Contact Us</button>' . $hash_input . '</form>' .
				'<form class="search-form" <input type="submit" class="search-submit" value="Search"></form>',
			],
		];
	}
}
