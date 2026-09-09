<?php
/**
 * OrderWithdrawalTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\WC\OrderWithdrawal;

/**
 * Test OrderWithdrawal class.
 *
 * @group wc
 * @group wc-order-withdrawal
 */
class OrderWithdrawalTest extends WooCommerceTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new OrderWithdrawal();

		self::assertSame(
			10,
			has_action( 'woocommerce_before_template_part', [ $subject, 'before_template_part' ] )
		);
		self::assertSame(
			10,
			has_action( 'woocommerce_after_template_part', [ $subject, 'after_template_part' ] )
		);
		self::assertSame( 10, has_action( 'wp_loaded', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test hCaptcha insertion into the live WooCommerce review template.
	 */
	public function test_template_part_injection(): void {
		$hcaptcha = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_wc_order_withdrawal',
				'name'   => 'hcaptcha_wc_order_withdrawal_nonce',
				'id'     => [
					'source'  => [ 'woocommerce/woocommerce.php' ],
					'form_id' => 'order_withdrawal',
				],
			]
		);

		new OrderWithdrawal();

		ob_start();

		wc_get_template(
			'myaccount/form-order-withdrawal.php',
			[
				'screen'          => 'review',
				'data'            => [ 'email' => 'customer@example.com' ],
				'hidden_fields'   => [],
				'review_rows'     => [],
				'nonce_action'    => 'woocommerce_order_withdrawal',
				'nonce_field'     => 'woocommerce-order-withdrawal-nonce',
				'action_field'    => 'order_withdrawal_action',
				'action_confirm'  => 'confirm',
				'action_edit'     => 'edit',
				'form_action_url' => home_url( '/my-account/withdraw-order/' ),
			]
		);

		$output = ob_get_clean();

		self::assertStringContainsString( 'class="woocommerce-OrderWithdrawalForm"', $output );
		self::assertSame( 1, substr_count( $output, $hcaptcha ) );
		self::assertLessThan(
			strpos( $output, '<p class="woocommerce-order-withdrawal-content__actions">' ),
			strpos( $output, $hcaptcha )
		);
	}

	/**
	 * Test that hCaptcha is not added to the details screen.
	 */
	public function test_no_injection_outside_review_screen(): void {
		$template_name = 'myaccount/form-order-withdrawal.php';
		$row           = '<button type="submit" name="order_withdrawal_action" value="confirm">Confirm</button>';
		$args          = [ 'screen' => 'form' ];
		$subject       = new OrderWithdrawal();

		ob_start();
		$subject->before_template_part( $template_name, '', '', $args );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $row;

		$subject->after_template_part( $template_name, '', '', $args );

		self::assertSame( $row, ob_get_clean() );
	}

	/**
	 * Test successful hCaptcha verification.
	 */
	public function test_verify(): void {
		$this->prepare_confirmation_request();
		$this->prepare_verify_post( 'hcaptcha_wc_order_withdrawal_nonce', 'hcaptcha_wc_order_withdrawal' );
		$this->prepare_widget_id();

		$subject = new OrderWithdrawal();

		wc_clear_notices();
		$subject->verify();

		self::assertSame( 'confirm', Request::filter_input( INPUT_POST, 'order_withdrawal_action' ) );
		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test failed hCaptcha verification.
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

		$this->prepare_confirmation_request();
		$this->prepare_verify_post( 'hcaptcha_wc_order_withdrawal_nonce', 'hcaptcha_wc_order_withdrawal', false );
		$this->prepare_widget_id();

		$subject = new OrderWithdrawal();

		wc_clear_notices();
		$subject->verify();

		self::assertSame( 'review', Request::filter_input( INPUT_POST, 'order_withdrawal_action' ) );
		self::assertSame( $expected, wc_get_notices() );
	}

	/**
	 * Test that a request with an invalid WooCommerce nonce is ignored.
	 */
	public function test_verify_with_invalid_woocommerce_nonce(): void {
		$this->prepare_confirmation_request();
		$_POST['woocommerce-order-withdrawal-nonce'] = 'invalid';

		$subject = new OrderWithdrawal();

		wc_clear_notices();
		$subject->verify();

		self::assertSame( 'confirm', Request::filter_input( INPUT_POST, 'order_withdrawal_action' ) );
		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Test that a non-confirmation request is ignored.
	 */
	public function test_verify_with_review_action(): void {
		$this->prepare_confirmation_request();
		$_POST['order_withdrawal_action'] = 'review';

		$subject = new OrderWithdrawal();

		wc_clear_notices();
		$subject->verify();

		self::assertSame( 'review', Request::filter_input( INPUT_POST, 'order_withdrawal_action' ) );
		self::assertSame( [], wc_get_notices() );
	}

	/**
	 * Prepare a WooCommerce order withdrawal confirmation request.
	 */
	private function prepare_confirmation_request(): void {
		$_SERVER['REQUEST_METHOD']                   = 'POST';
		$_POST['order_withdrawal_action']            = 'confirm';
		$_POST['woocommerce-order-withdrawal-nonce'] = wp_create_nonce( 'woocommerce_order_withdrawal' );
	}

	/**
	 * Prepare the hCaptcha widget id.
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'order_withdrawal',
			]
		);
	}
}
