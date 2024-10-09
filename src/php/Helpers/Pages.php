<?php
/**
 * Pages class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Class Pages.
 */
class Pages {

	/**
	 * Check if the current page is a Beaver Builder edit page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_beaver_builder_edit_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['fl_builder'] );
	}

	/**
	 * Check if the current page is a CF7 form create, edit or view page.
	 *
	 * @return bool
	 */
	public static function is_cf7_edit_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$page = Request::filter_input( INPUT_GET, 'page' );

		if ( ! in_array( $page, [ 'wpcf7-new', 'wpcf7' ], true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( 'wpcf7' === $page ) && ! isset( $_GET['post'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the current page is an Elementor preview page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_elementor_preview_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$elementor_preview = Request::filter_input( INPUT_GET, 'elementor-preview' );

		return (bool) filter_var( $elementor_preview, FILTER_VALIDATE_INT );
	}

	/**
	 * Check if the current page is an Elementor Pro post/page edit page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_elementor_pro_edit_page(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$request_uri = Request::filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$get_action  = Request::filter_input( INPUT_GET, 'action' );
		$preview     = Request::filter_input( INPUT_GET, 'preview' );
		$post_action = Request::filter_input( INPUT_POST, 'action' );

		$request1 = (
			isset( $_GET['post'] ) &&
			0 === strpos( $request_uri, '/wp-admin/post.php' ) &&
			'elementor' === $get_action
		);
		$request2 = (
			isset( $_GET['preview_id'], $_GET['preview_nonce'] ) &&
			filter_var( $preview, FILTER_VALIDATE_BOOLEAN )
		);
		$request3 = 'elementor_ajax' === $post_action;

		// phpcs:enable WordPress.Security.NonceVerification

		return $request1 || $request2 || $request3;
	}

	/**
	 * Check if the current page is a Gravity Forms form edit page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_gravity_edit_page(): bool {
		$request_uri = Request::filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$page        = Request::filter_input( INPUT_GET, 'page' );

		return (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['id'] ) &&
			0 === strpos( $request_uri, '/wp-admin/admin.php' ) &&
			'gf_edit_forms' === $page
		);
	}

	/**
	 * Check if the current page is a Fluent form edit page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_fluent_edit_page(): bool {
		$request_uri = Request::filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$page        = Request::filter_input( INPUT_GET, 'page' );

		return (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			0 === strpos( $request_uri, '/wp-admin/admin.php' ) &&
			'fluent_forms_settings' === $page
		);
	}

	/**
	 * Check if the current page is a Forminator form edit page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_forminator_edit_page(): bool {
		$request_uri = Request::filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$page        = Request::filter_input( INPUT_GET, 'page' );

		return (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['id'] ) &&
			0 === strpos( $request_uri, '/wp-admin/admin.php' ) &&
			'forminator-cform-wizard' === $page
		);
	}

	/**
	 * Check if the current page is a Formidable Forms form edit or settings page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_formidable_forms_edit_page(): bool {
		$request_uri   = Request::filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$page          = Request::filter_input( INPUT_GET, 'page' );
		$admin_request = 0 === strpos( $request_uri, '/wp-admin/admin.php' );

		$request1 = (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['frm_action'], $_GET['id'] ) &&
			$admin_request &&
			'formidable' === $page
		);

		$request2 = (
			$admin_request &&
			'formidable-styles' === $page
		);

		$request3 = (
			$admin_request &&
			'formidable-settings' === $page
		);

		return $request1 || $request2 || $request3;
	}

	/**
	 * Check if the current page is a Ninja form create, edit or view page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_ninja_edit_page(): bool {
		$request_uri   = Request::filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$page          = Request::filter_input( INPUT_GET, 'page' );
		$admin_request = 0 === strpos( $request_uri, '/wp-admin/admin.php' );

		return (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['form_id'] ) &&
			$admin_request &&
			'ninja-forms' === $page
		);
	}

	/**
	 * Check if the current page is a WPForms form preview or settings page.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public static function is_wpforms_edit_page(): bool {
		$page = Request::filter_input( INPUT_GET, 'page' );
		$view = Request::filter_input( INPUT_GET, 'view' );

		if ( 'wpforms-settings' === $page && 'captcha' === $view && is_admin() ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wpforms_form_preview'] ) ) {
			return true;
		}

		return false;
	}
}
