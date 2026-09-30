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
		self::assertStringContainsString( 'optional Statistics setting', $message );
		self::assertStringContainsString( 'https://a.hcaptcha.com/api/event', $message );
		self::assertStringContainsString( 'help prioritize plugin development', $message );
		self::assertStringContainsString( 'visitor IP of the current request (X-Forwarded-For)', $message );
		self::assertStringContainsString( 'full site URL with /plugin-stats appended', $message );
		self::assertStringContainsString( 'without key values', $message );
		self::assertStringContainsString( 'ordinary network metadata, such as the server IP', $message );

		set_current_screen( 'dashboard' );
		do_action( 'admin_init' );

		self::assertTrue( true );
	}
}
