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
use Mockery;
use WP_Error;
use WP_REST_Request;

/**
 * Test Checkout class.
 *
 * WooCommerce requires PHP 7.4.
 *
 * @requires PHP >= 7.4
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
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		if ( did_action( 'woocommerce_init' ) ) {
			wc_clear_notices();
		}

		wp_dequeue_script( 'hcaptcha-wc-checkout' );
		wp_dequeue_script( 'hcaptcha-wc-block-checkout' );

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
			has_filter( 'render_block', [ $subject, 'add_block_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'woocommerce_checkout_process', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_filter( 'rest_request_before_callbacks', [ $subject, 'verify_block' ] )
		);
		self::assertSame(
			9,
			has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] )
		);
	}

	/**
	 * Tests add_captcha().
	 */
	public function test_add_captcha() {
		$args     = [
			'action' => 'hcaptcha_wc_checkout',
			'name'   => 'hcaptcha_wc_checkout_nonce',
			'id'     => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'checkout',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Checkout();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Tests add_captcha().
	 */
	public function test_add_block_captcha() {
		$content1       = 'some block content 1';
		$checkout_block = '<div data-block-name="woocommerce/checkout-actions-block" class="wp-block-woocommerce-checkout-actions-block"></div>';
		$content2       = 'some block content 2';
		$block_content  = $content1 . $checkout_block . $content2;
		$block          = [
			'blockName' => 'some',
		];
		$args           = [
			'action' => 'hcaptcha_wc_checkout',
			'name'   => 'hcaptcha_wc_checkout_nonce',
			'id'     => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'checkout',
			],
		];
		$expected       = $content1 . $this->get_hcap_form( $args ) . $checkout_block . $content2;
		$instance       = Mockery::mock( 'WP_Block' );

		$subject = new Checkout();

		// Not a checkout block.
		self::assertSame( $block_content, $subject->add_block_captcha( $block_content, $block, $instance ) );

		$block['blockName'] = 'woocommerce/checkout';

		// Checkout block.
		self::assertSame( $expected, $subject->add_block_captcha( $block_content, $block, $instance ) );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
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
	 * @noinspection PhpUndefinedFunctionInspection
	 */
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
	 * Test verify_block().
	 *
	 * @return void
	 */
	public function test_verify_block() {
		$widget_id_name         = 'hcaptcha-widget-id';
		$hcaptcha_response_name = 'h-captcha-response';
		$hcaptcha_response      = 'some hcaptcha response';
		$response               = 'some response';
		$handler                = [];

		$subject = new Checkout();

		// Not a checkout route.
		$request = new WP_REST_Request( '', 'some route' );

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );

		// Checkout route, verified.
		$request = new WP_REST_Request( '', '/wc/store/v1/checkout' );

		$request->set_param( $widget_id_name, [ 'some widget' ] );
		$request->set_param( $hcaptcha_response_name, $hcaptcha_response );

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );

		// Checkout route, not verified.
		hcaptcha()->has_result = false;
		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

		$expected = new WP_Error(
			'fail',
			'The hCaptcha is invalid.',
			400
		);

		self::assertEquals( $expected, $subject->verify_block( $response, $handler, $request ) );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts() {
		$subject = new Checkout();

		self::assertFalse( wp_script_is( 'hcaptcha-wc-checkout' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-wc-block-checkout' ) );

		ob_start();
		$subject->add_captcha();
		ob_end_clean();

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-wc-checkout' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-wc-block-checkout' ) );

		$subject->add_block_captcha(
			'',
			[ 'blockName' => 'woocommerce/checkout' ],
			Mockery::mock( 'WP_Block' )
		);

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-wc-checkout' ) );
		self::assertTrue( wp_script_is( 'hcaptcha-wc-block-checkout' ) );
	}

	/**
	 * Test enqueue_scripts() when captcha was NOT added.
	 */
	public function test_enqueue_scripts_when_captcha_was_NOT_added() {
		$subject = new Checkout();

		self::assertFalse( wp_script_is( 'hcaptcha-wc-checkout' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-wc-block-checkout' ) );

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-wc-checkout' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-wc-block-checkout' ) );
	}
}
