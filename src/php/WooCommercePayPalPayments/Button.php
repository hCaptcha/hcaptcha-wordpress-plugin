<?php
/**
 * The Button class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\WooCommercePayPalPayments;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Utils;
use HCaptcha\WC\Checkout;
use WC_Order;

/**
 * Class Button.
 */
class Button {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-woocommerce-paypal-payments';

	/**
	 * Early script handle.
	 */
	private const EARLY_HANDLE = 'hcaptcha-woocommerce-paypal-payments-early';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_woocommerce_paypal_payments';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_woocommerce_paypal_payments_nonce';

	/**
	 * PayPal Payments reCAPTCHA settings option.
	 */
	private const RECAPTCHA_SETTINGS_OPTION = 'woocommerce_ppcp-recaptcha_settings';

	/**
	 * The hCaptcha was added.
	 *
	 * @var bool
	 */
	private bool $captcha_added = false;

	/**
	 * Button constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'ppcp_start_button_wrapper_ppcp_gateway', [ $this, 'add_captcha' ] );
		add_action( 'woocommerce_paypal_payments_minicart_button_render', [ $this, 'add_captcha' ] );
		add_filter( 'render_block_woocommerce/cart-express-payment-block', [ $this, 'add_block_captcha' ] );
		add_filter( 'render_block_woocommerce/checkout-express-payment-block', [ $this, 'add_checkout_block_captcha' ] );
		add_filter( 'option_' . self::RECAPTCHA_SETTINGS_OPTION, [ $this, 'disable_recaptcha' ] );
		add_filter( 'hcap_form_args', [ $this, 'require_checkout_challenge' ] );

		add_action( 'wc_ajax_ppc-create-order', [ $this, 'verify' ], 0 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_early_scripts' ], 0 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ], 0 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function add_captcha(): void {
		$is_pay_now  = function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page();
		$is_checkout = ! $is_pay_now && function_exists( 'is_checkout' ) && is_checkout();

		if ( $is_checkout && hcaptcha()->settings()->is( 'woocommerce_status', 'checkout' ) ) {
			$this->captcha_added = true;

			return;
		}

		$args = [
			'mandatory' => true,
			'action'    => self::ACTION,
			'name'      => self::NONCE,
			'id'        => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'paypal_button',
			],
		];

		echo '<span class="hcaptcha-woocommerce-paypal-payments" style="display:block;">';
		HCaptcha::form_display( $args );
		echo '</span>';

		$this->captcha_added = true;
	}

	/**
	 * Disable WooCommerce PayPal Payments reCAPTCHA.
	 *
	 * @param mixed $settings reCAPTCHA settings.
	 *
	 * @return array
	 */
	public function disable_recaptcha( $settings ): array {
		$settings            = is_array( $settings ) ? $settings : [];
		$settings['enabled'] = 'no';

		return $settings;
	}

	/**
	 * Add captcha to a WooCommerce block page.
	 *
	 * @param string|mixed $block_content The block content.
	 *
	 * @return string
	 */
	public function add_block_captcha( $block_content ): string {
		$block_content = (string) $block_content;

		if ( $this->captcha_added ) {
			return $block_content;
		}

		$args = [
			'mandatory' => true,
			'action'    => self::ACTION,
			'name'      => self::NONCE,
			'id'        => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'express_payment_block',
			],
		];

		$this->captcha_added = true;
		$captcha             = sprintf(
			'<div class="hcaptcha-woocommerce-paypal-payments" style="display:none;">%s</div>',
			HCaptcha::form( $args )
		);

