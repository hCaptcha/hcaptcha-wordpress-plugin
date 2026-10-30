<?php
/**
 * ButtonEmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WooCommercePayPalPayments;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\WC\WooCommerceTestCase;
use HCaptcha\WooCommercePayPalPayments\Button;
use tad\FunctionMocker\FunctionMocker;
use WC_Post_Types;
use function Patchwork\relay;

/**
 * Test native PayPal create-order email selection using the real verifier.
 *
 * @group wc
 * @group paypal-payments
 */
class ButtonEmailPolicyTest extends WooCommerceTestCase {
	/**
	 * Replay native registration after WordPress resets its lifecycle counters.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! did_action( 'woocommerce_after_register_post_type' ) ) {
			// Native registration otherwise exits early because the prior test's product type remains registered.
			unregister_post_type( 'product' );
			WC_Post_Types::register_post_types();
		}
	}

	/**
	 * Native payer and checkout fields reach disposable-email policy.
	 *
	 * @param string $context Native email source.
	 * @param bool   $blocked Whether the selected email is disposable.
	 *
	 * @return void
	 * @dataProvider dp_native_email_sources
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_native_email_policy( string $context, bool $blocked ): void {
		$email = $blocked ? 'buyer@blocked.test' : 'buyer@allowed.test';
		wp_set_current_user( 0 );
		$this->prepare_verify_post( 'hcaptcha_woocommerce_paypal_payments_nonce', 'hcaptcha_woocommerce_paypal_payments' );
		hcaptcha()->settings()->set( 'paypal_payments_status', [ 'button' ] );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce-paypal-payments/woocommerce-paypal-payments.php' ],
				'form_id' => 'paypal_button',
			]
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data            = $_POST;
		$data['context'] = 'cart';
		$data['email']   = 'forged@allowed.test';
		$order           = null;

		if ( in_array( $context, [ 'pay-now', 'normalized pay-now' ], true ) ) {
			$order = wc_create_order();
			$order->set_billing_email( $email );
			$order->save();
			$data['context']   = 'pay-now';
			$data['order_id']  = $order->get_id();
			$data['order_key'] = $order->get_order_key();
			$data['payer']     = [ 'email_address' => 'forged@allowed.test' ];

			if ( 'normalized pay-now' === $context ) {
				$data['context'] = '<b>pay-now</b>';
			}
		} elseif ( in_array( $context, [ 'payer', 'normalized payer' ], true ) ) {
			$data['payer'] = [ 'email_address' => $email ];
			$data['form']  = [ 'billing_email' => 'forged@allowed.test' ];

			if ( 'normalized payer' === $context ) {
				$data['payer'] = [ '<b>email_address</b>' => '<b>' . $email . '</b>' ];
			}
		} elseif ( 'encoded form' === $context ) {
			$data['form_encoded'] = http_build_query(
				[
					'billing_email' => $email,
					'email'         => 'forged@allowed.test',
				]
			);
		} else {
			$data['form'] = [
				'billing_email' => $email,
				'email'         => 'forged@allowed.test',
			];

			if ( 'normalized form' === $context ) {
				unset( $data['form'] );
				$data['<b>form</b>'] = [ '<b>billing_email</b>' => '<b>' . $email . '</b>' ];
			}
		}

		$this->prepare_native_input( $data );
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
			ob_start();
			( new Button() )->verify();
			$output = ob_get_clean();
			self::assertSame(
				$blocked ? [
					'success' => false,
					'data'    => [ 'message' => 'Please use a permanent email address.' ],
				] : null,
				json_decode( $output, true )
			);
			self::assertSame( [ $email ], $checked );
		} finally {
			if ( $order ) {
				$order->delete( true );
			}
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Native email sources and policy outcomes.
	 *
	 * @return array
	 */
	public function dp_native_email_sources(): array {
		return [
			'blocked guest order'    => [ 'pay-now', true ],
			'allowed guest order'    => [ 'pay-now', false ],
			'blocked payer'          => [ 'payer', true ],
			'allowed payer'          => [ 'payer', false ],
			'blocked form'           => [ 'form', true ],
			'allowed form'           => [ 'form', false ],
			'blocked encoded'        => [ 'encoded form', true ],
			'allowed encoded'        => [ 'encoded form', false ],
			'blocked native context' => [ 'normalized pay-now', true ],
			'allowed native context' => [ 'normalized pay-now', false ],
			'blocked native payer'   => [ 'normalized payer', true ],
			'allowed native payer'   => [ 'normalized payer', false ],
			'blocked native form'    => [ 'normalized form', true ],
			'allowed native form'    => [ 'normalized form', false ],
		];
	}

	/**
	 * A forged key cannot expose an unrelated order's stored email to policy.
	 *
	 * @return void
	 */
	public function test_pay_now_requires_native_order_key(): void {
		wp_set_current_user( 0 );
		$this->prepare_verify_post( 'hcaptcha_woocommerce_paypal_payments_nonce', 'hcaptcha_woocommerce_paypal_payments' );
		hcaptcha()->settings()->set( 'paypal_payments_status', [ 'button' ] );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$order = wc_create_order();
		$order->set_billing_email( 'buyer@blocked.test' );
		$order->save();
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce-paypal-payments/woocommerce-paypal-payments.php' ],
				'form_id' => 'paypal_button',
			]
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data              = $_POST;
		$data['context']   = 'pay-now';
		$data['order_id']  = $order->get_id();
		$data['order_key'] = 'forged-key';
		$checked           = [];
		$this->prepare_native_input( $data );
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, $email ) use ( &$checked ): bool {
				$checked[] = $email;
				return true;
			},
			10,
			2
		);

		try {
			ob_start();
			( new Button() )->verify();
			self::assertSame( '', ob_get_clean() );
			self::assertSame( [], $checked );
		} finally {
			$order->delete( true );
		}
	}

	/**
	 * Supply the native CLI input fixture while preserving the real provider response decoder.
	 *
	 * @param array $data Native request fixture.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function prepare_native_input( array $data ): void {
		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\Utils::json_decode_arr',
			static function ( string $json ) use ( $data ): array {
				// php://input is empty in the CLI test; siteverify responses always use the real decoder.
				return '' === $json ? $data : relay();
			}
		);
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {};
			}
		);
	}
}
