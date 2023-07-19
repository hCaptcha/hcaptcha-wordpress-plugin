<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BackInStockNotifier;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {
	/**
	 * Script handle.
	 */
	const HANDLE = 'hcaptcha-back-in-stock-notifier';

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_back_in_stock_notifier';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_back_in_stock_notifier_nonce';

	/**
	 * Form id.
	 *
	 * @var int
	 */
	private $form_id = 0;

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'cwg_instock_after_email_field', [ $this, 'after_email_field' ], 10, 2 );
		add_action( 'cwginstock_after_submit_button', [ $this, 'after_submit_button' ], 10, 2 );
		add_action( 'cwginstock_ajax_data', [ $this, 'verify' ], 0, 2 );

		// Fire it before same in Main, which is on 0.
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], - 1 );
	}

	/**
	 * After email field action.
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function after_email_field( $product_id, $variation_id ) {
		$this->form_id = $product_id;

		ob_start();
	}

	/**
	 * After submit button action.
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function after_submit_button( $product_id, $variation_id ) {
		$output = ob_get_clean();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		$search  = '<div class="form-group';
		$replace = '<div class="form-group center-block" style="text-align:center;">' . HCaptcha::form( $args ) . '</div>' . $search;
		$output  = str_replace( $search, $replace, $output );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	/**
	 * Verify request.
	 *
	 * @param array $post_data POST data.
	 * @param bool  $rest_api  Whether we have request via REST API.
	 *
	 * @return void
	 */
	public function verify( $post_data, $rest_api ) {

		$hcaptcha_response = $post_data['h-captcha-response'] ?? '';

		$result = hcaptcha_request_verify( $hcaptcha_response );

		if ( null === $result ) {
			return;
		}

		$error_msg = [ 'msg' => "<div class='cwginstockerror' style='color:red;'>$result</div>" ];

		if ( ! $rest_api ) {
			wp_send_json( $error_msg, 200 );
		} else {
			echo wp_json_encode( $error_msg );
			die();
		}
	}

	/**
	 * Enqueue Back In Stock Notifier script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( is_shop() ) {
			/**
			 * The form will be loaded on Ajax.
			 * Here we signal the Main class to load hcaptcha script.
			 */
			hcaptcha()->form_shown = true;
		}

		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-back-in-stock-notifier$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
