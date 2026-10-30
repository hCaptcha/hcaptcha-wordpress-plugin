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

use Exception;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\WC\Checkout;
use Mockery;
use ReflectionClass;
use WC_Cart;
use WC_Payment_Gateway;
use WC_Customer;
use WooCommerce;
use WP_Error;
use WP_REST_Request;

/**
 * Test Checkout class.
 *
 * @group    wc-checkout
 * @group    wc
 */
class CheckoutTest extends WooCommerceTestCase {

	/**
	 * Test that the live WooCommerce plugin is loaded.
	 *
	 * @return void
	 */
	public function test_live_plugin_is_loaded(): void {
		$woocommerce_file = wp_normalize_path( ( new ReflectionClass( WooCommerce::class ) )->getFileName() );

		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/woocommerce/' ), $woocommerce_file );
		self::assertNotEmpty( constant( 'WC_VERSION' ) );
	}

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
		wp_dequeue_script( 'hcaptcha-wc-stripe-express' );
		wp_deregister_script( 'hcaptcha-wc-stripe-express' );

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
			20,
			has_action( 'woocommerce_checkout_before_customer_details', [ $subject, 'add_stripe_checkout_banner_captcha' ] )
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
			0,
			has_action( 'wc_stripe_rest_process_checkout', [ $subject, 'verify_stripe_rest_checkout' ] )
		);
		self::assertSame( 10, has_action( 'wc_stripe_product_before_payment_methods', [ $subject, 'mark_stripe_product_buttons' ] ) );
		self::assertSame( 10, has_action( 'woocommerce_after_add_to_cart_form', [ $subject, 'add_stripe_product_captcha' ] ) );
		self::assertSame( 10, has_action( 'wc_stripe_cart_before_payment_methods', [ $subject, 'add_stripe_cart_captcha' ] ) );
		self::assertSame( 10, has_action( 'wc_stripe_minicart_before_payment_methods', [ $subject, 'mark_stripe_minicart_buttons' ] ) );
		self::assertSame( 10, has_action( 'woocommerce_widget_shopping_cart_after_buttons', [ $subject, 'add_stripe_minicart_captcha' ] ) );
		self::assertSame( 10, has_filter( 'wc_stripe_express_checkout_skeleton', [ $subject, 'mark_stripe_checkout_banner' ] ) );
		self::assertSame( 10, has_filter( 'wc_stripe_script_dependencies', [ $subject, 'add_stripe_script_dependency' ] ) );
		self::assertSame( 0, has_action( 'init', [ $subject, 'register_stripe_express_script' ] ) );
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
	 * Test checkout entry data for classic and block requests.
	 *
	 * @return void
	 */
	public function test_checkout_entry_data(): void {
		$subject = new Checkout();
		$_POST   = [
			'billing_email'      => 'buyer@example.com',
			'billing_first_name' => 'Jane',
			'billing_last_name'  => 'Doe',
			'billing_address_1'  => 'Main Street',
			'customer_opinion'   => 'Great shop',
			'payment_method'     => 'card',
		];

		$classic = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject );
		$block   = $this->set_method_accessibility( $subject, 'get_block_entry' )->invoke(
			$subject,
			[
				'billing_address'   => [
					'email'      => 'block@example.com',
					'first_name' => 'John',
					'last_name'  => 'Smith',
					'address_1'  => 'Oak Street',
				],
				'shipping_address'  => [ 'address_1' => 'Pine Street' ],
				'additional_fields' => [ 'customer_opinion' => 'Very good' ],
				'customer_note'     => 'Please call first',
				'payment_data'      => [ 'card_number' => 'do-not-copy' ],
			]
		);

		self::assertSame( 'buyer@example.com', $classic['data']['email'] );
		self::assertSame( 'Jane Doe', $classic['data']['name'] );
		self::assertSame( 'Main Street', $classic['data']['billing_address_1'] );
		self::assertSame( 'Great shop', $classic['data']['customer_opinion'] );
		self::assertArrayNotHasKey( 'payment_method', $classic['data'] );
		self::assertSame( 'block@example.com', $block['data']['email'] );
		self::assertSame( 'John Smith', $block['data']['name'] );
		self::assertSame( 'Oak Street', $block['data']['address_1'] );
		self::assertSame( 'Pine Street', $block['data']['shipping_address']['address_1'] );
		self::assertSame( 'Very good', $block['data']['additional_fields']['customer_opinion'] );
		self::assertSame( 'Please call first', $block['data']['customer_note'] );
		self::assertArrayNotHasKey( 'payment_data', $block['data'] );

		unset( $_POST['billing_email'], $_POST['billing_first_name'], $_POST['billing_last_name'], $_POST['billing_address_1'], $_POST['customer_opinion'], $_POST['payment_method'] );
	}

	/**
	 * Tests add_captcha().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
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
		$hcaptcha = $this->get_hcap_form( $args );

		WC()->init();
		new Checkout();

		ob_start();

		wc_get_template(
			'checkout/payment.php',
			[
				'available_gateways' => [],
				'order_button_text'  => 'Place order',
			]
		);

		$output = ob_get_clean();

		self::assertStringContainsString( 'woocommerce-checkout-payment', $output );
		self::assertSame( 1, substr_count( $output, $hcaptcha ) );
		self::assertSame( 1, substr_count( $output, 'class="hcaptcha-wc-checkout-proof"' ) );
		self::assertStringNotContainsString( 'class="hcaptcha-wc-stripe-express-proof"', $output );
		self::assertLessThan( strpos( $output, 'id="place_order"' ), strpos( $output, $hcaptcha ) );
	}

	/**
	 * A native Stripe banner makes the existing checkout widget visible.
	 */
	public function test_stripe_checkout_banner_forces_one_visible_widget(): void {
		$original_settings = get_option( 'hcaptcha_settings' );
		$subject           = new Checkout();

		update_option( 'hcaptcha_settings', [ 'size' => 'invisible' ] );
		hcaptcha()->init_hooks();

		try {
			self::assertFalse( $subject->mark_stripe_checkout_banner( false ) );
			self::assertFalse( $subject->mark_stripe_checkout_banner( false, 'product' ) );
			ob_start();
			$subject->add_stripe_checkout_banner_captcha();
			self::assertSame( '', ob_get_clean() );

			ob_start();
			$subject->add_captcha();
			$without_banner = (string) ob_get_clean();

			self::assertStringContainsString( 'data-size="invisible"', $without_banner );
			self::assertSame( 1, substr_count( $without_banner, 'class="hcaptcha-wc-checkout-proof"' ) );
			self::assertStringNotContainsString( 'name="hcaptcha_wc_stripe_banner_present"', $without_banner );
			$fallback = new Checkout();
			$fallback->mark_stripe_checkout_banner( true, 'express_checkout' );
			ob_start();
			$fallback->add_captcha();
			self::assertStringContainsString( 'class="hcaptcha-wc-checkout-proof"', (string) ob_get_clean() );
			self::assertTrue( $subject->mark_stripe_checkout_banner( true, 'express_checkout' ) );

			ob_start();
			$subject->add_stripe_checkout_banner_captcha();
			$with_banner = (string) ob_get_clean();

			self::assertStringContainsString( 'data-size="normal"', $with_banner );
			self::assertStringContainsString( 'data-force="false"', $with_banner );
			self::assertSame( 1, substr_count( $with_banner, 'class="hcaptcha-wc-checkout-proof"' ) );
			self::assertSame( 1, substr_count( $with_banner, '<h-captcha' ) );
			self::assertSame( 1, substr_count( $with_banner, 'name="hcaptcha_wc_stripe_banner_present" value="1"' ) );
			self::assertStringNotContainsString( 'class="hcaptcha-wc-stripe-express-proof"', $with_banner );
			ob_start();
			$subject->add_stripe_checkout_banner_captcha();
			$subject->add_captcha();
			self::assertSame( '', ob_get_clean() );
		} finally {
			if ( false === $original_settings ) {
				delete_option( 'hcaptcha_settings' );
			} else {
				update_option( 'hcaptcha_settings', $original_settings );
			}

			hcaptcha()->init_hooks();
		}
	}

	/**
	 * The payment fragment omits its widget only when the existing checkout form has one.
	 */
	public function test_checkout_fragment_uses_actual_banner_marker(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve the test request while checking the display-only marker.
		$original_post = $_POST;
		$subject       = new Checkout();

		add_filter( 'wp_doing_ajax', '__return_true' );

		try {
			do_action( 'woocommerce_checkout_update_order_review', '' );

			$cases = [
				'with banner'     => [ 'hcaptcha_wc_stripe_banner_present=1', false ],
				'without banner'  => [ 'billing_email=buyer%40example.com', true ],
				'false marker'    => [ 'hcaptcha_wc_stripe_banner_present=0', true ],
				'array marker'    => [ 'hcaptcha_wc_stripe_banner_present%5B%5D=1', true ],
				'array post data' => [ [ 'hcaptcha_wc_stripe_banner_present' => '1' ], true ],
			];

			foreach ( $cases as $label => [ $post_data, $should_render ] ) {
				$_POST['post_data'] = $post_data;

				ob_start();
				$subject->add_captcha();
				$output = (string) ob_get_clean();

				self::assertSame( (int) $should_render, substr_count( $output, 'class="hcaptcha-wc-checkout-proof"' ), $label );
			}

			remove_filter( 'wp_doing_ajax', '__return_true' );
			add_filter( 'wp_doing_ajax', '__return_false' );
			$_POST['post_data'] = 'hcaptcha_wc_stripe_banner_present=1';

			ob_start();
			$subject->add_captcha();
			self::assertSame( 1, substr_count( (string) ob_get_clean(), 'class="hcaptcha-wc-checkout-proof"' ) );
		} finally {
			$_POST = $original_post;
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_doing_ajax', '__return_false' );
		}
	}

	/**
	 * Native Stripe hooks render distinct visible checkout-identity widgets only where buttons exist.
	 */
	public function test_stripe_express_widgets_follow_native_button_hooks(): void {
		$subject = new Checkout();
		$id      = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'checkout',
			]
		);

		ob_start();
		$subject->add_stripe_product_captcha();
		$subject->add_stripe_minicart_captcha();
		self::assertSame( '', ob_get_clean() );

		$subject->mark_stripe_product_buttons();
		ob_start();
		$subject->add_stripe_product_captcha();
		$product = (string) ob_get_clean();

		self::assertStringContainsString( 'class="hcaptcha-wc-stripe-express-proof" data-context="product"', $product );
		self::assertStringContainsString( 'data-size="normal"', $product );
		self::assertStringContainsString( 'data-force="false"', $product );
		self::assertStringContainsString( 'data-callback="hCaptchaWCStripeExpress.onProductSolved"', $product );
		self::assertStringContainsString( 'data-expired-callback="hCaptchaWCStripeExpress.onProductExpired"', $product );
		self::assertStringContainsString( 'data-error-callback="hCaptchaWCStripeExpress.onProductExpired"', $product );
		self::assertStringContainsString( 'name="hcaptcha_wc_checkout_nonce"', $product );
		self::assertStringContainsString( 'value="' . $id . '"', $product );

		ob_start();
		$subject->add_stripe_product_captcha();
		self::assertSame( '', ob_get_clean() );

		ob_start();
		$subject->add_stripe_cart_captcha();
		$cart = (string) ob_get_clean();

		self::assertStringContainsString( 'data-context="cart"', $cart );
		self::assertStringContainsString( 'data-callback="hCaptchaWCStripeExpress.onCartSolved"', $cart );
		self::assertStringContainsString( 'data-expired-callback="hCaptchaWCStripeExpress.onCartExpired"', $cart );
		self::assertStringContainsString( 'value="' . $id . '"', $cart );

		$subject->mark_stripe_minicart_buttons();
		ob_start();
		$subject->add_stripe_minicart_captcha();
		$minicart = (string) ob_get_clean();

		self::assertStringContainsString( 'data-context="minicart"', $minicart );
		self::assertStringContainsString( 'data-callback="hCaptchaWCStripeExpress.onMinicartSolved"', $minicart );
		self::assertStringContainsString( 'data-expired-callback="hCaptchaWCStripeExpress.onMinicartExpired"', $minicart );
		self::assertStringContainsString( 'value="' . $id . '"', $minicart );

		ob_start();
		$subject->add_stripe_minicart_captcha();
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * A form-specific exemption still renders a signed checkout identity for Stripe.
	 */
	public function test_stripe_express_widget_preserves_form_exemption_identity(): void {
		$subject = new Checkout();
		$filter  = static function ( bool $protect, array $source, $form_id ): bool {
			return 'checkout' === $form_id ? false : $protect;
		};

		add_filter( 'hcap_protect_form', $filter, 10, 3 );

		try {
			ob_start();
			$subject->add_stripe_cart_captcha();
			$output = (string) ob_get_clean();
		} finally {
			remove_filter( 'hcap_protect_form', $filter );
		}

		self::assertStringContainsString( 'class="hcaptcha-wc-stripe-express-proof" data-context="cart"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertStringNotContainsString( '<h-captcha', $output );
		self::assertStringNotContainsString( 'name="hcaptcha_wc_checkout_nonce"', $output );
	}

	/**
	 * The bridge loads before Stripe controllers and only with native Stripe scripts.
	 */
	public function test_stripe_express_script_dependency_and_localization(): void {
		$subject = new Checkout();
		$handle  = 'hcaptcha-wc-stripe-express';

		$subject->register_stripe_express_script();

		self::assertTrue( wp_script_is( $handle, 'registered' ) );
		self::assertFalse( wp_script_is( $handle ) );
		self::assertSame( [ 'hcaptcha', 'wp-api-fetch', 'wc-stripe-actions' ], wp_scripts()->registered[ $handle ]->deps );
		self::assertStringContainsString( 'HCaptchaWCStripeExpressObject', wp_scripts()->registered[ $handle ]->extra['data'] );
		self::assertStringContainsString( 'hcaptcha_wc_checkout_nonce', wp_scripts()->registered[ $handle ]->extra['data'] );
		self::assertSame(
			[ 'wc-stripe-actions', $handle ],
			$subject->add_stripe_script_dependency( [ 'wc-stripe-actions' ], 'wc-stripe-controllers' )
		);
		self::assertSame(
			[ 'wc-stripe-actions', $handle ],
			$subject->add_stripe_script_dependency( [ 'wc-stripe-actions', $handle ], 'wc-stripe-controllers' )
		);
		self::assertSame( [ 'wc-stripe-actions' ], $subject->add_stripe_script_dependency( [ 'wc-stripe-actions' ], 'unrelated-script' ) );
		self::assertSame( [ 'wc-stripe-actions' ], $subject->add_stripe_script_dependency( [ 'wc-stripe-actions' ] ) );
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
	 * Missing proof stops Stripe checkout before its customer-creation step.
	 */
	public function test_stripe_rest_checkout_rejects_missing_proof_before_later_hooks(): void {
		$this->prepare_verify_post( Checkout::NONCE, Checkout::ACTION, false );
		$this->prepare_widget_id();
		unset( $_POST['h-captcha-response'] );

		new Checkout();

		$continued = false;
		$later     = static function () use ( &$continued ): void {
			$continued = true;
		};

		add_action( 'wc_stripe_rest_process_checkout', $later );

		try {
			do_action( 'wc_stripe_rest_process_checkout' );
			self::fail( 'Missing hCaptcha proof must stop Stripe checkout.' );
		} catch ( Exception $exception ) {
			self::assertSame( hcap_get_error_messages()['empty'], $exception->getMessage() );
		} finally {
			remove_action( 'wc_stripe_rest_process_checkout', $later );
		}

		self::assertFalse( $continued );
	}

	/**
	 * An invalid remote proof cannot reach Stripe's customer creation.
	 */
	public function test_stripe_rest_checkout_rejects_invalid_proof(): void {
		$this->prepare_verify_post( Checkout::NONCE, Checkout::ACTION, false );
		$this->prepare_widget_id();

		$subject = new Checkout();

		$this->expectExceptionMessage( hcap_get_error_messages()['fail'] );
		$subject->verify_stripe_rest_checkout();
	}

	/**
	 * A token signed for another form cannot authorize Stripe checkout.
	 */
	public function test_stripe_rest_checkout_rejects_foreign_widget_identity(): void {
		$this->prepare_verify_post( Checkout::NONCE, Checkout::ACTION );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'login',
			]
		);

		$subject = new Checkout();

		$this->expectExceptionMessage( hcap_get_error_messages()['bad-signature'] );
		$subject->verify_stripe_rest_checkout();
	}

	/**
	 * The later WooCommerce check can reuse the same verified request proof.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_stripe_rest_checkout_reuses_valid_proof_at_woocommerce_checkout_process(): void {
		$this->prepare_verify_post( Checkout::NONCE, Checkout::ACTION );
		$this->prepare_widget_id();

		WC()->init();
		wc_clear_notices();

		$siteverify_calls = 0;
		$count_siteverify = static function ( $preempt, $parsed_args, $url ) use ( &$siteverify_calls ) {
			if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
				++$siteverify_calls;
			}

			return $preempt;
		};

		add_filter( 'pre_http_request', $count_siteverify, 9, 3 );

		try {
			$subject = new Checkout();
			$subject->verify_stripe_rest_checkout();
			$subject->verify();
		} finally {
			remove_filter( 'pre_http_request', $count_siteverify, 9 );
		}

		self::assertSame( 1, $siteverify_calls );
		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Stripe frontend checkout requests still require proof for ordinary gateways.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_checks_stripe_frontend_checkout_request(): void {
		$_GET['wc-ajax'] = 'wc_stripe_frontend_request';
		$_GET['path']    = '/wc-stripe/v1/checkout';
		$this->prepare_verify_post( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );
		$this->prepare_widget_id();

		WC()->init();
		wc_clear_notices();

		$gateway                 = new class() extends WC_Payment_Gateway {
			/**
			 * Set an available ordinary gateway identity.
			 *
			 * @noinspection PhpDynamicFieldDeclarationInspection*/
			public function __construct() {
				$this->id = 'ordinary_gateway';
			}

			/**
			 * Make the synthetic gateway available.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function is_available(): bool {
				return true;
			}
		};
		$add_gateway             = static function ( array $gateways ) use ( $gateway ): array {
			$gateways[ $gateway->id ] = $gateway;

			return $gateways;
		};
		$original_cart           = WC()->cart;
		WC()->cart               = new class() extends WC_Cart {
			/**
			 * Simulate a paid cart.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function needs_payment(): bool {
				return true;
			}
		};
		$_POST['payment_method'] = $gateway->id;
		$subject                 = new Checkout();
		$callback                = static function () use ( $subject ): void {
			$subject->verify();
		};

		add_filter( 'woocommerce_available_payment_gateways', $add_gateway );
		add_action( 'wc_ajax_wc_stripe_frontend_request', $callback );
		try {
			do_action( 'wc_ajax_wc_stripe_frontend_request' );
		} finally {
			WC()->cart = $original_cart;
			remove_action( 'wc_ajax_wc_stripe_frontend_request', $callback );
			remove_filter( 'woocommerce_available_payment_gateways', $add_gateway );
		}

		self::assertSame( 'The hCaptcha is invalid.', wc_get_notices( 'error' )[0]['notice'] );
	}

	/**
	 * Stripe labels do not exempt the earlier native classic place-order handler.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_does_not_trust_stripe_labels_before_dispatch(): void {
		$_GET['wc-ajax']                           = 'wc_stripe_frontend_request';
		$_GET['path']                              = '/wc-stripe/v1/checkout';
		$_POST['woocommerce_checkout_place_order'] = 'Place order';

		$this->prepare_verify_post( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );
		$this->prepare_widget_id();

		WC()->init();
		wc_clear_notices();

		( new Checkout() )->verify();

		self::assertSame( 'The hCaptcha is invalid.', wc_get_notices( 'error' )[0]['notice'] );
	}

	/**
	 * Legacy Stripe action and an enabled express gateway do not bypass hCaptcha.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_legacy_stripe_action_checks_paid_express_gateway(): void {
		$_GET['wc-ajax'] = 'wc_stripe_frontend_request';
		$_GET['path']    = '/wc-stripe/v1/checkout';

		$gateway       = new class() extends WC_Payment_Gateway {
			/**
			 * Set synthetic gateway identity and express capability.
			 *
			 * @noinspection PhpDynamicFieldDeclarationInspection*/
			public function __construct() {
				$this->id       = 'legacy_product_express';
				$this->supports = [ 'wc_stripe_product_checkout' ];
			}

			/**
			 * Make the synthetic gateway available.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function is_available(): bool {
				return true;
			}

			/**
			 * Enable the product-page express button only.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function product_checkout_enabled(): bool {
				return true;
			}
		};
		$add_gateway   = static function ( array $gateways ) use ( $gateway ): array {
			$gateways[ $gateway->id ] = $gateway;

			return $gateways;
		};
		$original_cart = WC()->cart;
		$subject       = new Checkout();
		$run_checkout  = static function () use ( $subject ): void {
			$subject->verify();
		};

		add_filter( 'woocommerce_available_payment_gateways', $add_gateway );
		add_action( 'wc_ajax_wc_stripe_frontend_request', $run_checkout );

		try {
			$_POST['payment_method'] = $gateway->id;
			$this->prepare_verify_post( Checkout::NONCE, Checkout::ACTION, false );
			$this->prepare_widget_id();
			unset( $_POST['h-captcha-response'] );
			WC()->cart = new class() extends WC_Cart {
				/**
				 * Simulate a paid cart.
				 *
				 * @return bool
				 * @noinspection PhpUnused
				 */
				public function needs_payment(): bool {
					return true;
				}
			};
			wc_clear_notices();
			do_action( 'wc_ajax_wc_stripe_frontend_request' );
			self::assertSame( hcap_get_error_messages()['empty'], wc_get_notices( 'error' )[0]['notice'] );
		} finally {
			WC()->cart = $original_cart;
			remove_action( 'wc_ajax_wc_stripe_frontend_request', $run_checkout );
			remove_filter( 'woocommerce_available_payment_gateways', $add_gateway );
		}
	}

	/**
	 * A valid checkout proof still works inside the legacy Stripe AJAX action.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_legacy_stripe_action_accepts_valid_checkout_proof(): void {
		$_GET['wc-ajax'] = 'wc_stripe_frontend_request';
		$_GET['path']    = '/wc-stripe/v1/checkout';

		$_POST['payment_method'] = 'express_gateway';

		$this->mock_express_payment_gateway();
		$this->prepare_verify_post( Checkout::NONCE, Checkout::ACTION );
		$this->prepare_widget_id();

		WC()->init();
		wc_clear_notices();

		$original_cart = WC()->cart;
		$this->set_test_cart_needs_payment( true );
		$subject          = new Checkout();
		$siteverify_calls = 0;
		$count_siteverify = static function ( $preempt, $parsed_args, $url ) use ( &$siteverify_calls ) {
			if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
				++$siteverify_calls;
			}

			return $preempt;
		};
		$run_checkout     = static function () use ( $subject ): void {
			$subject->verify();
		};

		add_filter( 'pre_http_request', $count_siteverify, 9, 3 );
		add_action( 'wc_ajax_wc_stripe_frontend_request', $run_checkout );

		try {
			do_action( 'wc_ajax_wc_stripe_frontend_request' );
			self::assertSame( [], wc_get_notices() );
			self::assertSame( 1, $siteverify_calls );
		} finally {
			WC()->cart = $original_cart;
			remove_action( 'wc_ajax_wc_stripe_frontend_request', $run_checkout );
			remove_filter( 'pre_http_request', $count_siteverify, 9 );
		}
	}

	/**
	 * A paid classic checkout cannot use a banner gateway ID to skip CAPTCHA.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_checks_express_gateway_on_paid_cart(): void {
		$_POST['payment_method'] = 'express_gateway';

		$this->mock_express_payment_gateway();
		$this->prepare_verify_post( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );
		$this->prepare_widget_id();

		WC()->init();
		wc_clear_notices();
		$original_cart = WC()->cart;
		$this->set_test_cart_needs_payment( true );

		try {
			( new Checkout() )->verify();

			self::assertSame( 'The hCaptcha is invalid.', wc_get_notices( 'error' )[0]['notice'] );
		} finally {
			WC()->cart = $original_cart;
		}
	}

	/**
	 * A zero-total classic checkout cannot use a banner gateway ID to skip CAPTCHA.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_checks_express_gateway_on_free_cart(): void {
		$_POST['payment_method'] = 'express_gateway';
		$this->mock_express_payment_gateway();
		$this->prepare_verify_post( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );
		$this->prepare_widget_id();

		WC()->init();
		wc_clear_notices();
		$original_cart = WC()->cart;
		$this->set_test_cart_needs_payment( false );

		try {
			( new Checkout() )->verify();

			self::assertSame( 'The hCaptcha is invalid.', wc_get_notices( 'error' )[0]['notice'] );
		} finally {
			WC()->cart = $original_cart;
		}
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
		$hp_sig = $_POST[ $hp_sig_name ];
		$token  = $_POST[ $token_name ];

		$request->set_param( $widget_id_name, $this->get_test_widget_id() );
		$request->set_param( $hcaptcha_response_name, $hcaptcha_response );
		$request->set_param( $hp_sig_name, $hp_sig );
		$request->set_param( $token_name, $token );
		$request->set_param( $hp_name, '' );

		self::assertSame( $response, $subject->verify_block( $response, $handler, $request ) );
		self::assertArrayNotHasKey( $widget_id_name, $_POST );
		self::assertArrayNotHasKey( $hcaptcha_response_name, $_POST );
		self::assertArrayNotHasKey( $hp_sig_name, $_POST );
		self::assertArrayNotHasKey( $token_name, $_POST );
		self::assertArrayNotHasKey( $hp_name, $_POST );

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
		$request->set_param( $hp_sig_name, $hp_sig );
		$request->set_param( $token_name, $token );
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
		$request->set_param( $hp_sig_name, $hp_sig );
		$request->set_param( $token_name, $token );
		$request->set_param( $hp_name, '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$expected = new WP_Error(
			'fail',
			'The hCaptcha is invalid.',
			[ 'status' => 400 ]
		);

		self::assertEquals( $expected, $subject->verify_block( $response, $handler, $request ) );
	}

	/**
	 * A partial Store API checkout checks the effective retained billing address.
	 *
	 * @param bool $replace_email Whether the request supplies an allowed replacement.
	 *
	 * @return void
	 * @dataProvider dp_partial_checkout_email
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_partial_block_checkout_checks_effective_email( bool $replace_email ): void {
		$previous_customer = WC()->customer;
		$customer          = new WC_Customer();
		$customer->set_billing_email( 'buyer@blocked.test' );
		WC()->customer = $customer;
		$this->prepare_verify_request( 'valid-checkout-response' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_header( 'Content-Type', 'application/json' );
		$billing = $replace_email ? [ 'email' => 'buyer@allowed.test' ] : [ 'first_name' => 'Buyer' ];
		$request->set_body( wp_json_encode( [ 'billing_address' => $billing ] ) );
		$request->set_param( 'hcaptcha-widget-id', $this->get_test_widget_id() );
		$request->set_param( 'h-captcha-response', 'valid-checkout-response' );
		$request->set_param( 'hcap_hp_test', '' );
		$request->set_param( 'hcap_hp_sig', wp_create_nonce( 'hcap_hp_test' ) );
		$request->set_param( 'hcap_fst_token', 'test_token' );
		$checked = [];
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'buyer@blocked.test' === $address;
			},
			10,
			2
		);

		try {
			$result = ( new Checkout() )->verify_block( 'native response', [], $request );

			if ( $replace_email ) {
				self::assertSame( 'native response', $result );
			} else {
				$this->assertWPError( $result );
				self::assertSame( 'Please use a permanent email address.', $result->get_error_message( 'disposable-email' ) );
			}

			self::assertSame( [ $replace_email ? 'buyer@allowed.test' : 'buyer@blocked.test' ], $checked );
			self::assertSame( 'buyer@blocked.test', $customer->get_billing_email() );
		} finally {
			WC()->customer = $previous_customer;
		}
	}

	/**
	 * Provide partial checkout email updates.
	 *
	 * @return array
	 */
	public function dp_partial_checkout_email(): array {
		return [
			'retained billing'     => [ false ],
			'supplied replacement' => [ true ],
		];
	}

	/**
	 * A paid Store API checkout cannot use a banner gateway ID to skip CAPTCHA.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_block_checks_express_gateway_on_paid_cart(): void {
		$response = 'some response';
		$handler  = [];
		$request  = new WP_REST_Request( '', '/wc/store/v1/checkout' );

		$request->set_method( 'POST' );
		$request->set_body( wp_json_encode( [ 'payment_method' => 'express_gateway' ] ) );

		$this->mock_express_payment_gateway();
		$this->prepare_verify_request( '', false );
		$request->set_param( 'hcaptcha-widget-id', $this->get_test_widget_id() );
		$request->set_param( 'h-captcha-response', '' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$request->set_param( 'hcap_hp_sig', $_POST['hcap_hp_sig'] );
		$request->set_param( 'hcap_fst_token', $_POST['hcap_fst_token'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$request->set_param( 'hcap_hp_test', '' );

		$original_cart = WC()->cart;
		$this->set_test_cart_needs_payment( true );

		try {
			$result = ( new Checkout() )->verify_block( $response, $handler, $request );
			$this->assertWPError( $result );
			self::assertSame( hcap_get_error_messages()['empty'], $result->get_error_message() );
		} finally {
			WC()->cart = $original_cart;
		}
	}

	/**
	 * A zero-total Store API checkout cannot use a banner gateway ID to skip CAPTCHA.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_block_checks_express_gateway_on_free_cart(): void {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_body( wp_json_encode( [ 'payment_method' => 'express_gateway' ] ) );
		$this->mock_express_payment_gateway();
		$this->prepare_verify_request( '', false );
		$request->set_param( 'hcaptcha-widget-id', $this->get_test_widget_id() );
		$request->set_param( 'h-captcha-response', '' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$request->set_param( 'hcap_hp_sig', $_POST['hcap_hp_sig'] );
		$request->set_param( 'hcap_fst_token', $_POST['hcap_fst_token'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$request->set_param( 'hcap_hp_test', '' );

		$original_cart = WC()->cart;
		$this->set_test_cart_needs_payment( false );

		try {
			$result = ( new Checkout() )->verify_block( 'native response', [], $request );

			$this->assertWPError( $result );
			self::assertSame( hcap_get_error_messages()['empty'], $result->get_error_message() );
		} finally {
			WC()->cart = $original_cart;
		}
	}

	/**
	 * An empty payment method still requires CAPTCHA without loading gateways.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_empty_payment_method_checks_captcha_without_loading_gateways(): void {
		$gateways_loaded = false;
		$filter          = static function ( array $gateways ) use ( &$gateways_loaded ): array {
			$gateways_loaded = true;

			return $gateways;
		};

		add_filter( 'woocommerce_available_payment_gateways', $filter );

		try {
			$_POST['payment_method'] = '';
			$this->prepare_verify_post( 'hcaptcha_wc_checkout_nonce', 'hcaptcha_wc_checkout', false );
			$this->prepare_widget_id();
			WC()->init();
			wc_clear_notices();
			( new Checkout() )->verify();
			self::assertSame( 'The hCaptcha is invalid.', wc_get_notices( 'error' )[0]['notice'] );
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
			[ 'status' => 400 ]
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
	 * Set a synthetic cart with a fixed payment requirement.
	 *
	 * @param bool $needs_payment Whether the cart needs payment.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function set_test_cart_needs_payment( bool $needs_payment ): void {
		WC()->cart = new class( $needs_payment ) extends WC_Cart {
			/**
			 * Whether payment is needed.
			 *
			 * @var bool
			 */
			private bool $needs_payment;

			/**
			 * Set the payment requirement.
			 *
			 * @param bool $needs_payment Whether payment is needed.
			 */
			public function __construct( bool $needs_payment ) {
				$this->needs_payment = $needs_payment;
			}

			/**
			 * Report the payment requirement.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function needs_payment(): bool {
				return $this->needs_payment;
			}
		};
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
