<?php
/**
 * The Checkout class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\WC;

use Automattic\WooCommerce\StoreApi\Routes\V1\Checkout as StoreApiCheckout;
use Exception;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_Block;

/**
 * Class Checkout
 */
class Checkout {

	/**
	 * Nonce action.
	 */
	public const ACTION = 'hcaptcha_wc_checkout';

	/**
	 * Nonce name.
	 */
	public const NONCE = 'hcaptcha_wc_checkout_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-wc-checkout';

	/**
	 * Block script handle.
	 */
	private const BLOCK_HANDLE = 'hcaptcha-wc-block-checkout';

	/**
	 * Stripe express checkout script handle.
	 */
	private const STRIPE_EXPRESS_HANDLE = 'hcaptcha-wc-stripe-express';

	/**
	 * Marks a checkout form that already contains the stable Stripe banner challenge.
	 */
	private const STRIPE_BANNER_MARKER = 'hcaptcha_wc_stripe_banner_present';

	/**
	 * The hCaptcha was added.
	 *
	 * @var bool
	 */
	private bool $captcha_added = false;

	/**
	 * The block hCaptcha was added.
	 *
	 * @var bool
	 */
	private bool $block_captcha_added = false;

	/**
	 * Whether Stripe rendered product express buttons in the current form.
	 *
	 * @var bool
	 */
	private bool $stripe_product_buttons_rendered = false;

	/**
	 * Whether Stripe rendered mini-cart express buttons in the current cart.
	 *
	 * @var bool
	 */
	private bool $stripe_minicart_buttons_rendered = false;

	/**
	 * Whether Stripe rendered the checkout express banner in this request.
	 *
	 * @var bool
	 */
	private bool $stripe_checkout_banner_rendered = false;

