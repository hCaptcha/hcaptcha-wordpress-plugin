<?php
/**
 * WCWLCreateListTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WCWL;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test wc-wl-create-list.php file.
 *
 * WooCommerce requires PHP 7.0.
 *
 * Cannot activate WooCommerce plugin with php 8.0
 * due to some bug with usort() in \WC_Install::needs_db_update()
 * caused by antecedent/patchwork.
 *
 * @requires PHP >= 7.0
 * @requires PHP < 8.0
 */
class WCWLCreateListTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'woocommerce/woocommerce.php';

	/**
	 * Test hcap_woocommerce_wishlists_before_wrapper_action() and
	 * hcap_woocommerce_wishlists_after_wrapper_action().
	 */
	public function test_hcap_woocommerce_wishlists_wrapper_action() {
		$row      = '<p class="form-row">';
		$expected =
			"\n" .
			$this->get_hcap_form( 'hcaptcha_wc_create_wishlists_action', 'hcaptcha_wc_create_wishlists_nonce' ) .
			"\n" .
			$row;

		ob_start();

		hcap_woocommerce_wishlists_before_wrapper_action();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $row;
		hcap_woocommerce_wishlists_after_wrapper_action();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_wc_wl_create_list_captcha().
	 */
	public function test_hcap_verify_wc_wl_create_list_captcha() {
		$valid_captcha = 'some captcha';

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_create_wishlists_nonce', 'hcaptcha_wc_create_wishlists_action' );

		WC()->init();

		self::assertSame( $valid_captcha, hcap_verify_wc_wl_create_list_captcha( $valid_captcha ) );

		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test test_hcap_verify_wc_wl_create_list_captcha() not verified.
	 */
	public function test_hcap_verify_wc_wl_create_list_captcha_not_verified() {
		$valid_captcha = 'some captcha';
		$expected      = [
			'error' => [
				[
					'notice' => 'The hCaptcha is invalid.',
					'data'   => [],
				],
			],
		];

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_create_wishlists_nonce', 'hcaptcha_wc_create_wishlists_action', false );

		WC()->init();

		self::assertFalse( hcap_verify_wc_wl_create_list_captcha( $valid_captcha ) );

		self::assertSame( $expected, wc_get_notices() );
	}
}
