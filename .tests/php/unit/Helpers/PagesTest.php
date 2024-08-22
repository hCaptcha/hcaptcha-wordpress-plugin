<?php
/**
 * PagesTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Helpers;

use HCaptcha\Helpers\Pages;
use HCaptcha\Helpers\Request;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Pages class.
 *
 * @group helpers
 * @group helpers-pages
 */
class PagesTest extends HCaptchaTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		unset( $_GET, $_POST, $_SERVER );

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
	 * Test is_cf7_edit_page().
	 *
	 * @return void
	 */
	public function test_is_cf7_edit_page(): void {
		$this->mock_filter_input();

		// Not in admin.
		FunctionMocker::replace( 'is_admin', false );
		self::assertFalse( Pages::is_cf7_edit_page() );

		// Not a CF7 page.
		FunctionMocker::replace( 'is_admin', true );
		self::assertFalse( Pages::is_cf7_edit_page() );

		$_GET['page'] = 'some';
		self::assertFalse( Pages::is_cf7_edit_page() );

		$_GET['page'] = 'wpcf7-new';
		self::assertTrue( Pages::is_cf7_edit_page() );

		$_GET['page'] = 'wpcf7';
		self::assertFalse( Pages::is_cf7_edit_page() );

		$_GET['post'] = 'some';
		self::assertTrue( Pages::is_cf7_edit_page() );
	}

	/**
	 * Test is_elementor_pro_edit_page().
	 *
	 * @return void
	 */
	public function test_is_elementor_pro_edit_page(): void {
		$this->mock_filter_input();

		self::assertFalse( Pages::is_elementor_pro_edit_page() );

		// Request 1.
		$_GET['post']           = 'some';
		$_SERVER['REQUEST_URI'] = '/wp-admin/post.php';
		$_GET['action']         = 'elementor';

		self::assertTrue( Pages::is_elementor_pro_edit_page() );

		// Request 2.
		unset( $_GET, $_POST, $_SERVER );

		$_GET['preview_id']    = 'some';
		$_GET['preview_nonce'] = 'some';
		$_GET['preview']       = 'true';

		self::assertTrue( Pages::is_elementor_pro_edit_page() );

		// Request 3.
		unset( $_GET, $_POST, $_SERVER );

		$_POST['action'] = 'elementor_ajax';

		self::assertTrue( Pages::is_elementor_pro_edit_page() );
	}

	/**
	 * Test is_gravity_edit_page().
	 *
	 * @return void
	 */
	public function test_is_gravity_edit_page(): void {
		$this->mock_filter_input();

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
		$this->mock_filter_input();

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
		$this->mock_filter_input();

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
		$this->mock_filter_input();

		self::assertFalse( Pages::is_formidable_forms_edit_page() );

		// Request 1.
		$_GET['frm_action']     = 'some';
		$_GET['id']             = 'some';
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'formidable';

		self::assertTrue( Pages::is_formidable_forms_edit_page() );

		// Request 2.
		unset( $_GET, $_POST, $_SERVER );

		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'formidable-styles';

		self::assertTrue( Pages::is_formidable_forms_edit_page() );

		// Request 3.
		unset( $_GET, $_POST, $_SERVER );

		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
		$_GET['page']           = 'formidable-settings';

		self::assertTrue( Pages::is_formidable_forms_edit_page() );
	}

	/**
	 * Test is_wpforms_edit_page().
	 *
	 * @return void
	 */
	public function test_is_wpforms_edit_page(): void {
		$this->mock_filter_input();

		self::assertFalse( Pages::is_wpforms_edit_page() );

		// Request 1.
		$_GET['page'] = 'wpforms-settings';
		$_GET['view'] = 'captcha';

		FunctionMocker::replace( 'is_admin', true );

		self::assertTrue( Pages::is_wpforms_edit_page() );

		// Request 2.
		unset( $_GET, $_POST, $_SERVER );

		$_GET['wpforms_form_preview'] = 'some';

		FunctionMocker::replace( 'is_admin', false );

		self::assertTrue( Pages::is_wpforms_edit_page() );
	}

	/**
	 * Mock filter_input().
	 *
	 * @return void
	 */
	private function mock_filter_input(): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( $type, $var_name ) {
				// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				switch ( $type ) {
					case INPUT_GET:
						return $_GET[ $var_name ] ?? '';
					case INPUT_POST:
						return $_POST[ $var_name ] ?? '';
					case INPUT_SERVER:
						return $_SERVER[ $var_name ] ?? '';
					default:
						return '';
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
				// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			}
		);
	}
}
