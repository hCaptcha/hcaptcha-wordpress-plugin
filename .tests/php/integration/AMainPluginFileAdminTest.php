<?php
/**
 * MainPluginFileAdminTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration;

/**
 * Test main plugin file in admin.
 */
class AMainPluginFileAdminTest extends HCaptchaWPTestCase {

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		set_current_screen( 'edit-post' );
		require_once HCAPTCHA_INC . '/backend/nav.php';

		parent::setUp();
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test main plugin file content in admin.
	 */
	public function test_main_file_content_in_admin(): void {
		// nav.php was required.
		self::assertSame( 'hcaptcha-options', HCAPTCHA_MENU_SLUG );

		self::assertTrue( function_exists( 'hcaptcha_options_nav' ) );
		self::assertSame( 10, has_action( 'admin_menu', 'hcaptcha_options_nav' ) );

		self::assertTrue( function_exists( 'hcaptcha_options' ) );

		self::assertTrue( function_exists( 'hcap_admin_enqueue_scripts' ) );
		self::assertSame(
			10,
			has_action( 'admin_enqueue_scripts', 'hcap_admin_enqueue_scripts' )
		);

		self::assertTrue( function_exists( 'hcap_add_settings_link' ) );
		self::assertSame(
			10,
			has_filter(
				'plugin_action_links_' . plugin_basename( HCAPTCHA_FILE ),
				'hcap_add_settings_link'
			)
		);
	}
}
