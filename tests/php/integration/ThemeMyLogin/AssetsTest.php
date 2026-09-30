<?php
/**
 * AssetsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ThemeMyLogin;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\ThemeMyLogin\Assets;

/**
 * Test Theme My Login assets.
 *
 * @group theme-my-login
 */
class AssetsTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'theme-my-login/theme-my-login.php';

	/**
	 * Test init().
	 *
	 * @return void
	 */
	public function test_init(): void {
		Assets::init();

		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ Assets::class, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ Assets::class, 'add_type_module' ] ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		Assets::enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-theme-my-login' ) );

		hcaptcha()->form_shown = true;
		Assets::enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-theme-my-login' ) );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag      = '<script src="https://test.test/a.js">some</script>';
		$expected = '<script type="module" src="https://test.test/a.js">some</script>';
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		self::assertSame( $tag, Assets::add_type_module( $tag, 'some-handle', '' ) );
		self::assertSame( $expected, Assets::add_type_module( $tag, 'hcaptcha-theme-my-login', '' ) );
	}
}
