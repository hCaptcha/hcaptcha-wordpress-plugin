<?php
/**
 * BlockRenderingTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use HCaptcha\EasyDigitalDownloads\LostPassword;
use HCaptcha\EasyDigitalDownloads\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Block;

/**
 * Test hCaptcha placement in the native EDD account blocks.
 *
 * @group easy-digital-downloads
 */
class BlockRenderingTest extends HCaptchaWPTestCase {
	/**
	 * A recovery challenge appears before the EDD submit control.
	 */
	public function test_lost_password_block(): void {
		$subject = new LostPassword();
		$block   = [ 'blockName' => 'edd/login' ];
		$object  = new WP_Block( $block );
		$html    = '<div class="edd-blocks-form__group edd-blocks-form__group-submit"><input id="edd_lost_password_submit" type="submit"></div>';

		self::assertSame( $html, $subject->add_captcha( $html, $block, $object ) );
		do_action( 'edd_lost_password_fields_after' );
		self::assertSame( $html, $subject->add_captcha( $html, [ 'blockName' => 'core/group' ], $object ) );

		$output = $subject->add_captcha( $html, $block, $object );

		self::assertStringContainsString( 'name="hcaptcha_easy_digital_downloads_lostpassword_nonce"', $output );
		self::assertLessThan( strpos( $output, 'edd_lost_password_submit' ), strpos( $output, 'hcaptcha-widget-id' ) );
	}

	/**
	 * A registration challenge appears before the EDD submit control.
	 */
	public function test_registration_block(): void {
		$subject = new Register();
		$block   = [ 'blockName' => 'edd/register' ];
		$object  = new WP_Block( $block );
		$html    = '<div class="edd-blocks-form__group edd-blocks-form__group-submit reset-pass-submit"><button type="submit">Register</button></div>';

		self::assertSame( $html, $subject->add_captcha( $html, $block, $object ) );
		do_action( 'edd_register_form_fields_after' );
		self::assertSame( $html, $subject->add_captcha( $html, [ 'blockName' => 'core/group' ], $object ) );

		$output = $subject->add_captcha( $html, $block, $object );

		self::assertStringContainsString( 'name="hcaptcha_easy_digital_downloads_register_nonce"', $output );
		self::assertLessThan( strpos( $output, 'reset-pass-submit' ), strpos( $output, 'hcaptcha-widget-id' ) );
	}
}
