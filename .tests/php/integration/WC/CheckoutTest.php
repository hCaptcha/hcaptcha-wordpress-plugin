<?php
/**
 * CheckoutTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WC\Checkout;

/**
 * Test Checkout class.
 *
 * WooCommerce requires PHP 7.3.
 *
 * @requires PHP >= 7.3
 *
 * @group    wc-checkout
 * @group    wc
 */
class CheckoutTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'woocommerce/woocommerce.php';

	/**
	 * Test tear down.
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Checkout();

		self::assertSame(
			10,
			has_action( 'woocommerce_review_order_before_submit', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'woocommerce_checkout_process', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] )
		);
	}

	/**
	 * Tests add_captcha().
	 */
	public function test_add_captcha() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field(
				'hcaptcha_wc_checkout',
				'hcaptcha_wc_checkout_nonce',
				true,
				false
			);

		$subject = new Checkout();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function test_verify() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout' );

		WC()->init();
		wc_clear_notices();

		$subject = new Checkout();
		$subject->verify();

		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection*/
	public function test_verify_not_verified() {
		$expected = [
			'error' => [
				[
					'notice' => 'The hCaptcha is invalid.',
					'data'   => [],
				],
			],
		];

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );

		WC()->init();
		wc_clear_notices();

		$subject = new Checkout();
		$subject->verify();

		self::assertSame( $expected, wc_get_notices() );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts() {
		$subject = new Checkout();

		self::assertFalse( wp_script_is( 'hcaptcha-wc' ) );

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-wc' ) );
	}
}
