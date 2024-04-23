<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\EssentialBlocks;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Block;
use WP_Error;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_essential_blocks';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_essential_blocks_nonce';

	/**
	 * Script handle.
	 */
	const HANDLE = 'hcaptcha-essential-blocks';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		// Disable recaptcha compatibility, otherwise, the Essential Blocks script fails.
		hcaptcha()->settings()->set( 'recaptcha_compat_off', [ 'on' ] );

		add_action( 'wp_ajax_eb_form_submit', [ $this, 'verify' ], 9 );
		add_action( 'wp_ajax_nopriv_eb_form_submit', [ $this, 'verify' ], 9 );

		if ( ! Request::is_frontend() ) {
			return;
		}

		add_filter( 'render_block', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add hcaptcha to an Essential Blocks form.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $block_content, array $block, WP_Block $instance ): string {
		if ( 'essential-blocks/form' !== $block['blockName'] ) {
			return (string) $block_content;
		}

		$form_id = 0;

		if ( preg_match( '/<form id="(.+)">/', $block_content, $m ) ) {
			$form_id = $m[1];
		}

		$search = '<div class="eb-form-submit';
		$args   = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];

		return str_replace( $search, HCaptcha::form( $args ) . "\n" . $search, $block_content );
	}

	/**
	 * Render block context filter.
	 * CoBlocks has no filters in form processing. So, we need to do some tricks.
	 *
	 * @since WP 5.1.0
	 *
	 * @param array|mixed $parsed_block The block being rendered.
	 * @param array       $source_block An unmodified copy of $parsed_block, as it appeared in the source content.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function render_block_data( $parsed_block, array $source_block ): array {
		static $filters_added;

		if ( $filters_added ) {
			return $parsed_block;
		}

		$parsed_block = (array) $parsed_block;
		$block_name   = $parsed_block['blockName'] ?? '';

		if ( 'coblocks/form' !== $block_name ) {
			return $parsed_block;
		}

		// Nonce is checked by CoBlocks.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_submission = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		if ( 'coblocks-form-submit' !== $form_submission ) {
			return $parsed_block;
		}

		// We cannot add filters right here.
		// In this case, the calculation of form hash in the coblocks_render_coblocks_form_block() will fail.
		add_action( 'coblocks_before_form_submit', [ $this, 'before_form_submit' ], 10, 2 );

		$filters_added = true;

		return $parsed_block;
	}

	/**
	 * Before form submitting action.
	 *
	 * @param array $post User submitted form data.
	 * @param array $atts Form block attributes.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function before_form_submit( array $post, array $atts ) {
		add_filter( 'pre_option_coblocks_google_recaptcha_site_key', '__return_true' );
		add_filter( 'pre_option_coblocks_google_recaptcha_secret_key', '__return_true' );

		$_POST['g-recaptcha-token'] = self::HCAPTCHA_DUMMY_TOKEN;

		add_filter( 'pre_http_request', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Verify the hCaptcha.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 *
	 * @return array|WP_Error
	 */
	public function verify_co( $response, array $parsed_args, string $url ) {
		if (
			CoBlocks_Form::GCAPTCHA_VERIFY_URL !== $url ||
			self::HCAPTCHA_DUMMY_TOKEN !== $parsed_args['body']['response']
		) {
			return $response;
		}

		remove_filter( 'pre_http_request', [ $this, 'verify' ] );

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return [
				'body'     => '{"success":true}',
				'response' =>
					[
						'code'    => 200,
						'message' => 'OK',
					],
			];
		}

		return [
			'body'     => '{"success":false}',
			'response' =>
				[
					'code'    => 200,
					'message' => 'OK',
				],
		];
	}

	/**
	 * Verify the hCaptcha.
	 *
	 * @return void
	 */
	public function verify() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_data_str = isset( $_POST['form_data'] ) ? sanitize_text_field( wp_unslash( $_POST['form_data'] ) ) : '';
		$form_data     = (array) json_decode( $form_data_str, true );

		$_POST['hcaptcha-widget-id'] = $form_data['hcaptcha-widget-id'] ?? '';
		$_POST['h-captcha-response'] = $form_data['h-captcha-response'] ?? '';
		$_POST[ self::NONCE ]        = $form_data[ self::NONCE ] ?? '';

		$error_message = hcaptcha_verify_post( self::NONCE, self::ACTION );

		unset( $_POST['hcaptcha-widget-id'], $_POST['h-captcha-response'], $_POST[ self::NONCE ] );

		if ( null !== $error_message ) {
			wp_send_json_error( $error_message );
		}
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles() {
		$css = <<<CSS
	.wp-block-essential-blocks-form .h-captcha {
		margin: 15px 0 0 0;
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-essential-blocks$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}
}
