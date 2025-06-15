<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\API;
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
	private const ACTION = 'hcaptcha_wc_checkout';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_wc_checkout_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-wc-checkout';

	/**
	 * Block script handle.
	 */
	private const BLOCK_HANDLE = 'hcaptcha-wc-block-checkout';

	/**
	 * The hCaptcha was added.
	 *
	 * @var bool
	 */
	private $captcha_added = false;

	/**
	 * The block hCaptcha was added.
	 *
	 * @var bool
	 */
	private $block_captcha_added = false;

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
		add_filter( 'render_block', [ $this, 'add_block_captcha' ], 10, 3 );
		add_action( 'woocommerce_checkout_process', [ $this, 'verify' ] );
		add_filter( 'rest_request_before_callbacks', [ $this, 'verify_block' ], 10, 3 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'checkout',
			],
		];

		HCaptcha::form_display( $args );

		$this->captcha_added = true;
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

		$search = '<div data-block-name="woocommerce/checkout-actions-block" class="wp-block-woocommerce-checkout-actions-block"></div>';
		$args   = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'checkout',
			],
		];

		$this->block_captcha_added = true;

		return str_replace( $search, HCaptcha::form( $args ) . $search, $block_content );
	}

	/**
	 * Verify checkout form.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			wc_add_notice( $error_message, 'error' );
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
		if ( '/wc/store/v1/checkout' !== $request->get_route() ) {
			return $response;
		}

		$widget_id_name           = 'hcaptcha-widget-id';
		$hcaptcha_response_name   = 'h-captcha-response';
		$_POST[ $widget_id_name ] = $request->get_param( $widget_id_name );
		$hcaptcha_response        = $request->get_param( $hcaptcha_response_name );

		$error_message = API::verify_request( $hcaptcha_response );

		if ( null === $error_message ) {
			return $response;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
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
}
