<?php
/**
 * Newsletter Subscribe class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Blocksy;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Block;

/**
 * Class `Newsletter Subscribe`.
 */
class NewsletterSubscribe {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_blocksy_newsletter_subscribe';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_blocksy_newsletter_subscribe_nonce';

	/**
	 * Class constructor.
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
		add_filter( 'render_block', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_action(
			'wp_ajax_blc_newsletter_subscribe_process_ajax_subscribe',
			[ $this, 'verify' ],
			9
		);
		add_action(
			'wp_ajax_nopriv_blc_newsletter_subscribe_process_ajax_subscribe',
			[ $this, 'verify' ],
			9
		);

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
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
	 */
	public function add_hcaptcha( $block_content, array $block, WP_Block $instance ): string {
		$block_content = (string) $block_content;

		if ( 'blocksy/newsletter' !== $block['blockName'] ) {
			return $block_content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'newsletter-subscribe',
			],
		];

		$search = '<button';

		return str_replace(
			$search,
			"\n" . HCaptcha::form( $args ) . "\n" . $search,
			$block_content
		);
	}

	/**
	 * Verify.
	 *
	 * @return void
	 */
	public function verify(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return;
		}

		wp_send_json_error(
			[
				'result'  => 'no',
				'message' => $error_message,
			]
		);
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.ct-newsletter-subscribe-form input[type="email"] {
		grid-row: 1;
	}

	.ct-newsletter-subscribe-form h-captcha {
		grid-row: 2;
		margin-bottom: 0;
	}

	.ct-newsletter-subscribe-form button {
		grid-row: 3;
	}
';

		HCaptcha::css_display( $css );
	}
}