	/**
	 * Whether the stable checkout banner challenge was added.
	 *
	 * @var bool
	 */
	private bool $stripe_checkout_banner_captcha_added = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'add_captcha' ] );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'add_stripe_checkout_banner_captcha' ], 20 );
		add_filter( 'render_block', [ $this, 'add_block_captcha' ], 10, 3 );
		add_action( 'woocommerce_checkout_process', [ $this, 'verify' ] );
		add_action( 'wc_stripe_rest_process_checkout', [ $this, 'verify_stripe_rest_checkout' ], 0 );
		add_action( 'wc_stripe_product_before_payment_methods', [ $this, 'mark_stripe_product_buttons' ] );
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'add_stripe_product_captcha' ] );
		add_action( 'wc_stripe_cart_before_payment_methods', [ $this, 'add_stripe_cart_captcha' ] );
		add_action( 'wc_stripe_minicart_before_payment_methods', [ $this, 'mark_stripe_minicart_buttons' ] );
		add_action( 'woocommerce_widget_shopping_cart_after_buttons', [ $this, 'add_stripe_minicart_captcha' ] );
		add_filter( 'wc_stripe_express_checkout_skeleton', [ $this, 'mark_stripe_checkout_banner' ], 10, 2 );
		add_filter( 'wc_stripe_script_dependencies', [ $this, 'add_stripe_script_dependency' ], 10, 2 );
		add_action( 'init', [ $this, 'register_stripe_express_script' ], 0 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'verify_block' ], 10, 3 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( $this->stripe_checkout_banner_captcha_added || $this->has_stripe_banner_on_checkout_fragment() ) {
			return;
		}

		$this->display_checkout_captcha( false );
	}

	/**
	 * Place the single visible banner challenge outside AJAX-replaced fragments.
	 *
	 * @return void
	 */
	public function add_stripe_checkout_banner_captcha(): void {
		if ( ! $this->stripe_checkout_banner_rendered || $this->stripe_checkout_banner_captcha_added ) {
			return;
		}

		$this->stripe_checkout_banner_captcha_added = true;
		$this->display_checkout_captcha( true );
	}

	/**
	 * Render the checkout widget once at its selected form location.
	 *
	 * @param bool $visible Force a normal visible challenge for express buttons.
	 *
	 * @return void
	 */
	private function display_checkout_captcha( bool $visible ): void {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		if ( $visible ) {
			$args['size']  = 'normal';
			$args['force'] = false;
		}

		echo '<div class="hcaptcha-wc-checkout-proof">';
		HCaptcha::form_display( $args );

		if ( $visible ) {
			printf( '<input type="hidden" name="%s" value="1">', esc_attr( self::STRIPE_BANNER_MARKER ) );
		}

		echo '</div>';

		$this->captcha_added = true;
	}

	/**
	 * Observe the native Stripe banner render without changing its skeleton setting.
	 *
	 * Stripe applies this filter only after finding available express gateways.
	 *
	 * @param bool   $enabled Whether Stripe's skeleton is enabled.
	 * @param string $context Stripe express surface.
	 *
	 * @return bool
	 */
	public function mark_stripe_checkout_banner( bool $enabled, string $context = '' ): bool {
		if ( 'express_checkout' === $context ) {
			$this->stripe_checkout_banner_rendered = true;
		}

		return $enabled;
	}

	/**
	 * Detect the stable banner challenge during a WooCommerce payment refresh.
	 *
	 * The refresh renders only the payment fragment. Its serialized checkout form
	 * includes the marker only when the banner widget was actually rendered.
	 *
	 * @return bool
	 */
	private function has_stripe_banner_on_checkout_fragment(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verifies the refresh nonce; this marker only selects the widget's display location.
		$serialized_post_data = wp_unslash( $_POST['post_data'] ?? null );

		if (
			! is_string( $serialized_post_data ) ||
			! wp_doing_ajax() ||
			! did_action( 'woocommerce_checkout_update_order_review' )
		) {
			return false;
		}

		wp_parse_str( $serialized_post_data, $post_data );

		return is_array( $post_data ) &&
			'1' === ( $post_data[ self::STRIPE_BANNER_MARKER ] ?? null );
	}

	/**
	 * Record that the native Stripe product template rendered express buttons.
	 *
	 * @return void
	 */
	public function mark_stripe_product_buttons(): void {
		$this->stripe_product_buttons_rendered = true;
	}

	/**
	 * Render a visible challenge after the product add-to-cart form.
	 *
	 * @return void
	 */
	public function add_stripe_product_captcha(): void {
		if ( ! $this->stripe_product_buttons_rendered ) {
			return;
		}

		$this->stripe_product_buttons_rendered = false;
		$this->display_stripe_express_captcha( 'product' );
	}

	/**
	 * Render a visible challenge before native Stripe cart express buttons.
	 *
	 * @return void
	 */
	public function add_stripe_cart_captcha(): void {
		$this->display_stripe_express_captcha( 'cart' );
	}

	/**
	 * Record that the native Stripe mini-cart template rendered express buttons.
	 *
	 * @return void
	 */
	public function mark_stripe_minicart_buttons(): void {
		$this->stripe_minicart_buttons_rendered = true;
	}

	/**
	 * Render a visible challenge after the mini-cart button paragraph.
	 *
	 * @return void
	 */
	public function add_stripe_minicart_captcha(): void {
		if ( ! $this->stripe_minicart_buttons_rendered ) {
			return;
		}

		$this->stripe_minicart_buttons_rendered = false;
		$this->display_stripe_express_captcha( 'minicart' );
	}

	/**
	 * Render the checkout-identity widget for a native Stripe express surface.
	 *
	 * @param string $context Product, cart, or mini-cart.
	 *
	 * @return void
	 */
	private function display_stripe_express_captcha( string $context ): void {
		$callbacks = [
			'product'  => [ 'onProductSolved', 'onProductExpired' ],
			'cart'     => [ 'onCartSolved', 'onCartExpired' ],
			'minicart' => [ 'onMinicartSolved', 'onMinicartExpired' ],
		];
		$callback  = $callbacks[ $context ];
		$args      = [
			'action'        => self::ACTION,
			'name'          => self::NONCE,
			'id'            => $this->get_expected_id(),
			'size'          => 'normal',
			'force'         => false,
			'widget_params' => [
				'callback'         => 'hCaptchaWCStripeExpress.' . $callback[0],
				'expired-callback' => 'hCaptchaWCStripeExpress.' . $callback[1],
				'error-callback'   => 'hCaptchaWCStripeExpress.' . $callback[1],
			],
		];

		printf( '<div class="hcaptcha-wc-stripe-express-proof" data-context="%s">', esc_attr( $context ) );
		HCaptcha::form_display( $args );
		echo '</div>';
	}

	/**
	 * Load the hCaptcha bridge before Stripe initializes its express controllers.
	 *
	 * @param array  $dependencies Native Stripe script dependencies.
	 * @param string $handle       Native Stripe script handle.
	 *
	 * @return array
	 */
	public function add_stripe_script_dependency( array $dependencies, string $handle = '' ): array {
		if ( 'wc-stripe-controllers' === $handle && ! in_array( self::STRIPE_EXPRESS_HANDLE, $dependencies, true ) ) {
			$dependencies[] = self::STRIPE_EXPRESS_HANDLE;
		}

		return $dependencies;
	}

	/**
	 * Register the Stripe bridge before its native controller is registered.
	 *
	 * @return void
	 */
	public function register_stripe_express_script(): void {
		$min = hcap_min_suffix();

		wp_register_script(
			self::STRIPE_EXPRESS_HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-wc-stripe-express$min.js",
			[ 'hcaptcha', 'wp-api-fetch', 'wc-stripe-actions' ],
			HCAPTCHA_VERSION,
			true
		);
		wp_localize_script(
			self::STRIPE_EXPRESS_HANDLE,
			'HCaptchaWCStripeExpressObject',
			[
				'nonceName'           => self::NONCE,
				'missingProofMessage' => hcap_get_error_messages()['empty'],
			]
		);
	}

	/**
	 * Add captcha to the checkout block.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 *
	 * @return string
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_block_captcha( $block_content, array $block, WP_Block $instance ): string {
		$block_content = (string) $block_content;

		if ( 'woocommerce/checkout' !== $block['blockName'] ) {
			return (string) $block_content;
		}

		$search = '~<div(?=[^>]*\bdata-block-name="woocommerce/checkout-actions-block")(?=[^>]*\bclass="[^"]*\bwp-block-woocommerce-checkout-actions-block\b[^"]*")[^>]*></div>~';

		if ( ! preg_match( $search, $block_content ) ) {
			return $block_content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$this->block_captcha_added = true;

		return (string) preg_replace( $search, HCaptcha::form( $args ) . '$0', $block_content, 1 );
	}

	/**
	 * Verify the checkout form.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		$error_message = API::verify( $this->get_entry() );

		if ( null !== $error_message ) {
			wc_add_notice( $error_message, 'error' );
		}
	}

	/**
	 * Verify Stripe express checkout before it can create a customer account.
	 *
	 * The Stripe REST route has already copied its JSON fields into $_POST here.
	 * WooCommerce's checkout_process hook runs after account creation, so it
	 * cannot protect that earlier side effect.
	 *
	 * @return void
	 * @throws Exception When hCaptcha verification fails.
	 * @noinspection ThrowRawExceptionInspection
	 */
	public function verify_stripe_rest_checkout(): void {
		$error_message = API::verify( $this->get_entry() );

		if ( null !== $error_message ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Convert the already-escaped verifier result to plain REST text.
			throw new Exception( wp_strip_all_tags( wp_specialchars_decode( $error_message, ENT_QUOTES ) ) );
		}
	}

	/**
	 * Verify the checkout block.
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
	 *
	 * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_block( $response, array $handler, WP_REST_Request $request ) {
		if ( ! $this->is_checkout_request( $handler, $request ) ) {
			return $response;
		}

		$method = $request->get_method();

		if ( 'POST' !== $method ) {
			return $response;
		}

		$error_message = API::verify( $this->get_block_entry( $request->get_params() ) );

		if ( null === $error_message ) {
			return $response;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, [ 'status' => 400 ] );
	}

	/**
	 * Recognize the selected native checkout callback and legacy route aliases.
	 *
	 * @param array           $handler Native handler selected by WordPress.
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	private function is_checkout_request( array $handler, WP_REST_Request $request ): bool {
		$callback = $handler['callback'] ?? null;

		if (
			is_array( $callback ) &&
			( $callback[0] ?? null ) instanceof StoreApiCheckout &&
			'get_response' === ( $callback[1] ?? null )
		) {
			return true;
		}

		// Preserve older Store API namespaces and direct calls without handler metadata.
		return 1 === preg_match( '~^/wc/store(?:/v1)?/checkout$~iD', $request->get_route() );
	}

	/**
	 * Get hCaptcha verification entry.
	 *
	 * @return array
	 */
	private function get_entry(): array {
		$entry = [
			'nonce_name'   => self::NONCE,
			'nonce_action' => self::ACTION,
			'data'         => EntryData::from_post(
				[
					'email'      => 'billing_email',
					'first_name' => 'billing_first_name',
					'last_name'  => 'billing_last_name',
				]
			),
			'expected_id'  => $this->get_expected_id(),
		];

		// Nonce is checked by API::verify(). Select only WooCommerce's native email field.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['billing_email'] ) && is_string( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

		return EntryData::with_emails( $entry, [ $email ] );
	}

	/**
	 * Get hCaptcha block verification entry.
	 *
	 * @param array $post_data Request parameters.
	 *
	 * @return array
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_block_entry( array $post_data ): array {
		$billing_address = (array) ( $post_data['billing_address'] ?? [] );
		$email           = '';

		if ( array_key_exists( 'email', $billing_address ) ) {
			$email = is_string( $billing_address['email'] ) ? sanitize_email( $billing_address['email'] ) : '';
		} elseif ( WC()->customer ) {
			$email = WC()->customer->get_billing_email();
		}

		$data = EntryData::from_array(
			$billing_address,
			[
				'email'      => 'email',
				'first_name' => 'first_name',
				'last_name'  => 'last_name',
			]
		);

		foreach ( [ 'shipping_address', 'additional_fields' ] as $field_group ) {
			$fields = EntryData::from_array( (array) ( $post_data[ $field_group ] ?? [] ), [] );

			if ( $fields ) {
				$data[ $field_group ] = $fields;
			}
		}

		if ( isset( $post_data['customer_note'] ) && is_scalar( $post_data['customer_note'] ) ) {
			$data['customer_note'] = sanitize_text_field( (string) $post_data['customer_note'] );
		}

		return EntryData::with_emails(
			[
				'expected_id' => $this->get_expected_id(),
				'post_data'   => $post_data,
				'data'        => $data,
			],
			[ $email ]
		);
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'checkout',
		];
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		if ( $this->captcha_added ) {
			wp_enqueue_script(
				self::HANDLE,
				HCAPTCHA_URL . "/assets/js/hcaptcha-wc-checkout$min.js",
				[ 'jquery', 'hcaptcha' ],
				HCAPTCHA_VERSION,
				true
			);
		}

		if ( $this->block_captcha_added ) {
			wp_enqueue_script(
				self::BLOCK_HANDLE,
				HCAPTCHA_URL . "/assets/js/hcaptcha-wc-block-checkout$min.js",
				[ 'hcaptcha' ],
				HCAPTCHA_VERSION,
				true
			);
		}
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

		if ( self::BLOCK_HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
