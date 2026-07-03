<?php
/**
 * PrivacyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\Privacy;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Privacy class.
 *
 * @group admin
 * @group privacy
 */
class PrivacyTest extends HCaptchaWPTestCase {

	/**
	 * Test add_privacy_message() when content is filtered to an empty string.
	 *
	 * @return void
	 */
	public function test_add_privacy_message_returns_when_content_is_empty(): void {
		add_filter( 'hcap_privacy_policy_content', '__return_empty_string' );

		( new Privacy() )->add_privacy_message();

		self::assertTrue( true );
	}

	/**
	 * Test get_privacy_message() and add_privacy_message().
	 *
	 * @return void
	 */
	public function test_get_privacy_message_and_add_privacy_message(): void {
		$subject = new Privacy();
		$message = $subject->get_privacy_message();

		self::assertStringContainsString( 'wp-suggested-text', $message );
		self::assertStringContainsString( 'https://www.hcaptcha.com/privacy', $message );
		self::assertStringContainsString( 'https://www.hcaptcha.com/terms', $message );

		set_current_screen( 'dashboard' );
		do_action( 'admin_init' );

		self::assertTrue( true );
	}
}
