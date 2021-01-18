<?php
/**
 * BBPTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BBP;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use WP_Error;

/**
 * Test bbp files.
 */
class BBPTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'bbpress/bbpress.php';

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST );
		bbpress()->errors = new WP_Error();

		parent::tearDown();
	}

	/**
	 * Test hcap_display_bbp_new_topic().
	 */
	public function test_hcap_display_bbp_new_topic() {
		$nonce    = wp_nonce_field( 'hcaptcha_bbp_new_topic', 'hcaptcha_bbp_new_topic_nonce', true, false );
		$expected = $this->get_hcap_form() . $nonce;

		ob_start();

		hcap_display_bbp_new_topic();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_bbp_new_topic_captcha().
	 */
	public function test_hcap_verify_bbp_new_topic_captcha() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_bbp_new_topic_nonce', 'hcaptcha_bbp_new_topic' );

		$expected = new WP_Error();

		self::assertTrue( hcap_verify_bbp_new_topic_captcha() );

		self::assertEquals( $expected, bbpress()->errors );
	}

	/**
	 * Test hcap_verify_bbp_new_topic_captcha() when not verified.
	 */
	public function test_hcap_verify_bbp_new_topic_captcha_not_verified() {
		$expected = new WP_Error( 'hcap_error', 'Please complete the captcha.' );

		self::assertFalse( hcap_verify_bbp_new_topic_captcha() );

		self::assertEquals( $expected, bbpress()->errors );
	}

	/**
	 * Test hcap_display_bbp_reply().
	 */
	public function test_hcap_display_bbp_reply() {
		$nonce    = wp_nonce_field( 'hcaptcha_bbp_reply', 'hcaptcha_bbp_reply_nonce', true, false );
		$expected = $this->get_hcap_form() . $nonce;

		ob_start();

		hcap_display_bbp_reply();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_bbp_reply_captcha().
	 */
	public function test_hcap_verify_bbp_reply_captcha() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_bbp_reply_nonce', 'hcaptcha_bbp_reply' );

		$expected = new WP_Error();

		self::assertTrue( hcap_verify_bbp_reply_captcha() );

		self::assertEquals( $expected, bbpress()->errors );
	}

	/**
	 * Test hcap_verify_bbp_reply_captcha() when not verified.
	 */
	public function test_hcap_verify_bbp_reply_captcha_not_verified() {
		$expected = new WP_Error( 'hcap_error', 'Please complete the captcha.' );

		self::assertFalse( hcap_verify_bbp_reply_captcha() );

		self::assertEquals( $expected, bbpress()->errors );
	}
}
