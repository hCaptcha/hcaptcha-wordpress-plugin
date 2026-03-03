<?php
/**
 * CheckoutTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\PaidMembershipsPro;

use HCaptcha\PaidMembershipsPro\Checkout;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Checkout class.
 *
 * @group paid-memberships-pro
 */
class CheckoutTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['pmpro_msg'], $GLOBALS['pmpro_msgt'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Checkout();

		self::assertSame(
			10,
			has_action( 'pmpro_checkout_before_submit_button', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'pmpro_checkout_after_parameters_set', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$subject = new Checkout();

		ob_start();
		$subject->add_captcha();
		$output = ob_get_clean();

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( 'hcaptcha_pmpro_checkout_nonce', $output );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		global $pmpro_msg, $pmpro_msgt;

		FunctionMocker::replace( 'pmpro_was_checkout_form_submitted', true );

		$this->prepare_verify_post( 'hcaptcha_pmpro_checkout_nonce', 'hcaptcha_pmpro_checkout' );

		$subject = new Checkout();

		$subject->verify();

		self::assertNull( $pmpro_msg );
		self::assertNull( $pmpro_msgt );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		global $pmpro_msg, $pmpro_msgt;

		FunctionMocker::replace( 'pmpro_was_checkout_form_submitted', true );

		$this->prepare_verify_post( 'hcaptcha_pmpro_checkout_nonce', 'hcaptcha_pmpro_checkout', false );

		$subject = new Checkout();

		$subject->verify();

		self::assertNotEmpty( $pmpro_msg );
		self::assertSame( 'pmpro_error', $pmpro_msgt );
	}

	/**
	 * Test verify() when a checkout form was not submitted.
	 *
	 * @return void
	 */
	public function test_verify_not_submitted(): void {
		global $pmpro_msg, $pmpro_msgt;

		FunctionMocker::replace( 'pmpro_was_checkout_form_submitted', false );

		$subject = new Checkout();

		$subject->verify();

		self::assertNull( $pmpro_msg );
		self::assertNull( $pmpro_msgt );
	}
}
