<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

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
	const ACTION = 'hcaptcha_wc_checkout';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_wc_checkout_nonce';

	/**
	 * Script handle.
	 */
	const HANDLE = 'hcaptcha-wc-checkout';

	/**
	 * Block script handle.
	 */
	const BLOCK_HANDLE = 'hcaptcha-wc-checkout';

	/**
	 * The hCaptcha was added.
	 *
	 * @var bool
	 */
	private $captcha_added = false;

	/**
	 * The hCaptcha was added to the Checkout block.
	 *
	 * @var bool
	 */
	private $captcha_added_to_block = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'add_captcha' ] );
		add_filter( 'render_block', [ $this, 'add_block_hcaptcha' ], 10, 3 );
		add_action( 'woocommerce_checkout_process', [ $this, 'verify' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'verify_block' ], 10, 3 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha() {
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
	 * Add hCaptcha to the checkout block.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 */
	public function add_block_hcaptcha( $block_content, array $block, WP_Block $instance ): string {
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

		$this->captcha_added_to_block = true;

		return str_replace( $search, HCaptcha::form( $args ) . $search, $block_content );
	}

	/**
	 * Verify checkout form.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify() {
		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null !== $error_message ) {
			wc_add_notice( $error_message, 'error' );
		}
	}

	/**
	 * Verify hCaptcha in the checkout block.
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

		$error_message = hcaptcha_request_verify( null );

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
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		if ( $this->captcha_added ) {
			wp_enqueue_script(
				self::HANDLE,
				HCAPTCHA_URL . "/assets/js/hcaptcha-wc-checkout$min.js",
				[ 'jquery', 'hcaptcha' ],
				HCAPTCHA_VERSION,
				true
			);

			return;
		}

		if ( $this->captcha_added_to_block ) {
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
	 * Add type="module" attribute to script tag.
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

		$type = ' type="module"';

		if ( false !== strpos( $tag, $type ) ) {
			return $tag;
		}

		$search = ' src';

		return str_replace( $search, $type . $search, $tag );
	}
}
