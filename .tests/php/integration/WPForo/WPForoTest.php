<?php
/**
 * WPForoTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WPForo;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use tad\FunctionMocker\FunctionMocker;
use wpforo\classes\Notices;

/**
 * Test wpforo files.
 */
class WPForoTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'wpforo/wpforo.php';

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		set_current_screen( 'edit-post' );

		parent::setUp();

		WPF()->notice = new Notices();
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		WPF()->session_token = '';
		WPF()->notice->clear();
		WPF()->session_token = '';

		parent::tearDown();
	}

	/**
	 * Test hcap_wpforo_topic_form().
	 */
	public function test_hcap_wpforo_topic_form() {
		$nonce    = wp_nonce_field( 'hcaptcha_wpforo_new_topic', 'hcaptcha_wpforo_new_topic_nonce', true, false );
		$expected = $this->get_hcap_form() . $nonce;

		ob_start();

		hcap_wpforo_topic_form();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_wpforo_topic_captcha().
	 */
	public function test_hcap_verify_wpforo_topic_captcha() {
		$data = [ 'some data' ];
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforo_new_topic_nonce', 'hcaptcha_wpforo_new_topic' );

		WPF()->session_token = '23';

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertEquals( $data, hcap_verify_wpforo_topic_captcha( $data ) );
		self::assertSame( '', WPF()->notice->get_notices() );
	}

	/**
	 * Test hcap_verify_wpforo_topic_captcha() when not verified.
	 */
	public function test_hcap_verify_wpforo_topic_captcha_not_verified() {
		$expected = '<p class="error">The hCaptcha is invalid.</p>';

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforo_new_topic_nonce', 'hcaptcha_wpforo_new_topic', false );

		FunctionMocker::replace( 'wpforo_is_ajax', true );

		WPF()->session_token = '23';

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertFalse( hcap_verify_wpforo_topic_captcha( [] ) );

		WPF()->session_token = '';

		self::assertSame( $expected, WPF()->notice->get_notices() );
	}

	/**
	 * Test hcap_wpforo_reply_form().
	 */
	public function test_hcap_wpforo_reply_form() {
		$nonce    = wp_nonce_field( 'hcaptcha_wpforo_reply', 'hcaptcha_wpforo_reply_nonce', true, false );
		$expected = $this->get_hcap_form() . $nonce;

		ob_start();

		hcap_wpforo_reply_form();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_wpforo_reply_captcha().
	 */
	public function test_hcap_verify_wpforo_reply_captcha() {
		$data = [ 'some data' ];

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforo_reply_nonce', 'hcaptcha_wpforo_reply' );

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertSame( $data, hcap_verify_wpforo_reply_captcha( $data ) );
		self::assertSame( '', WPF()->notice->get_notices() );
	}

	/**
	 * Test hcap_verify_wpforo_reply_captcha() when not verified.
	 */
	public function test_hcap_verify_wpforo_reply_captcha_not_verified() {
		$expected = '<p class="error">The hCaptcha is invalid.</p>';

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforo_reply_nonce', 'hcaptcha_wpforo_reply', false );

		FunctionMocker::replace( 'wpforo_is_ajax', true );

		WPF()->session_token = '23';

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertFalse( hcap_verify_wpforo_reply_captcha( [] ) );

		WPF()->session_token = '';

		self::assertSame( $expected, WPF()->notice->get_notices() );
	}
}
