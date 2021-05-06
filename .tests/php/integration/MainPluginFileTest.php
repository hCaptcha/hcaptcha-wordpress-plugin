<?php
/**
 * MainPluginFileTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration;

/**
 * Test main plugin file.
 */
class MainPluginFileTest extends HCaptchaWPTestCase {

	/**
	 * Test main plugin file content.
	 */
	public function test_main_file_content(): void {
		self::assertSame( '1.9.2', HCAPTCHA_VERSION );
		self::assertSame( realpath( __DIR__ . '/../../../' ), HCAPTCHA_PATH );

		$config = include __DIR__ . '/../../../.codeception/_config/params.local.php';
		$wp_url = $config['WP_URL'];
		self::assertSame( 'http://' . $wp_url . '/wp-content/plugins/hcaptcha-wordpress-plugin', HCAPTCHA_URL );

		self::assertSame( realpath( __DIR__ . '/../../../hcaptcha.php' ), HCAPTCHA_FILE );

		// request.php was required.
		self::assertTrue( function_exists( 'hcaptcha_request_verify' ) );
		self::assertTrue( function_exists( 'hcaptcha_verify_POST' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_output' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_message' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_message_html' ) );

		// functions.php was required.
		self::assertTrue( function_exists( 'hcap_form' ) );
		self::assertTrue( function_exists( 'hcap_form_display' ) );
		self::assertTrue( function_exists( 'hcap_shortcode' ) );
		self::assertTrue( shortcode_exists( 'hcaptcha' ) );
		self::assertTrue( function_exists( 'hcap_options' ) );
	}

	/**
	 * Test hcap_hcaptcha_error_message().
	 */
	public function test_hcap_hcaptcha_error_message(): void {
		$hcaptcha_content = 'Some content';
		$expected         = '<p id="hcap_error" class="error hcap_error">The Captcha is invalid.</p>' . $hcaptcha_content;

		self::assertSame( $expected, hcap_hcaptcha_error_message( $hcaptcha_content ) );
	}
}
