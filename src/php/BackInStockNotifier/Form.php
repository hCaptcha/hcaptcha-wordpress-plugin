<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BackInStockNotifier;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-back-in-stock-notifier';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_back_in_stock_notifier';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_back_in_stock_notifier_nonce';

	/**
	 * Form id.
	 *
	 * @var int
	 */
	private int $form_id = 0;

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
	public function init_hooks(): void {
		add_action( 'cwg_instock_after_email_field', [ $this, 'after_email_field' ], 10, 2 );
		add_action( 'cwginstock_after_submit_button', [ $this, 'after_submit_button' ], 10, 2 );
		add_action( 'cwginstock_ajax_data', [ $this, 'verify' ], 0, 2 );

		// Fire it before the same in Main, which is on 0.
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], - 1 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
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
	public function after_email_field( int $product_id, int $variation_id ): void {
		$this->form_id = $product_id;

		ob_start();
	}

	/**
	 * 'After submit button' action.
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function after_submit_button( int $product_id, int $variation_id ): void {
		$output = ob_get_clean();

		$args = [
			'mandatory' => $this->form_id <= 0,
			'action'    => self::ACTION,
			'name'      => self::NONCE,
			'id'        => $this->get_id( $this->form_id ),
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
	 * @param bool  $rest_api  Whether we have a request via REST API.
	 *
	 * @return void
	 */
	public function verify( array $post_data, bool $rest_api ): void {
		$product_id  = isset( $post_data['product_id'] ) && is_scalar( $post_data['product_id'] ) ? (int) $post_data['product_id'] : 0;
		$expected_id = $this->get_id( $product_id );
		$email       = isset( $post_data['user_email'] ) && is_string( $post_data['user_email'] ) ? $post_data['user_email'] : '';
		$entry       = EntryData::with_emails( [ 'data' => EntryData::from_array( $post_data, [] ) ], [ $email ] );
		$result      = API::verify_post_data( self::NONCE, self::ACTION, $post_data, $expected_id, $entry );

		if ( null === $result ) {
			return;
		}

		$error_msg = [ 'msg' => "<div class='cwginstockerror' style='color:red;'>$result</div>" ];

		if ( ! $rest_api ) {
			wp_send_json( $error_msg, 200 );
		} else {
			echo wp_json_encode( $error_msg );
			$this->exit();
		}
	}

	/**
	 * Get the widget identity for a product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array
	 */
	private function get_id( int $product_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $product_id,
		];
	}

	/**
	 * Exit wrapper for test purposes.
	 *
	 * @return void
	 */
	protected function exit(): void {
		// @codeCoverageIgnoreStart
		die();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Enqueue Back In Stock Notifier script.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function enqueue_scripts(): void {
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

		if ( static::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
