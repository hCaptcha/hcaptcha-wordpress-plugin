<?php
/**
 * WCCheckoutTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test wc-checkout.php file.
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
class WCCheckoutTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'woocommerce/woocommerce.php';

	/**
	 * Test tear down.
	 */
	public function tearDown(): void {
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		parent::tearDown();
	}

	/**
	 * Tests hcap_display_wc_checkout().
	 */
	public function test_hcap_display_wc_checkout() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field(
				'hcaptcha_wc_checkout',
				'hcaptcha_wc_checkout_nonce',
				true,
				false
			);

		ob_start();

		hcap_display_wc_checkout();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_wc_checkout_captcha().
	 */
	public function test_hcap_verify_wc_checkout_captcha() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout' );

		WC()->init();
		wc_clear_notices();

		hcap_verify_wc_checkout_captcha();

		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test hcap_subscriber_verify() not verified.
	 */
	public function test_hcap_subscriber_verify_not_verified() {
		$expected = [
			'error' => [
				[
					'notice' => 'The Captcha is invalid.',
					'data'   => [],
				],
			],
		];

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );

		WC()->init();
		wc_clear_notices();

		hcap_verify_wc_checkout_captcha();

		self::assertSame( $expected, wc_get_notices() );
	}
}
