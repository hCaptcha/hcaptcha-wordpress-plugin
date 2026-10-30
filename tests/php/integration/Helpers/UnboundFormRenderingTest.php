<?php
/**
 * UnboundFormRenderingTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Asgaros\Form as AsgarosForm;
use HCaptcha\BackInStockNotifier\Form as BackInStockForm;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\IcegramExpress\Form as IcegramForm;
use HCaptcha\Passster\Protect;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\Checkout;
use HCaptcha\WooCommercePayPalPayments\Button;
use ReflectionClass;
use ReflectionException;
use WP_Block;

/**
 * Test challenge availability for handlers without a server-selected form identity.
 *
 * @group helpers
 * @group helpers-unbound-rendering
 */
class UnboundFormRenderingTest extends HCaptchaWPTestCase {

	/**
	 * Identities observed by the real protection filter.
	 *
	 * @var array
	 */
	private array $protection_checks = [];

	/**
	 * Enable the integration policies and disable per-form CAPTCHA protection.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( 0 );
		$_POST = [];
		hcaptcha()->settings()->set( 'woocommerce_status', [] );
		hcaptcha()->settings()->set( 'paypal_payments_status', [ 'button' ] );
		hcaptcha()->settings()->set( 'asgaros_status', [ 'form' ] );
		hcaptcha()->settings()->set( 'passster_status', [ 'protect' ] );
		hcaptcha()->settings()->set( 'icegram_express_status', [ 'form' ] );
		hcaptcha()->settings()->set( 'back_in_stock_notifier_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );

		add_filter(
			'hcap_protect_form',
			function ( $protect, $source, $form_id ): bool {
				$this->protection_checks[] = [
					'source'  => $source,
					'form_id' => $form_id,
				];

				return false;
			},
			PHP_INT_MAX,
			3
		);
	}

	/**
	 * Mandatory forms render a usable challenge even when either policy disables it.
	 *
	 * @param bool $protect Explicit form protection argument.
	 *
	 * @dataProvider dp_test_mandatory_form_renders_challenge
	 */
	public function test_mandatory_form_renders_challenge( bool $protect ): void {
		$id     = [
			'source'  => [ 'WordPress' ],
			'form_id' => 'login',
		];
		$output = HCaptcha::form(
			[
				'action'    => 'unbound_rendering',
				'name'      => 'unbound_rendering_nonce',
				'id'        => $id,
				'protect'   => $protect,
				'mandatory' => true,
			]
		);

		$this->assert_challenge( $output, 'unbound_rendering_nonce', 'unbound_rendering' );
		self::assertSame( [ $id ], $this->protection_checks );
	}

	/**
	 * Explicit and filtered protection policies.
	 *
	 * @return array
	 */
	public function dp_test_mandatory_form_renders_challenge(): array {
		return [
			'filtered protection disabled' => [ true ],
			'explicit protection disabled' => [ false ],
		];
	}

