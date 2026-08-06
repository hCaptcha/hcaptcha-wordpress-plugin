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
