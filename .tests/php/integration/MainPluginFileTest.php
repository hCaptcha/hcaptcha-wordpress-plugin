<?php
/**
 * MainPluginFileTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration;

/**
 * Test main plugin file.
 *
 * @group main-plugin-file
 */
class MainPluginFileTest extends HCaptchaWPTestCase {

	/**
	 * Test main plugin file content.
	 *
	 * @noinspection HttpUrlsUsage
	 */
	public function test_main_file_content() {
		$expected    = [
			'version' => HCAPTCHA_VERSION,
		];
		$plugin_file = HCAPTCHA_FILE;

		$plugin_headers = get_file_data(
			$plugin_file,
			[ 'version' => 'Version' ],
			'plugin'
		);

		self::assertSame( $expected, $plugin_headers );

		self::assertSame( realpath( __DIR__ . '/../../../' ), HCAPTCHA_PATH );

		$config = include __DIR__ . '/../../../.codeception/_config/params.local.php';
		$wp_url = $config['WP_URL'];
		self::assertSame( 'http://' . $wp_url . '/wp-content/plugins/hcaptcha-wordpress-plugin', HCAPTCHA_URL );

		self::assertSame( realpath( __DIR__ . '/../../../hcaptcha.php' ), HCAPTCHA_FILE );

		self::assertSame( 'hcaptcha_action', HCAPTCHA_ACTION );
		self::assertSame( 'hcaptcha_nonce', HCAPTCHA_NONCE );

		// request.php was required.
		self::assertTrue( function_exists( 'hcap_get_user_ip' ) );
		self::assertTrue( function_exists( 'hcap_get_error_messages' ) );
		self::assertTrue( function_exists( 'hcap_get_error_message' ) );
		self::assertTrue( function_exists( 'hcaptcha_request_verify' ) );
		self::assertTrue( function_exists( 'hcaptcha_verify_post' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_output' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_message' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_message_html' ) );

		// functions.php was required.
		self::assertTrue( function_exists( 'hcap_shortcode' ) );
		self::assertTrue( shortcode_exists( 'hcaptcha' ) );
	}

	/**
	 * Test that readme.txt contains proper stable tag.
	 */
	public function test_readme_txt() {
		$expected    = [
			'stable_tag' => HCAPTCHA_VERSION,
		];
		$readme_file = HCAPTCHA_PATH . '/readme.txt';

		$readme_headers = get_file_data(
			$readme_file,
			[ 'stable_tag' => 'Stable tag' ],
			'plugin'
		);

		self::assertSame( $expected, $readme_headers );
	}
}
