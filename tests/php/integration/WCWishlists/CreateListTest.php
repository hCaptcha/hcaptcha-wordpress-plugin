<?php
/**
 * CreateListTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WCWishlists;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WCWishlists\CreateList;

/**
 * Test CreateList class.
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
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'woocommerce_wishlists_status', 'create_list' );
		hcaptcha()->modules['WooCommerce Wishlists'] = [
			[ 'woocommerce_wishlists_status', 'create_list' ],
			'woocommerce-wishlists/woocommerce-wishlists.php',
			CreateList::class,
		];
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

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

		$output = (string) ob_get_clean();

		self::assertSame( $expected, $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
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

	/**
	 * Test verify() with a filled honeypot.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_filled_honeypot(): void {
		$this->prepare_verify_post( 'hcaptcha_wc_create_wishlists_nonce', 'hcaptcha_wc_create_wishlists_action' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce-wishlists/woocommerce-wishlists.php' ],
				'form_id' => 'form',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		WC()->init();
		wc_clear_notices();

		self::assertFalse( ( new CreateList() )->verify( true ) );
		self::assertSame( 'Anti-spam check failed.', wc_get_notices( 'error' )[0]['notice'] );
	}
}
