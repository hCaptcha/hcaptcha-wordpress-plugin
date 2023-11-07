<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\HCaptcha;

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
	 * The hCaptcha was added.
	 *
	 * @var bool
	 */
	private $captcha_added = false;

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
		add_action( 'woocommerce_checkout_process', [ $this, 'verify' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
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
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->captcha_added ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-wc-checkout$min.js",
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
