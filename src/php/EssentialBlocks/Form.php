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

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_essential_blocks';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_essential_blocks_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-essential-blocks';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
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
	 * Verify the hCaptcha.
	 *
	 * @return void
	 */
	public function verify(): void {
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
	public function print_inline_styles(): void {
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
	public function enqueue_scripts(): void {
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