	/**
	 * Ordinary forms continue to honor a matching per-form exemption.
	 */
	public function test_default_form_honors_policy_exemption(): void {
		$output = HCaptcha::form(
			[
				'action' => 'bound_rendering',
				'name'   => 'bound_rendering_nonce',
				'id'     => [
					'source'  => [ 'WordPress' ],
					'form_id' => 'login',
				],
			]
		);

		self::assertStringNotContainsString( '<h-captcha', $output );
		self::assertStringNotContainsString( 'name="bound_rendering_nonce"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Rendering a mandatory challenge preserves protection-filter side effects.
	 */
	public function test_mandatory_form_keeps_honeypot_and_timing_policy(): void {
		hcaptcha()->settings()->set( 'honeypot', [ 'on' ] );
		hcaptcha()->settings()->set( 'set_min_submit_time', [ 'on' ] );
		add_filter(
			'hcap_protect_form',
			static function (): bool {
				hcaptcha()->settings()->set( 'honeypot', [] );
				hcaptcha()->settings()->set( 'set_min_submit_time', [] );

				return false;
			},
			PHP_INT_MAX
		);

		$output = HCaptcha::form(
			[
				'action'    => 'unbound_rendering',
				'name'      => 'unbound_rendering_nonce',
				'id'        => [
					'source'  => [ 'WordPress' ],
					'form_id' => 'login',
				],
				'protect'   => false,
				'mandatory' => true,
			]
		);

		$this->assert_challenge( $output, 'unbound_rendering_nonce', 'unbound_rendering' );
		self::assertFalse( hcaptcha()->settings()->is_on( 'honeypot' ) );
		self::assertFalse( hcaptcha()->settings()->is_on( 'set_min_submit_time' ) );
		self::assertStringNotContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringNotContainsString( 'name="hcap_fst_token"', $output );
	}

	/**
	 * Native unbound renderers retain the challenge their handlers require.
	 *
	 * @param string     $renderer   Native rendering path.
	 * @param string     $class_name Integration class.
	 * @param int|string $form_id    Rendered identity.
	 * @param string     $nonce_name Nonce field name.
	 * @param string     $action     Nonce action.
	 *
	 * @dataProvider dp_test_native_unbound_renderer
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_native_unbound_renderer(
		string $renderer,
		string $class_name,
		$form_id,
		string $nonce_name,
		string $action
	): void {
		$subject = ( new ReflectionClass( $class_name ) )->newInstanceWithoutConstructor();

		switch ( $renderer ) {
			case 'asgaros':
				global $asgarosforum;

				$asgarosforum = (object) [ 'current_forum' => 7 ];

				try {
					$output = $subject->add_captcha( '<form><div class="editor-row editor-row-submit"></div></form>', 'forum', [], [] );
				} finally {
					unset( $GLOBALS['asgarosforum'] );
				}
				break;
			case 'passster':
				$html   = '<form><div data-area="3866"></div>' . "\n" . '<button name="submit">Unlock</button></form>';
				$output = $subject->do_shortcode_tag( $html, 'passster', [], [] );
				break;
			case 'paypal-button':
				$output = $this->capture_output( [ $subject, 'add_captcha' ] );
				break;
			case 'paypal-cart-block':
				$output = $subject->add_block_captcha( '<div>Cart express payment</div>' );
				break;
			case 'back-in-stock':
				$output = $this->capture_output(
					static function () use ( $subject ): void {
						$subject->after_email_field( 0, 0 );
						echo '<div class="form-group"><input type="submit"></div>';
						$subject->after_submit_button( 0, 0 );
					}
				);
				break;
			default:
				$output = $subject->add_checkout_block_captcha( '<div>Checkout express payment</div>' );
		}

		$this->assert_challenge( $output, $nonce_name, $action );
		self::assertContains(
			[
				'source'  => HCaptcha::get_class_source( $class_name ),
				'form_id' => $form_id,
			],
			$this->protection_checks
		);
	}

	/**
	 * Unbound handlers and the native markup each consumes.
	 *
	 * @return array
	 */
	public function dp_test_native_unbound_renderer(): array {
		return [
			'Asgaros forum'                    => [ 'asgaros', AsgarosForm::class, 7, 'hcaptcha_asgaros_new_topic_nonce', 'hcaptcha_asgaros_new_topic' ],
			'Passster unlock'                  => [ 'passster', Protect::class, '3866', 'hcaptcha_passster_nonce', 'hcaptcha_passster' ],
			'PayPal classic button'            => [ 'paypal-button', Button::class, 'paypal_button', 'hcaptcha_woocommerce_paypal_payments_nonce', 'hcaptcha_woocommerce_paypal_payments' ],
			'PayPal cart block'                => [ 'paypal-cart-block', Button::class, 'express_payment_block', 'hcaptcha_woocommerce_paypal_payments_nonce', 'hcaptcha_woocommerce_paypal_payments' ],
			'PayPal checkout own block'        => [ 'paypal-checkout-block', Button::class, 'express_payment_block', 'hcaptcha_woocommerce_paypal_payments_nonce', 'hcaptcha_woocommerce_paypal_payments' ],
			'Back In Stock without product id' => [ 'back-in-stock', BackInStockForm::class, 0, 'hcaptcha_back_in_stock_notifier_nonce', 'hcaptcha_back_in_stock_notifier' ],
		];
	}

	/**
	 * An Icegram widget can honor exemptions only when it has a native form id.
	 *
	 * @param int $form_id Native form ID, or zero when absent from widget markup.
	 *
	 * @dataProvider dp_test_icegram_widget
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_icegram_widget( int $form_id ): void {
		$subject = ( new ReflectionClass( IcegramForm::class ) )->newInstanceWithoutConstructor();
		$this->set_protected_property( $subject, 'in_widget', true );
		$html = '<form' . ( $form_id ? ' data-form-id="' . $form_id . '"' : '' ) . '><input type="submit"></form>';

		$output = $this->capture_output(
			static function () use ( $subject, $html ): void {
				ob_start();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native widget markup supplied to the renderer.
				echo $html;
				$subject->dynamic_sidebar_after( 1, true );
			}
		);

		if ( 0 === $form_id ) {
			$this->assert_challenge( $output, 'hcaptcha_icegram_express_nonce', 'hcaptcha_icegram_express' );
		} else {
			self::assertStringNotContainsString( '<h-captcha', $output );
			self::assertStringNotContainsString( 'name="hcaptcha_icegram_express_nonce"', $output );
		}

		self::assertContains(
			[
				'source'  => HCaptcha::get_class_source( IcegramForm::class ),
				'form_id' => $form_id,
			],
			$this->protection_checks
		);
	}

	/**
	 * Bound and unbound Icegram widget markup.
	 *
	 * @return array
	 */
	public function dp_test_icegram_widget(): array {
		return [
			'missing form id' => [ 0 ],
			'known form id'   => [ 17 ],
		];
	}

	/**
	 * The shared Checkout widget remains available only for an enabled PayPal handler.
	 *
	 * @param bool $paypal_enabled PayPal button protection is enabled.
	 * @param bool $block          Render the Checkout block instead of the classic form.
	 *
	 * @dataProvider dp_test_shared_checkout_challenge
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_shared_checkout_challenge( bool $paypal_enabled, bool $block ): void {
		hcaptcha()->settings()->set( 'woocommerce_status', [ 'checkout' ] );
		hcaptcha()->settings()->set( 'paypal_payments_status', $paypal_enabled ? [ 'button' ] : [] );
		$button  = $paypal_enabled ? new Button() : null;
		$subject = ( new ReflectionClass( Checkout::class ) )->newInstanceWithoutConstructor();

		if ( $block ) {
			$parsed = [
				'blockName' => 'woocommerce/checkout',
				'attrs'     => [],
			];
			$output = $subject->add_block_captcha(
				'<div data-block-name="woocommerce/checkout-actions-block" class="wp-block-woocommerce-checkout-actions-block"></div>',
				$parsed,
				new WP_Block( $parsed )
			);
		} else {
			$output = $this->capture_output( [ $subject, 'add_captcha' ] );
		}

		if ( $paypal_enabled ) {
			$this->assert_challenge( $output, Checkout::NONCE, Checkout::ACTION );
		} else {
			self::assertStringNotContainsString( '<h-captcha', $output );
			self::assertStringNotContainsString( 'name="' . Checkout::NONCE . '"', $output );
		}

		$unrelated = HCaptcha::form(
			[
				'action' => 'unrelated_rendering',
				'name'   => 'unrelated_rendering_nonce',
				'id'     => [
					'source'  => HCaptcha::get_class_source( Checkout::class ),
					'form_id' => 'register',
				],
			]
		);
		self::assertStringNotContainsString( '<h-captcha', $unrelated );
		self::assertStringNotContainsString( 'name="unrelated_rendering_nonce"', $unrelated );

		if ( $button ) {
			remove_filter( 'hcap_form_args', [ $button, 'require_checkout_challenge' ] );
		}
	}

	/**
	 * Classic and block Checkout with and without an unbound PayPal handler.
	 *
	 * @return array
	 */
	public function dp_test_shared_checkout_challenge(): array {
		return [
			'classic Checkout with PayPal'    => [ true, false ],
			'Checkout block with PayPal'      => [ true, true ],
			'classic Checkout without PayPal' => [ false, false ],
			'Checkout block without PayPal'   => [ false, true ],
		];
	}

	/**
	 * Assert the rendered challenge includes a valid native nonce and signed identity.
	 *
	 * @param string $output     Rendered native form.
	 * @param string $nonce_name Nonce field name.
	 * @param string $action     Nonce action.
	 */
	private function assert_challenge( string $output, string $nonce_name, string $action ): void {
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertSame( 1, preg_match( '/name="' . preg_quote( $nonce_name, '/' ) . '" value="([^"]+)"/', $output, $matches ) );
		self::assertNotFalse( wp_verify_nonce( $matches[1], $action ) );
		self::assertSame( 1, preg_match( '/name="hcaptcha-widget-id"\s+value="([^"]+)"/', $output, $widget ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $widget[1];

		try {
			self::assertTrue( HCaptcha::decode_id_info()['valid'] );
		} finally {
			unset( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );
		}
	}

	/**
	 * Capture real renderers without leaking their nested output buffers.
	 *
	 * @param callable $render Native rendering callback.
	 *
	 * @return string
	 */
	private function capture_output( callable $render ): string {
		$level = ob_get_level();
		ob_start();

		try {
			$render();

			return (string) ob_get_contents();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
	}
}
