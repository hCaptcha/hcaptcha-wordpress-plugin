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
 * Test the main plugin file.
 *
 * @group main-plugin-file
 */
class MainPluginFileTest extends HCaptchaWPTestCase {

	/**
	 * Test main plugin file content.
	 *
	 * @noinspection HttpUrlsUsage
	 */
	public function test_main_file_content(): void {
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

		self::assertSame( HCAPTCHA_PATH, realpath( __DIR__ . '/../../../' ) );

		$config = include __DIR__ . '/../../../.codeception/_config/params.local.php';
		$wp_url = $config['WP_URL'];
		self::assertSame( HCAPTCHA_URL, 'http://' . $wp_url . '/wp-content/plugins/hcaptcha-wordpress-plugin' );

		self::assertSame( HCAPTCHA_FILE, realpath( __DIR__ . '/../../../hcaptcha.php' ) );

		self::assertSame( 'hcaptcha_action', HCAPTCHA_ACTION );
		self::assertSame( 'hcaptcha_nonce', HCAPTCHA_NONCE );

		// request.php was required.
		self::assertTrue( function_exists( 'hcap_get_user_ip' ) );
		self::assertTrue( function_exists( 'hcap_get_error_messages' ) );
		self::assertTrue( function_exists( 'hcap_get_error_message' ) );

		// functions.php was required.
		self::assertTrue( function_exists( 'hcap_shortcode' ) );
		self::assertTrue( shortcode_exists( 'hcaptcha' ) );
	}

	/**
	 * Test that readme.txt contains a proper stable tag.
	 */
	public function test_stable_tag_in_readme_txt(): void {
		if ( preg_match( '/-.+$/', HCAPTCHA_VERSION ) ) {
			$this->markTestSkipped( 'Not a final version, skipping stable tag in readme.txt test.' );
		}

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

	/**
	 * Test that readme.txt contains changelog records for the current version.
	 * The test requires PHP 8.4 because updating of the changelog is done under PHP 8.4 on the CI.
	 *
	 * @requires PHP = 8.4
	 */
	public function test_changelog(): void {
		if ( preg_match( '/-.+$/', HCAPTCHA_VERSION ) ) {
			$this->markTestSkipped( 'Not a final version, skipping changelog test.' );
		}

		$readme_file    = HCAPTCHA_PATH . '/readme.txt';
		$changelog_file = HCAPTCHA_PATH . '/changelog.txt';

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$readme    = file_get_contents( $readme_file );
		$changelog = file_get_contents( $changelog_file );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$readme_changelog = substr( $readme, strpos( $readme, "\n== Changelog ==" ) );

		self::assertSame(
			$this->get_current_version_records( $readme_changelog ),
			$this->get_current_version_records( $changelog )
		);
	}

	/**
	 * Get current version records from a changelog section.
	 *
	 * @param string $changelog Changelog.
	 *
	 * @return string
	 */
	private function get_current_version_records( string $changelog ): string {
		$current_version_records = '';

		if ( preg_match( '/= ' . HCAPTCHA_VERSION . ' =\n((?:.|\n)+)\n= /U', $changelog, $m ) ) {
			$current_version_records = $m[1];
		}

		self::assertNotEmpty( trim( $current_version_records ) );

		return $current_version_records;
	}
}
