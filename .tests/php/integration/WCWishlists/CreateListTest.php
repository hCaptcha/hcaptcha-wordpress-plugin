<?php
/**
 * CreateListTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WCWishlists;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WCWishlists\CreateList;

/**
 * Test CreateList class.
 *
 * WooCommerce requires PHP 7.3.
 *
 * @requires PHP >= 7.3
 *
 * @group    wcwishlist
 */
class CreateListTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'woocommerce/woocommerce.php';

	/**
	 * Test before_wrapper() and after_wrapper().
	 */
	public function test_wrapper() {
		$row      = '<p class="form-row">';
		$expected =
			"\n" .
			$this->get_hcap_form( 'hcaptcha_wc_create_wishlists_action', 'hcaptcha_wc_create_wishlists_nonce' ) .
			"\n" .
			$row;
		$subject  = new CreateList();

		ob_start();

		$subject->before_wrapper();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $row;
		$subject->after_wrapper();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function verify() {
		$valid_captcha = 'some captcha';

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_create_wishlists_nonce', 'hcaptcha_wc_create_wishlists_action' );

		$subject = new CreateList();

		WC()->init();

		self::assertSame( $valid_captcha, $subject->verify( $valid_captcha ) );

		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function test_verify_not_verified() {
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

		$subject = new CreateList();

		WC()->init();

		self::assertFalse( $subject->verify( $valid_captcha ) );

		self::assertSame( $expected, wc_get_notices() );
	}
}
