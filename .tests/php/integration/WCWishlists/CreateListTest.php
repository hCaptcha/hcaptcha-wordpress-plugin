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
 * WooCommerce requires PHP 7.4.
 *
 * @requires PHP >= 7.4
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
	public function test_wrapper(): void {
		$row      = '<p class="form-row">';
		$expected =
			"\n" .
			$this->get_hcap_form(
				[
					'action' => 'hcaptcha_wc_create_wishlists_action',
					'name'   => 'hcaptcha_wc_create_wishlists_nonce',
					'id'     => [
						'source'  => [ 'woocommerce-wishlists/woocommerce-wishlists.php' ],
						'form_id' => 'form',
					],
				]
			) .
			"\n" .
			$row;

		$subject = new CreateList();

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
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$valid_captcha = 'some captcha';

		$this->prepare_verify_post( 'hcaptcha_wc_create_wishlists_nonce', 'hcaptcha_wc_create_wishlists_action' );

		$subject = new CreateList();

		WC()->init();

		self::assertSame( $valid_captcha, $subject->verify( $valid_captcha ) );

		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$valid_captcha = 'some captcha';
		$expected      = [
			'error' => [
				[
					'notice' => 'The hCaptcha is invalid.',
					'data'   => [],
				],
			],
		];

		$this->prepare_verify_post( 'hcaptcha_wc_create_wishlists_nonce', 'hcaptcha_wc_create_wishlists_action', false );

		$subject = new CreateList();

		WC()->init();

		self::assertFalse( $subject->verify( $valid_captcha ) );

		self::assertSame( $expected, wc_get_notices() );
	}
}
