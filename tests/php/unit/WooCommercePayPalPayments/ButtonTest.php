<?php
/**
 * ButtonTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\WooCommercePayPalPayments;

use HCaptcha\Tests\Unit\HCaptchaTestCase;
use HCaptcha\WC\Checkout;
use HCaptcha\WooCommercePayPalPayments\Button;
use Mockery;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class ButtonTest
 *
 * @group woocommerce-paypal-payments
 */
class ButtonTest extends HCaptchaTestCase {
	/**
	 * Create a button without registering hooks.
	 *
	 * @return Button
	 */
	private function create_subject(): Button {
		return ( new ReflectionClass( Button::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Test the constructor registers its first action.
	 */
	public function test_constructor(): void {
		$subject = $this->create_subject();
		WP_Mock::expectActionAdded( 'ppcp_start_button_wrapper_ppcp_gateway', [ $subject, 'add_captcha' ] );
		( new ReflectionClass( Button::class ) )->getConstructor()->invoke( $subject );
	}

	/**
	 * Test the PayPal captcha is rendered and only added once.
	 */
	public function test_add_captcha_and_block_once(): void {
		FunctionMocker::replace( 'function_exists', false );
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\HCaptcha::form_display' );

		$subject = $this->create_subject();
		ob_start();
		$subject->add_captcha();
		$output = ob_get_clean();

		self::assertStringContainsString( 'hcaptcha-woocommerce-paypal-payments', $output );
		self::assertSame( 'content', $subject->add_block_captcha( 'content' ) );
	}

	/**
	 * Test checkout protection supplies the captcha instead of the PayPal button.
	 */
	public function test_add_captcha_uses_checkout_protection(): void {
		FunctionMocker::replace( 'function_exists', true );
		WP_Mock::userFunction( 'is_checkout_pay_page' )->andReturn( false );
		WP_Mock::userFunction( 'is_checkout' )->andReturn( true );
		$settings = Mockery::mock();
		$settings->shouldReceive( 'is' )->with( 'woocommerce_status', 'checkout' )->once()->andReturn( true );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->once()->andReturn( $settings );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );

		$subject = $this->create_subject();
		ob_start();
		$subject->add_captcha();
		self::assertSame( '', ob_get_clean() );
		self::assertSame( 'content', $subject->add_block_captcha( 'content' ) );
	}

	/**
	 * Test checkout block protection marks the PayPal captcha as present.
	 */
	public function test_add_checkout_block_captcha_with_checkout_protection(): void {
		$settings = Mockery::mock();
		$settings->shouldReceive( 'is' )->with( 'woocommerce_status', 'checkout' )->once()->andReturn( true );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->once()->andReturn( $settings );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );

		$subject = $this->create_subject();
		self::assertSame( 'content', $subject->add_checkout_block_captcha( 'content' ) );
		self::assertSame( 'content', $subject->add_block_captcha( 'content' ) );
	}

	/**
	 * Test the inline stylesheet delegates to the captcha helper.
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::css_display',
			static function ( $css ) {
				self::assertStringContainsString( 'margin-top: 0.7rem', $css );
			}
		);

		$this->create_subject()->print_inline_styles();
	}

	/**
	 * Test both PayPal script enqueue paths.
	 */
	public function test_enqueue_scripts(): void {
		if ( ! defined( 'HCAPTCHA_URL' ) ) {
			define( 'HCAPTCHA_URL', HCAPTCHA_TEST_URL );
		}
		if ( ! defined( 'HCAPTCHA_VERSION' ) ) {
			define( 'HCAPTCHA_VERSION', '1.0.0' );
		}
		WP_Mock::userFunction( 'hcap_min_suffix' )->andReturn( '.min' );
		WP_Mock::userFunction( 'wp_enqueue_script' )->twice();
		WP_Mock::userFunction( 'wp_script_is' )->andReturnUsing(
			static function ( $handle ) {
				return 'ppcp-smart-button' === $handle;
			}
		);

		$subject = $this->create_subject();
		$subject->enqueue_early_scripts();
		$subject->enqueue_scripts();
	}

	/**
	 * Test no PayPal script is queued without a captcha or PayPal button.
	 */
	public function test_enqueue_scripts_without_paypal_button(): void {
		WP_Mock::userFunction( 'wp_script_is' )->twice()->andReturn( false );
		WP_Mock::userFunction( 'wp_enqueue_script' )->never();

		$this->create_subject()->enqueue_scripts();
	}

	/**
	 * Test script status and loader tag filtering.
	 */
	public function test_script_filters(): void {
		WP_Mock::userFunction( 'wp_script_is' )->andReturn( true, false );
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\HCaptcha::add_type_module', '<script type="module">' );

		$subject = $this->create_subject();
		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
		self::assertSame( '<script>', $subject->add_type_module( '<script>', 'other', '' ) );
		self::assertSame( '<script type="module">', $subject->add_type_module( '<script>', 'hcaptcha-woocommerce-paypal-payments', '' ) );
	}

	/**
	 * Require the shared checkout challenge only for its own form.
	 */
	public function test_require_checkout_challenge(): void {
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\HCaptcha::get_class_source', [ 'checkout-source' ] );
		$subject = $this->create_subject();
		$args    = [
			'id' => [
				'form_id' => 'checkout',
				'source'  => [ 'checkout-source' ],
			],
		];

		self::assertSame( $args + [ 'mandatory' => true ], $subject->require_checkout_challenge( $args ) );
		self::assertSame(
			[
				'id' => [
					'form_id' => 'cart',
					'source'  => [ 'checkout-source' ],
				],
			],
			$subject->require_checkout_challenge(
				[
					'id' => [
						'form_id' => 'cart',
						'source'  => [ 'checkout-source' ],
					],
				]
			)
		);
	}

	/**
	 * Read payer emails from encoded and structured PayPal requests.
	 */
	public function test_get_email_entry_from_native_request(): void {
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing(
			static function ( $value ) {
				return $value;
			}
		);
		$subject = $this->create_subject();
		$method  = $this->set_method_accessibility( $subject, 'get_email_entry' );

		$encoded = $method->invoke(
			$subject,
			[
				'context'      => 'cart',
				'form_encoded' => 'billing_email=encoded%40example.test',
			]
		);
		$payer   = $method->invoke(
			$subject,
			[
				'context' => 'cart',
				'payer'   => [ 'email_address' => 'payer@example.test' ],
			]
		);

		self::assertSame( [ 'encoded@example.test' ], $encoded['email_addresses'] );
		self::assertSame( [ 'payer@example.test' ], $payer['email_addresses'] );
	}

	/**
	 * Use an order email only when WooCommerce validates the key and capability.
	 */
	public function test_get_order_email_after_native_checks(): void {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'key_is_valid' )->with( 'order-key' )->twice()->andReturn( false, true );
		$order->shouldReceive( 'get_billing_email' )->once()->andReturn( 'buyer@example.test' );

		FunctionMocker::replace( 'function_exists', true );
		WP_Mock::userFunction( 'wc_get_order' )->with( 42 )->twice()->andReturn( $order );
		WP_Mock::userFunction( 'current_user_can' )->with( 'pay_for_order', 42 )->once()->andReturn( true );

		$subject = $this->create_subject();
		$method  = $this->set_method_accessibility( $subject, 'get_order_email' );

		self::assertSame(
			'',
			$method->invoke(
				$subject,
				[
					'order_id'  => 42,
					'order_key' => 'order-key',
				]
			)
		);
		self::assertSame(
			'buyer@example.test',
			$method->invoke(
				$subject,
				[
					'order_id'  => 42,
					'order_key' => 'order-key',
				]
			)
		);
	}

