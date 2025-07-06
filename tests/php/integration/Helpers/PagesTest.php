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
		unset( $_GET, $_POST, $_SERVER['REQUEST_URI'], $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test is_beaver_builder_edit_page().
	 *
	 * @return void
	 */
	public function test_is_beaver_builder_edit_page(): void {
		self::assertFalse( Pages::is_beaver_builder_edit_page() );

		$_GET['fl_builder'] = '';

		self::assertTrue( Pages::is_beaver_builder_edit_page() );
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

	/**
	 * Test is_elementor_preview_page().
	 *
	 * @return void
	 */
	public function test_is_elementor_preview_page(): void {
		self::assertFalse( Pages::is_elementor_preview_page() );

		// Request.
		$_GET['elementor-preview'] = '4242';

		self::assertTrue( Pages::is_elementor_preview_page() );
	}

	/**
	 * Test is_elementor_pro_edit_page().
	 *
	 * @return void
	 */
	public function test_is_elementor_pro_edit_page(): void {
		self::assertFalse( Pages::is_elementor_pro_edit_page() );

		// Request 1.
		$_GET['post']           = 'some';
		$_SERVER['REQUEST_URI'] = '/wp-admin/post.php';
		$_GET['action']         = 'elementor';

		self::assertTrue( Pages::is_elementor_pro_edit_page() );

		// Request 2.
		unset( $_GET, $_POST, $_SERVER['REQUEST_URI'] );

		$_GET['preview_id']    = 'some';
		$_GET['preview_nonce'] = 'some';
		$_GET['preview']       = 'true';

		self::assertTrue( Pages::is_elementor_pro_edit_page() );

		// Request 3.
		unset( $_GET, $_POST );

		$_POST['action'] = 'elementor_ajax';

		self::assertTrue( Pages::is_elementor_pro_edit_page() );
	}

	/**
	 * Test is_gravity_edit_page().
	 *
	 * @return void
	 */
	public function test_is_gravity_edit_page(): void {
		self::assertFalse( Pages::is_gravity_edit_page() );

		$_GET['id']             = 'some';
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'gf_edit_forms';

		self::assertTrue( Pages::is_gravity_edit_page() );
	}

	/**
	 * Test is_fluent_edit_page().
	 *
	 * @return void
	 */
	public function test_is_fluent_edit_page(): void {
		self::assertFalse( Pages::is_fluent_edit_page() );

		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'fluent_forms_settings';

		self::assertTrue( Pages::is_fluent_edit_page() );
	}

	/**
	 * Test is_forminator_edit_page().
	 *
	 * @return void
	 */
	public function test_is_forminator_edit_page(): void {
		self::assertFalse( Pages::is_forminator_edit_page() );

		$_GET['id']             = 'some';
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'forminator-cform-wizard';

		self::assertTrue( Pages::is_forminator_edit_page() );
	}

	/**
	 * Test is_formidable_edit_page().
	 *
	 * @return void
	 */
	public function test_is_formidable_edit_page(): void {
		self::assertFalse( Pages::is_formidable_forms_edit_page() );

		// Request 1.
		$_GET['frm_action']     = 'some';
		$_GET['id']             = 'some';
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'formidable';

		self::assertTrue( Pages::is_formidable_forms_edit_page() );

		// Request 2.
		unset( $_GET, $_POST, $_SERVER['REQUEST_URI'] );

		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'formidable-styles';

		self::assertTrue( Pages::is_formidable_forms_edit_page() );

		// Request 3.
		unset( $_GET, $_POST, $_SERVER['REQUEST_URI'] );

		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'formidable-settings';

		self::assertTrue( Pages::is_formidable_forms_edit_page() );
	}

	/**
	 * Test is_is_ninja_edit_page().
	 *
	 * @return void
	 */
	public function test_is_ninja_edit_page(): void {
		self::assertFalse( Pages::is_ninja_edit_page() );

		$_GET['form_id']        = 'some';
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'ninja-forms';

		self::assertTrue( Pages::is_ninja_edit_page() );
	}

	/**
	 * Test is_wpforms_edit_page().
	 *
	 * @return void
	 */
	public function test_is_wpforms_edit_page(): void {
		self::assertFalse( Pages::is_wpforms_edit_page() );

		// Request 1.
		$_GET['page'] = 'wpforms-settings';
		$_GET['view'] = 'captcha';

		set_current_screen( 'some' );

		self::assertTrue( Pages::is_wpforms_edit_page() );

		// Request 2.
		unset( $_GET );

		$_GET['wpforms_form_preview'] = 'some';

		unset( $GLOBALS['current_screen'] );

		self::assertTrue( Pages::is_wpforms_edit_page() );
	}
}
