<?php
/**
 * The AddPaymentMethod class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class AddPaymentMethod
 */
class AddPaymentMethod {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_wc_add_payment_method';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_wc_add_payment_method_nonce';

	/**
	 * Style handle.
	 */
	private const STYLE_HANDLE = 'hcaptcha-wc-add-payment-method';

	/**
	 * Target template name.
	 */
	private const TEMPLATE_NAME = 'myaccount/form-add-payment-method.php';

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
	private function init_hooks(): void {
		add_action( 'woocommerce_before_template_part', [ $this, 'before_template_part' ], 10, 4 );
		add_action( 'woocommerce_after_template_part', [ $this, 'after_template_part' ], 10, 4 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_filter( 'woocommerce_add_payment_method_form_is_valid', [ $this, 'verify' ] );
	}

	/**
	 * Enqueue styles for Add Payment Method endpoint.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! is_account_page() || ! is_wc_endpoint_url( 'add-payment-method' ) ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_style(
			self::STYLE_HANDLE,
			HCAPTCHA_URL . "/assets/css/hcaptcha-wc-add-payment-method$min.css",
			[],
			HCAPTCHA_VERSION
		);
	}

	/**
	 * Start buffering before rendering the target template.
	 *
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @param string $located       Located path.
	 * @param array  $args          Template args.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function before_template_part( string $template_name, string $template_path, string $located, array $args ): void {
		if ( ! $this->is_target_template( $template_name ) ) {
			return;
		}

		ob_start();
	}

	/**
	 * Inject hCaptcha into the target template output and print it.
	 *
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @param string $located       Located path.
	 * @param array  $args          Template args.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function after_template_part( string $template_name, string $template_path, string $located, array $args ): void {
		if ( ! $this->is_target_template( $template_name ) ) {
			return;
		}

		$output = ob_get_clean();

		$hcap_args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'add_payment_method',
			],
		];

		$hcaptcha = HCaptcha::form( $hcap_args );
		$updated  = preg_replace(
			'/(<button\s+type="submit")/i',
			$hcaptcha . "\n" . '$1',
			$output,
			1
		);

		$output = is_string( $updated ) ? $updated : $output;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	/**
	 * Verify Add Payment Method form.
	 *
	 * @param bool|mixed $is_valid Whether the form is valid.
	 *
	 * @return bool
	 */
	public function verify( $is_valid ): bool {
		$is_valid = (bool) $is_valid;

		if ( ! $is_valid ) {
			return false;
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return true;
		}

		wc_add_notice( $error_message, 'error' );

		return false;
	}

	/**
	 * Check whether the template is a WooCommerce Add Payment Method template.
	 *
	 * @param string $template_name Template name.
	 *
	 * @return bool
	 */
	private function is_target_template( string $template_name ): bool {
		return self::TEMPLATE_NAME === $template_name;
	}
}