	/**
	 * A pay-now request without an order cannot supply a billing email.
	 */
	public function test_get_email_entry_without_pay_now_order(): void {
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing(
			static function ( $value ) {
				return $value;
			}
		);
		FunctionMocker::replace( 'function_exists', false );
		$subject = $this->create_subject();
		$method  = $this->set_method_accessibility( $subject, 'get_email_entry' );

		self::assertSame(
			[],
			$method->invoke(
				$subject,
				[
					'context'  => 'pay-now',
					'order_id' => 42,
				]
			)['email_addresses']
		);
	}

	/**
	 * Test PayPal order verification outcomes.
	 *
	 * @param bool        $enabled       Whether button protection is enabled.
	 * @param string|null $error_message Verification result.
	 *
	 * @dataProvider dp_test_verify
	 */
	public function test_verify( bool $enabled, ?string $error_message ): void {
		$settings = Mockery::mock();
		$settings->shouldReceive( 'is' )->with( 'paypal_payments_status', 'button' )->once()->andReturn( $enabled );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->once()->andReturn( $settings );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\Utils::json_decode_arr', [ 'context' => 'cart' ] );

		if ( $enabled ) {
			WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing(
				static function ( $value ) {
					return $value;
				}
			);
			FunctionMocker::replace( '\\HCaptcha\\Helpers\\API::verify_post_data', $error_message );
			if ( null !== $error_message ) {
				WP_Mock::userFunction( 'wp_send_json_error' )
					->with( [ 'message' => $error_message ], 400 )->once();
			}
		}

		$this->create_subject()->verify();
	}

