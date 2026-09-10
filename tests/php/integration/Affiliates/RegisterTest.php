<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Affiliates;

use HCaptcha\Affiliates\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Register class.
 *
 * @group affiliates
 * @group affiliates-register
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Test initial registration form rendering.
	 *
	 * @return void
	 */
	public function test_initial_render(): void {
		$subject = new Register();

		ob_start();
		$subject->before_section( 'registration' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form><input type="submit" name="affiliates-registration-submit"></form>';
		$subject->after_section( 'registration' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertStringNotContainsString( '<div class="error">', $output );
	}
}
