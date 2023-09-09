<?php
/**
 * AdvancedForm class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

use HCaptcha\Helpers\HCaptcha;
use WP_Block;

/**
 * Class AdvancedForm.
 */
class AdvancedForm {

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
		add_filter( 'render_block', [ $this, 'render_block' ], 10, 3 );
		add_filter(
			'block_parser_class',
			static function () {
				return AdvancedBlockParser::class;
			}
		);
		add_action( 'wp_ajax_kb_process_advanced_form_submit', [ $this, 'process_ajax' ], 9 );
		add_action( 'wp_ajax_nopriv_kb_process_advanced_form_submit', [ $this, 'process_ajax' ], 9 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Render block filter.
	 *
	 * @param string|mixed $block_content Block content.
	 * @param array        $block         Block.
	 * @param WP_Block     $instance      Instance.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection HtmlUnknownAttribute
	 */
	public function render_block( $block_content, array $block, WP_Block $instance ) {
		if ( 'kadence/advanced-form-captcha' !== $block['blockName'] ) {
			return $block_content;
		}

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => AdvancedBlockParser::$form_id,
			],
		];

		$pattern       = '#<div class="h-captcha" .*?></div>#';
		$block_content = (string) $block_content;

		return (string) preg_replace(
			$pattern,
			HCaptcha::form( $args ),
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
			'html'     => '<div class="kb-adv-form-message kb-adv-form-warning">' . $error . '</div>',
			'console'  => __( 'hCaptcha Failed', 'hcaptcha-for-forms-and-more' ),
			'required' => null,
		];

		wp_send_json_error( $data );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		wp_dequeue_script( 'kadence-blocks-hcaptcha' );
		wp_deregister_script( 'kadence-blocks-hcaptcha' );

		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-kadence-advanced',
			HCAPTCHA_URL . "/assets/js/hcaptcha-kadence-advanced$min.js",
			[ 'hcaptcha', 'kadence-blocks-advanced-form' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