		return $block_content . $captcha;
	}

	/**
	 * Mark the WooCommerce checkout block page as needing the PayPal script.
	 *
	 * @param string|mixed $block_content The block content.
	 *
	 * @return string
	 */
	public function add_checkout_block_captcha( $block_content ): string {
		$block_content = (string) $block_content;

		if ( ! hcaptcha()->settings()->is( 'woocommerce_status', 'checkout' ) ) {
			return $this->add_block_captcha( $block_content );
		}

		$this->captcha_added = true;

		return $block_content;
	}

	/**
	 * Keep the shared checkout challenge available to the unbound PayPal handler.
	 *
	 * @param array $args Form arguments.
	 *
	 * @return array
	 */
	public function require_checkout_challenge( array $args ): array {
		$id = $args['id'] ?? [];

		if (
			'checkout' === ( $id['form_id'] ?? null ) &&
			HCaptcha::get_class_source( Checkout::class ) === ( $id['source'] ?? [] )
		) {
			$args['mandatory'] = true;
		}

		return $args;
	}

	/**
	 * Verify PayPal create order request.
	 *
	 * @return void
	 */
	public function verify(): void {
		$input = file_get_contents( 'php://input' );
		$data  = Utils::json_decode_arr( (string) $input );
		$entry = $this->get_verification_entry( $data );

		if ( [] === $entry ) {
			return;
		}

		$verification  = $this->get_email_entry( $data );
		$error_message = API::verify_post_data( $entry['nonce'], $entry['action'], $data, [], $verification );

		if ( null === $error_message ) {
			return;
		}

		wp_send_json_error( [ 'message' => $error_message ], 400 );
	}

	/**
	 * Select the payer email using the native create-order context.
	 *
	 * @param array $data Native JSON request.
	 *
	 * @return array
	 */
	private function get_email_entry( array $data ): array {
		if ( isset( $data['form_encoded'] ) && is_string( $data['form_encoded'] ) ) {
			parse_str( $data['form_encoded'], $data['form'] );
		}

		$data = $this->normalize_request_data( $data );
		$form = is_array( $data['form'] ?? null ) ? $data['form'] : [];

		if ( 'pay-now' === ( $data['context'] ?? '' ) ) {
			$email = $this->get_order_email( $data );
		} elseif ( ! empty( $data['payer'] ) && is_array( $data['payer'] ) ) {
			$email = $data['payer']['email_address'] ?? '';
		} else {
			$email = $form['billing_email'] ?? '';
		}

		$email = is_string( $email ) ? $email : '';

		return EntryData::with_emails( [ 'data' => EntryData::from_array( $form, [] ) ], [ $email ] );
	}

	/**
	 * Match the native RequestData sanitizer before selecting its canonical fields.
	 *
	 * @param array $data Decoded native JSON request.
	 *
	 * @return array
	 */
	private function normalize_request_data( array $data ): array {
		$normalized = [];

		foreach ( $data as $key => $value ) {
			if ( 'form_encoded' === $key ) {
				$normalized[ $key ] = $value;
				continue;
			}

			$normalized[ sanitize_text_field( (string) $key ) ] = is_array( $value )
				? $this->normalize_request_data( $value )
				: sanitize_text_field( (string) $value );
		}

		return $normalized;
	}

	/**
	 * Read an order's billing email only after the native pay-for-order checks.
	 *
	 * @param array $data Native JSON request.
	 *
	 * @return string
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	private function get_order_email( array $data ): string {
		// The native request sanitizer retains arrays, and the endpoint casts this selector to int.
		$order_id  = (int) ( $data['order_id'] ?? 0 );
		$order_key = is_string( $data['order_key'] ?? null ) ? $data['order_key'] : '';
		$order     = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;

		// WooCommerce maps this capability for both owned and guest orders.
		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! $order instanceof WC_Order || ! $order->key_is_valid( $order_key ) || ! current_user_can( 'pay_for_order', $order_id ) ) {
			return '';
		}

		return $order->get_billing_email();
	}

	/**
	 * Get verification entry.
	 *
	 * @param array $data Request data.
	 *
	 * @return array{nonce:string,action:string}|array{}
	 */
	private function get_verification_entry( array $data ): array {
		$context = (string) ( $data['context'] ?? '' );

		if (
			in_array( $context, [ 'checkout', 'checkout-block' ], true ) &&
			hcaptcha()->settings()->is( 'woocommerce_status', 'checkout' )
		) {
			return [
				'nonce'  => Checkout::NONCE,
				'action' => Checkout::ACTION,
			];
		}

		// The context is client-controlled, so it must not select an unverified path.
		// Fall back to the PayPal captcha when checkout protection is disabled.
		if ( ! hcaptcha()->settings()->is( 'paypal_payments_status', 'button' ) ) {
			return [];
		}

		return [
			'nonce'  => self::NONCE,
			'action' => self::ACTION,
		];
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.hcaptcha-woocommerce-paypal-payments .h-captcha {
		margin-top: 0.7rem;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Enqueue script.
	 *
	 * @return void
	 */
	public function enqueue_early_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::EARLY_HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-woocommerce-paypal-payments-early$min.js",
			[],
			HCAPTCHA_VERSION,
			false
		);
	}

	/**
	 * Filter printed hCaptcha scripts status and return true if WC PayPal Payments script is already loaded.
	 *
	 * @param bool|mixed $status Print scripts status.
	 *
	 * @return bool
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		return wp_script_is( 'ppcp-smart-button' ) ? true : $status;
	}

	/**
	 * Enqueue script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if (
			! $this->captcha_added &&
			! wp_script_is( 'ppcp-smart-button' ) &&
			! wp_script_is( 'ppcp-checkout-block' )
		) {
			return;
		}

		$min          = hcap_min_suffix();
		$dependencies = [ 'hcaptcha' ];

		foreach ( [ 'ppcp-smart-button', 'ppcp-checkout-block' ] as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) || wp_script_is( $handle ) ) {
				$dependencies[] = $handle;
			}
		}

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-woocommerce-paypal-payments$min.js",
			$dependencies,
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add the type="module" attribute to the script tag.
	 *
	 * @param string|mixed $tag    Script tag.
	 * @param string       $handle Script handle.
	 * @param string       $src    Script source.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_type_module( $tag, string $handle, string $src ): string {
		$tag = (string) $tag;

		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
