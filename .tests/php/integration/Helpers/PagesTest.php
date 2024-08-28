<?php
/**
 * PagesTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\Pages;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Pages class.
 *
 * @group helpers
 * @group helpers-pages
 */
class PagesTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test is_cf7_form_admin_page().
	 *
	 * @param string $page     Page.
	 * @param bool   $has_post Has post.
	 * @param bool   $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_is_cf7_edit_page
	 */
	public function test_is_cf7_edit_page( string $page, bool $has_post, bool $expected ): void {
		self::assertFalse( Pages::is_cf7_edit_page() );

		set_current_screen( 'some' );

		$_GET['page'] = $page;

		if ( $has_post ) {
			$_GET['post'] = 177;
		}

		self::assertSame( $expected, Pages::is_cf7_edit_page() );
	}

	/**
	 * Data provider for test_is_cf7_edit_page().
	 *
	 * @return array
	 */
	public function dp_test_is_cf7_edit_page(): array {
		return [
			[ 'some', false, false ],
			[ 'wpcf7', false, false ],
			[ 'wpcf7', true, true ],
			[ 'wpcf7-new', false, true ],
			[ 'wpcf7-new', true, true ],
		];
	}
}
