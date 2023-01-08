<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

use WP_Block;

/**
 * Class Form.
 */
class Form {

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
		add_filter( 'kadence_blocks_frontend_build_css', [ $this, 'remove_recaptcha_from_block' ] );
		add_filter( 'kadence_blocks_form_render_block_attributes', [ $this, 'remove_recaptcha_from_attributes' ] );
		add_filter( 'render_block', [ $this, 'render_block' ], 10, 3 );
		add_filter(
			'block_parser_class',
			static function () {
				return BlockParser::class;
			}
		);
		add_action( 'wp_ajax_kb_process_ajax_submit', [ $this, 'process_ajax' ], 9 );
		add_action( 'wp_ajax_nopriv_kb_process_ajax_submit', [ $this, 'process_ajax' ], 9 );
	}

	/**
	 * Remove recaptcha from block.
	 *
	 * @param array $block Block.
	 *
	 * @return array
	 */
	public function remove_recaptcha_from_block( $block ) {
		if ( 'kadence/form' !== $block['blockName'] ) {
			return $block;
		}

		if ( isset( $block['attrs']['recaptcha'] ) ) {
			$block['attrs']['recaptcha'] = false;
		}

		return $block;
	}

	/**
	 * Remove recaptcha from attributes.
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return array
	 */
	public function remove_recaptcha_from_attributes( $attributes ) {
		if ( isset( $attributes['recaptcha'] ) ) {
			$attributes['recaptcha'] = false;
		}

		return $attributes;
	}

	/**
	 * Render block filter.
	 *
	 * @param string   $block_content Block content.
	 * @param array    $block         Block.
	 * @param WP_Block $instance      Instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function render_block( $block_content, $block, $instance ) {
		if ( 'kadence/form' !== $block['blockName'] ) {
			return $block_content;
		}

		return preg_replace(
			'/(<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">).+?(<\/div>)/',
			'$1' . hcap_form() . '$2',
			$block_content
		);
	}

	/**
	 * Process ajax.
	 *
	 * @return void
	 */
	public function process_ajax() {
		// Nonce is checked by Kadence.

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$error = hcaptcha_request_verify( $hcaptcha_response );

		if ( null === $error ) {
			return;
		}

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$data = [
			'html'         => '<div class="kadence-blocks-form-message kadence-blocks-form-warning">' . $error . '</div>',
			'console'      => __( 'hCaptcha Failed', 'hcaptcha-for-forms-and-more' ),
			'required'     => null,
			'headers_sent' => headers_sent(),
		];

		wp_send_json_error( $data );
	}
}
