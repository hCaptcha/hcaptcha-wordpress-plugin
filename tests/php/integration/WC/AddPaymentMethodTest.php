<?php
/**
 * AddPaymentMethodTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WC\AddPaymentMethod;

/**
 * Test AddPaymentMethod class.
 *
 * @group wc
 * @group wc-add-payment-method
 */
class AddPaymentMethodTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'woocommerce/woocommerce.php';

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new AddPaymentMethod();

		self::assertSame(
			10,
			has_action( 'woocommerce_before_template_part', [ $subject, 'before_template_part' ] )
		);
		self::assertSame(
			10,
			has_action( 'woocommerce_after_template_part', [ $subject, 'after_template_part' ] )
		);
		self::assertSame(
			10,
			has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_styles' ] )
		);
		self::assertSame(
			10,
			has_filter( 'woocommerce_add_payment_method_form_is_valid', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test hCaptcha insertion into the Add Payment Method template.
	 */
	public function test_template_part_injection(): void {
		$template_name = 'myaccount/form-add-payment-method.php';
		$located       = '/path/to/woocommerce/templates/myaccount/form-add-payment-method.php';
		$row           = '<div class="form-row"><button type="submit" class="button" id="place_order">Add payment method</button></div>';
		$hcaptcha      = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_wc_add_payment_method',
				'name'   => 'hcaptcha_wc_add_payment_method_nonce',
				'id'     => [
					'source'  => [ 'woocommerce/woocommerce.php' ],
					'form_id' => 'add_payment_method',
				],
			]
		);
		$expected      = '<div class="form-row">' . $hcaptcha . "\n" . '<button type="submit" class="button" id="place_order">Add payment method</button></div>';

		$subject = new AddPaymentMethod();

		ob_start();
		$subject->before_template_part( $template_name, '', $located, [] );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $row;

		$subject->after_template_part( $template_name, '', $located, [] );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test no injection for other templates.
	 */
	public function test_no_injection_for_other_template(): void {
		$template_name = 'myaccount/form-login.php';
		$located       = '/path/to/woocommerce/templates/myaccount/form-login.php';
		$row           = '<div class="form-row"><button type="submit" class="button">Submit</button></div>';

		$subject = new AddPaymentMethod();

		ob_start();
		$subject->before_template_part( $template_name, '', $located, [] );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $row;

		$subject->after_template_part( $template_name, '', $located, [] );

		self::assertSame( $row, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_wc_add_payment_method_nonce', 'hcaptcha_wc_add_payment_method' );

		$subject = new AddPaymentMethod();

		WC()->init();
		wc_clear_notices();

		self::assertTrue( $subject->verify( true ) );
		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test verify() not verified.
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

		$this->prepare_verify_post( 'hcaptcha_wc_add_payment_method_nonce', 'hcaptcha_wc_add_payment_method', false );

		$subject = new AddPaymentMethod();

		WC()->init();
		wc_clear_notices();

		self::assertFalse( $subject->verify( true ) );
		self::assertSame( $expected, wc_get_notices() );
	}
}
