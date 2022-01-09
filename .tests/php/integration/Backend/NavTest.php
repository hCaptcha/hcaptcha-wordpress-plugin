<?php
/**
 * NavTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Backend;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test nav file.
 */
class NavTest extends HCaptchaWPTestCase {

	/**
	 * Test hcaptcha_options_nav().
	 */
	public function test_hcaptcha_options_nav(): void {
		self::assertArrayNotHasKey( 'admin_page_hooks', $GLOBALS );

		do_action( 'admin_menu' );

		self::assertArrayHasKey( 'hcaptcha-options', $GLOBALS['_wp_submenu_nopriv']['options-general.php'] );
	}

	/**
	 * Test hcaptcha_options() with non-privileged user.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_hcaptcha_options_no_priv(): void {
		self::assertFalse( function_exists( 'hcap_display_options_page' ) );
		self::assertFalse( function_exists( 'hcap_display_options' ) );
		self::assertFalse( function_exists( 'hcap_display_option' ) );

		$die_arr  = [];
		$expected = [
			'You do not have sufficient permissions to access this page.',
			'hCaptcha',
			[],
		];

		remove_filter( 'wp_die_handler', '_default_wp_die_handler' );

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		ob_start();
		hcaptcha_options();
		ob_get_clean();

		self::assertTrue( function_exists( 'hcap_display_options_page' ) );
		self::assertTrue( function_exists( 'hcap_display_options' ) );
		self::assertTrue( function_exists( 'hcap_display_option' ) );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test hcaptcha_options().
	 */
	public function test_hcaptcha_options(): void {
		wp_set_current_user( 1 );

		ob_start();
		hcaptcha_options();
		ob_get_clean();
	}

	/**
	 * Test hcap_admin_enqueue_scripts().
	 */
	public function test_hcap_admin_enqueue_scripts(): void {
		self::assertFalse( wp_style_is( 'hcaptcha-admin', 'enqueued' ) );

		hcap_admin_enqueue_scripts();

		self::assertTrue( wp_style_is( 'hcaptcha-admin', 'enqueued' ) );
	}

	/**
	 * Test hcap_add_settings_link().
	 */
	public function test_hcap_add_settings_link() {
		$actions = [
			'some' => 'value',
		];

		$expected = array_merge(
			[
				'settings' =>
					'<a href="' . admin_url( 'options-general.php?page=' . HCAPTCHA_MENU_SLUG ) .
					'" aria-label="View hCaptcha settings">Settings</a>',
			],
			$actions
		);

		self::assertSame( $expected, hcap_add_settings_link( $actions, '', [], '' ) );
	}
}
