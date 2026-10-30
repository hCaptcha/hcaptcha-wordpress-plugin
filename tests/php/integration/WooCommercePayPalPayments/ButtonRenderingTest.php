<?php
/**
 * ButtonRenderingTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WooCommercePayPalPayments;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\WC\WooCommerceTestCase;
use HCaptcha\WC\Checkout;
use HCaptcha\WooCommercePayPalPayments\Button;

/**
 * Test classic and block PayPal challenge placement and script loading.
 *
 * @group wc
 * @group paypal-payments
 */
class ButtonRenderingTest extends WooCommerceTestCase {
	/**
	 * Classic buttons render one mandatory challenge and disable native reCAPTCHA.
	 */
	public function test_classic_button_challenge(): void {
		$subject = new Button();

		self::assertSame( [ 'enabled' => 'no' ], $subject->disable_recaptcha( null ) );
		self::assertSame(
			[
				'site_key' => 'native',
				'enabled'  => 'no',
			],
			$subject->disable_recaptcha( [ 'site_key' => 'native' ] )
		);
		ob_start();
		$subject->add_captcha();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcaptcha_woocommerce_paypal_payments_nonce"', $output );
		self::assertStringContainsString( 'hcaptcha-woocommerce-paypal-payments', $output );
		self::assertSame( 'original', $subject->add_block_captcha( 'original' ) );
	}

	/**
	 * Block buttons render once; a protected checkout block uses its shared widget.
	 */
	public function test_block_challenge_and_shared_checkout(): void {
		$subject = new Button();
		$output  = $subject->add_checkout_block_captcha( 'block' );

		self::assertStringContainsString( 'name="hcaptcha_woocommerce_paypal_payments_nonce"', $output );
		self::assertSame( 'block', $subject->add_block_captcha( 'block' ) );
		hcaptcha()->settings()->set( 'woocommerce_status', [ 'checkout' ] );
		$shared = new Button();

		self::assertSame( 'block', $shared->add_checkout_block_captcha( 'block' ) );
		self::assertSame( 'block', $shared->add_block_captcha( 'block' ) );
		self::assertSame( [ 'id' => [] ], $shared->require_checkout_challenge( [ 'id' => [] ] ) );
		self::assertSame(
			[
				'id'        => [
					'source'  => HCaptcha::get_class_source( Checkout::class ),
					'form_id' => 'checkout',
				],
				'mandatory' => true,
			],
			$shared->require_checkout_challenge(
				[
					'id' => [
						'source'  => HCaptcha::get_class_source( Checkout::class ),
						'form_id' => 'checkout',
					],
				]
			)
		);
	}

	/**
	 * Classic checkout uses the shared checkout challenge instead of another button widget.
	 */
	public function test_classic_checkout_reuses_shared_challenge(): void {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		hcaptcha()->settings()->set( 'woocommerce_status', [ 'checkout' ] );
		$subject = new Button();

		ob_start();
		$subject->add_captcha();
		self::assertSame( '', ob_get_clean() );
		self::assertSame( 'block', $subject->add_block_captcha( 'block' ) );
	}

	/**
	 * Queue the early helper and the module only when PayPal or its challenge is present.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_scripts_and_styles(): void {
		$subject = new Button();
		$subject->enqueue_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-woocommerce-paypal-payments' ) );
		$subject->enqueue_early_scripts();
		self::assertTrue( wp_script_is( 'hcaptcha-woocommerce-paypal-payments-early' ) );
		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
		wp_register_script( 'ppcp-smart-button', '/paypal-button.js', [], '1', true );
		wp_enqueue_script( 'ppcp-smart-button' );
		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
		$subject->enqueue_scripts();
		self::assertTrue( wp_script_is( 'hcaptcha-woocommerce-paypal-payments' ) );
		self::assertContains( 'ppcp-smart-button', wp_scripts()->registered['hcaptcha-woocommerce-paypal-payments']->deps );
		self::assertContains( 'hcaptcha', wp_scripts()->registered['hcaptcha-woocommerce-paypal-payments']->deps );
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Test fixture.
		$tag = '<script src="paypal.js"></script>';
		self::assertSame( $tag, $subject->add_type_module( $tag, 'other', 'paypal.js' ) );
		self::assertStringContainsString( 'type="module"', $subject->add_type_module( $tag, 'hcaptcha-woocommerce-paypal-payments', 'paypal.js' ) );
		ob_start();
		$subject->print_inline_styles();
		self::assertStringContainsString( '.hcaptcha-woocommerce-paypal-payments .h-captcha', ob_get_clean() );
	}

	/**
	 * Checkout requests use the shared verifier; disabled protection claims no request.
	 */
	public function test_verification_context_selection(): void {
		$subject = new Button();
		hcaptcha()->settings()->set( 'paypal_payments_status', [] );
		hcaptcha()->settings()->set( 'woocommerce_status', [] );
		$method = $this->set_method_accessibility( $subject, 'get_verification_entry' );

		self::assertSame( [], $method->invoke( $subject, [ 'context' => 'checkout' ] ) );
		ob_start();
		$subject->verify();
		self::assertSame( '', ob_get_clean() );

		hcaptcha()->settings()->set( 'woocommerce_status', [ 'checkout' ] );
		self::assertSame(
			[
				'nonce'  => Checkout::NONCE,
				'action' => Checkout::ACTION,
			],
			$method->invoke( $subject, [ 'context' => 'checkout' ] )
		);
	}
}