	/**
	 * Verification cases.
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			'button disabled' => [ false, null ],
			'valid captcha'   => [ true, null ],
			'invalid captcha' => [ true, 'Invalid captcha' ],
		];
	}

	/**
	 * Test init_hooks().
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_init_hooks(): void {
		$subject = ( new ReflectionClass( Button::class ) )->newInstanceWithoutConstructor();
		$method  = $this->set_method_accessibility( $subject, 'init_hooks' );

		WP_Mock::expectActionAdded(
			'ppcp_start_button_wrapper_ppcp_gateway',
			[ $subject, 'add_captcha' ]
		);

		$method->invoke( $subject );
	}

	/**
	 * Test get_verification_entry().
	 *
	 * @param string $context          Request context.
	 * @param bool   $checkout_enabled Checkout integration status.
	 * @param bool   $button_enabled   PayPal button integration status.
	 * @param array  $expected         Expected verification entry.
	 *
	 * @return void
	 * @dataProvider dp_test_get_verification_entry
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_verification_entry(
		string $context,
		bool $checkout_enabled,
		bool $button_enabled,
		array $expected
	): void {
		$settings = Mockery::mock();
		$settings->shouldReceive( 'is' )
			->with( 'woocommerce_status', 'checkout' )
			->andReturn( $checkout_enabled );
		$settings->shouldReceive( 'is' )
			->with( 'paypal_payments_status', 'button' )
			->andReturn( $button_enabled );

		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->with()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );

		$subject = ( new ReflectionClass( Button::class ) )->newInstanceWithoutConstructor();
		$method  = $this->set_method_accessibility( $subject, 'get_verification_entry' );

		self::assertSame( $expected, $method->invoke( $subject, [ 'context' => $context ] ) );
	}

	/**
	 * Data provider for test_get_verification_entry().
	 *
	 * @return array
	 */
	public function dp_test_get_verification_entry(): array {
		$paypal_entry = [
			'nonce'  => 'hcaptcha_woocommerce_paypal_payments_nonce',
			'action' => 'hcaptcha_woocommerce_paypal_payments',
		];

		return [
			'protected checkout'          => [
				'checkout',
				true,
				true,
				[
					'nonce'  => Checkout::NONCE,
					'action' => Checkout::ACTION,
				],
			],
			'unprotected checkout'        => [
				'checkout',
				false,
				true,
				$paypal_entry,
			],
			'unprotected checkout block'  => [
				'checkout-block',
				false,
				true,
				$paypal_entry,
			],
			'cart'                        => [
				'cart',
				false,
				true,
				$paypal_entry,
			],
			'button integration disabled' => [
				'checkout',
				false,
				false,
				[],
			],
		];
	}

	/**
	 * Test add_checkout_block_captcha() when checkout protection is disabled.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_add_checkout_block_captcha_without_checkout_protection(): void {
		$settings = Mockery::mock();
		$settings->shouldReceive( 'is' )
			->with( 'woocommerce_status', 'checkout' )
			->once()
			->andReturn( false );

		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::form', 'captcha' );

		$subject  = ( new ReflectionClass( Button::class ) )->newInstanceWithoutConstructor();
		$expected = 'content<div class="hcaptcha-woocommerce-paypal-payments" style="display:none;">captcha</div>';

		self::assertSame( $expected, $subject->add_checkout_block_captcha( 'content' ) );
	}

	/**
	 * Test disable_recaptcha().
	 *
	 * @param mixed $settings Settings.
	 * @param array $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_disable_recaptcha
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_disable_recaptcha( $settings, array $expected ): void {
		$subject = ( new ReflectionClass( Button::class ) )->newInstanceWithoutConstructor();

		self::assertSame( $expected, $subject->disable_recaptcha( $settings ) );
	}

	/**
	 * Data provider for test_disable_recaptcha().
	 *
	 * @return array
	 */
	public function dp_test_disable_recaptcha(): array {
		return [
			'not array'       => [
				false,
				[
					'enabled' => 'no',
				],
			],
			'enabled setting' => [
				[
					'enabled'     => 'yes',
					'site_key_v3' => 'v3-key',
					'site_key_v2' => 'v2-key',
				],
				[
					'enabled'     => 'no',
					'site_key_v3' => 'v3-key',
					'site_key_v2' => 'v2-key',
				],
			],
		];
	}
}
