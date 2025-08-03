<?php
/**
 * Customer Reviews for WooCommerce - Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CustomerReviews;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Main;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_customer_reviews';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_customer_reviews_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-customer-reviews';

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
	protected function init_hooks(): void {
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );

		add_action( 'woocommerce_before_template_part', [ $this, 'before_template_part' ], 10, 4 );
		add_action( 'woocommerce_after_template_part', [ $this, 'add_captcha' ], 10, 4 );
	}

	/**
	 * 'Before template part' action.
	 * Start the output buffer before the template part.
	 *
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @param string $located       Located.
	 * @param array  $template_args Arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function before_template_part( string $template_name, string $template_path, string $located, array $template_args ): void {
		if ( static::WC_TEMPLATE_NAME !== $template_name ) {
			return;
		}

		ob_start();
	}

	/**
	 * Verify.
	 *
	 * @return void
	 */
	public function verify(): void {
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			$return = [
				'code'        => 1,
				'description' => $error_message,
				'button'      => __( 'Try again', 'hcaptcha-for-forms-and-more' ),
			];

			wp_send_json( $return );
		}
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
	.cr-review-form-item .h-captcha {
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Print footer scripts.
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-customer-reviews$min.js",
			[ Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);
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

		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
