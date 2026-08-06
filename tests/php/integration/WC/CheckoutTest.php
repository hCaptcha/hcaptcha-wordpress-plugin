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

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WC\Checkout;
use Mockery;
use ReflectionException;
use WC_Payment_Gateway;
use WP_Error;
use WP_REST_Request;

/**
 * Test Checkout class.
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
	public function tearDown(): void {
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
	public function test_constructor_and_init_hooks(): void {
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
	public function test_add_captcha(): void {
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
	 * Test add_captcha() with built-in form interaction.
	 */
	public function test_add_captcha_with_form_interaction(): void {
		$subject = new Checkout();
		$level   = ob_get_level();

		add_filter( 'hcap_delay_api_event', '__return_true' );

		try {
			ob_start();
			$subject->add_captcha();
			$actual = (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			remove_filter( 'hcap_delay_api_event', '__return_true' );
		}

		self::assertStringContainsString(
			'class="h-captcha hcaptcha-api-delayed"',
			$actual
		);
	}

	/**
	 * Tests add_captcha().
	 */
	public function test_add_block_captcha(): void {
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

		$checkout_block = '<div data-block-name="woocommerce/checkout-actions-block" data-show-return-to-cart="true" class="wp-block-woocommerce-checkout-actions-block"></div>';
		$block_content  = $content1 . $checkout_block . $content2;
		$expected       = $content1 . $this->get_hcap_form( $args ) . $checkout_block . $content2;

		// Checkout block with the return-to-cart link enabled.
		self::assertSame( $expected, $subject->add_block_captcha( $block_content, $block, $instance ) );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout' );
		$this->prepare_widget_id();

		WC()->init();
		wc_clear_notices();

		$subject = new Checkout();
		$subject->verify();

		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test verify() skips Stripe frontend checkout request.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_skips_stripe_frontend_checkout_request(): void {
		$_GET['wc-ajax'] = 'wc_stripe_frontend_request';
		$_GET['path']    = '/wc-stripe/v1/checkout';

		WC()->init();
		wc_clear_notices();

		$subject = new Checkout();
		$subject->verify();

		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test verify() skips express payment methods.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_skips_express_payment_method(): void {
		$_POST['payment_method'] = 'express_gateway';

		$this->mock_express_payment_gateway();

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
	public function test_verify_not_verified(): void {
		$expected = [
			'error' => [
				[
					'notice' => 'The hCaptcha is invalid.',
					'data'   => [],
				],
			],
		];

		$this->prepare_verify_post( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );
		$this->prepare_widget_id();

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
	public function test_verify_block(): void {
		$widget_id_name         = 'hcaptcha-widget-id';
		$hcaptcha_response_name = 'h-captcha-response';
		$hp_sig_name            = 'hcap_hp_sig';
		$token_name             = 'hcap_fst_token';
		$hp_name                = 'hcap_hp_test';
		$hcaptcha_response      = 'some hcaptcha response';
		$response               = 'some response';
		$handler                = [];

		$subject = new Checkout();

		// Not a checkout route.
		$request = new WP_REST_Request( '', 'some route' );

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );

		// Checkout route, verified.
		$request = new WP_REST_Request( '', '/wc/store/v1/checkout' );
		$request->set_method( 'GET' );

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );

		$request = new WP_REST_Request( '', '/wc/store/v1/checkout' );
		$request->set_method( 'POST' );
		$request->set_body( wp_json_encode( [ 'payment_data' => [] ] ) );

		$this->prepare_verify_request( $hcaptcha_response );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$request->set_param( $widget_id_name, $this->get_test_widget_id() );
		$request->set_param( $hcaptcha_response_name, $hcaptcha_response );
		$request->set_param( $hp_sig_name, $_POST[ $hp_sig_name ] );
		$request->set_param( $token_name, $_POST[ $token_name ] );
		$request->set_param( $hp_name, '' );

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );

		// Checkout route, express payment type.
		$request = new WP_REST_Request( '', '/wc/store/v1/checkout' );
		$request->set_method( 'POST' );
		$request->set_body(
			wp_json_encode(
				[
					'payment_data' => [
						[
							'key'   => 'express_payment_type',
							'value' => 'example',
						],
					],
				]
			)
		);
		$request->set_param( $widget_id_name, $this->get_test_widget_id() );
		$request->set_param( $hcaptcha_response_name, $hcaptcha_response );
		$request->set_param( $hp_sig_name, $_POST[ $hp_sig_name ] );
		$request->set_param( $token_name, $_POST[ $token_name ] );
		$request->set_param( $hp_name, '' );

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );

		// Checkout route, not verified.
		$request = new WP_REST_Request( '', '/wc/store/v1/checkout' );
		$request->set_method( 'POST' );
		$request->set_body( wp_json_encode( [ 'payment_data' => [] ] ) );

		hcaptcha()->has_result = false;
		$this->prepare_verify_request( $hcaptcha_response, false );
		$request->set_param( $widget_id_name, $this->get_test_widget_id() );
		$request->set_param( $hcaptcha_response_name, $hcaptcha_response );
		$request->set_param( $hp_sig_name, $_POST[ $hp_sig_name ] );
		$request->set_param( $token_name, $_POST[ $token_name ] );
		$request->set_param( $hp_name, '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$expected = new WP_Error(
			'fail',
			'The hCaptcha is invalid.',
			400
		);

		self::assertEquals( $expected, $subject->verify_block( $response, $handler, $request ) );
	}

	/**
	 * Test verify_block() skips express payment methods.
	 *
	 * @return void
	 */
	public function test_verify_block_skips_express_payment_method(): void {
		$response = 'some response';
		$handler  = [];
		$request  = new WP_REST_Request( '', '/wc/store/v1/checkout' );

		$request->set_method( 'POST' );
		$request->set_body( wp_json_encode( [ 'payment_method' => 'express_gateway' ] ) );

		$this->mock_express_payment_gateway();

		$subject = new Checkout();

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );
	}

	/**
	 * Test that an empty payment method does not load available gateways.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_empty_payment_method_skips_loading_gateways(): void {
		$gateways_loaded = false;
		$filter          = static function ( array $gateways ) use ( &$gateways_loaded ): array {
			$gateways_loaded = true;

			return $gateways;
		};

		add_filter( 'woocommerce_available_payment_gateways', $filter );

		try {
			$subject = new Checkout();
			$method  = $this->set_method_accessibility( $subject, 'is_express_payment_method' );

			self::assertFalse( $method->invoke( $subject, '' ) );
		} finally {
			remove_filter( 'woocommerce_available_payment_gateways', $filter );
		}

		self::assertFalse( $gateways_loaded );
	}

	/**
	 * Test verify_block() error code mapping with a nested payment method.
	 *
	 * @return void
	 */
	public function test_verify_block_error_code_mapping(): void {
		$widget_id_name         = 'hcaptcha-widget-id';
		$hcaptcha_response_name = 'h-captcha-response';
		$hp_sig_name            = 'hcap_hp_sig';
		$token_name             = 'hcap_fst_token';
		$hp_name                = 'hcap_hp_test';
		$hcaptcha_response      = '';
		$response               = 'some response';
		$handler                = [];

		$subject = new Checkout();

		$request = new WP_REST_Request( '', '/wc/store/v1/checkout' );
		$request->set_method( 'POST' );
		$request->set_body(
			wp_json_encode(
				[
					'payment_method' => [],
					'payment_data'   => [],
				]
			)
		);

		$this->prepare_verify_request( $hcaptcha_response, false );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$request->set_param( $widget_id_name, $this->get_test_widget_id() );
		$request->set_param( $hcaptcha_response_name, $hcaptcha_response );
		$request->set_param( $hp_sig_name, $_POST[ $hp_sig_name ] );
		$request->set_param( $token_name, $_POST[ $token_name ] );
		$request->set_param( $hp_name, '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$expected = new WP_Error(
			'empty',
			hcap_get_error_messages()['empty'],
			400
		);

		self::assertEquals( $expected, $subject->verify_block( $response, $handler, $request ) );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts(): void {
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
			'<div data-block-name="woocommerce/checkout-actions-block" class="wp-block-woocommerce-checkout-actions-block"></div>',
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
	public function test_enqueue_scripts_when_captcha_was_NOT_added(): void {
		$subject = new Checkout();

		self::assertFalse( wp_script_is( 'hcaptcha-wc-checkout' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-wc-block-checkout' ) );

		$subject->add_block_captcha(
			'',
			[ 'blockName' => 'woocommerce/checkout' ],
			Mockery::mock( 'WP_Block' )
		);

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-wc-checkout' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-wc-block-checkout' ) );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag      = '<script src="https://test.test/a.js">some</script>';
		$expected = '<script type="module" src="https://test.test/a.js">some</script>';
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$subject = new Checkout();

		// Wrong tag.
		self::assertSame( $tag, $subject->add_type_module( $tag, 'some-handle', '' ) );

		// Proper tag.
		self::assertSame( $expected, $subject->add_type_module( $tag, 'hcaptcha-wc-block-checkout', '' ) );
	}

	/**
	 * Add a WooCommerce express payment gateway.
	 *
	 * @return void
	 */
	private function mock_express_payment_gateway(): void {
		$gateway = new class() extends WC_Payment_Gateway {

			/**
			 * Constructor.
			 *
			 * @noinspection PhpDynamicFieldDeclarationInspection
			 */
			public function __construct() {
				$this->id       = 'express_gateway';
				$this->supports = [ 'wc_stripe_banner_checkout' ];
			}

			/**
			 * Whether the gateway is available.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function is_available(): bool {
				return true;
			}

			/**
			 * Whether banner checkout is enabled.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function banner_checkout_enabled(): bool {
				return true;
			}
		};

		add_filter(
			'woocommerce_available_payment_gateways',
			static function ( array $gateways ) use ( $gateway ): array {
				$gateways[ $gateway->id ] = $gateway;

				return $gateways;
			}
		);
	}

	/**
	 * Prepare widget id.
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->get_test_widget_id();
	}

	/**
	 * Get test widget id.
	 *
	 * @return string
	 */
	private function get_test_widget_id(): string {
		return HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'checkout',
			]
		);
	}
}
