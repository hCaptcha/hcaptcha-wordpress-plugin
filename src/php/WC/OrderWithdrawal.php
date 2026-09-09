<?php
/**
 * The OrderWithdrawal class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;

/**
 * Class OrderWithdrawal.
 */
class OrderWithdrawal {

	/**
	 * The hCaptcha nonce action.
	 */
	private const ACTION = 'hcaptcha_wc_order_withdrawal';

	/**
	 * The hCaptcha nonce name.
	 */
	private const NONCE = 'hcaptcha_wc_order_withdrawal_nonce';

	/**
	 * WooCommerce form action field.
	 */
	private const WC_ACTION_FIELD = 'order_withdrawal_action';

	/**
	 * WooCommerce review action.
	 */
	private const WC_ACTION_REVIEW = 'review';

	/**
	 * WooCommerce confirmation action.
	 */
	private const WC_ACTION_CONFIRM = 'confirm';

	/**
	 * WooCommerce nonce action.
	 */
	private const WC_NONCE_ACTION = 'woocommerce_order_withdrawal';

	/**
	 * WooCommerce nonce name.
	 */
	private const WC_NONCE = 'woocommerce-order-withdrawal-nonce';

	/**
	 * Target template name.
	 */
	private const TEMPLATE_NAME = 'myaccount/form-order-withdrawal.php';

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
		add_action( 'wp_loaded', [ $this, 'verify' ] );
	}

	/**
	 * Start buffering before rendering the review form.
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
		if ( ! $this->is_review_template( $template_name, $args ) ) {
			return;
		}

		ob_start();
	}

	/**
	 * Add hCaptcha above the withdrawal action buttons.
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
		if ( ! $this->is_review_template( $template_name, $args ) ) {
			return;
		}

		$output = (string) ob_get_clean();
		$args   = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$search  = '~<p\b(?=[^>]*\bclass=["\'][^"\']*\bwoocommerce-order-withdrawal-content__actions\b[^"\']*["\'])[^>]*>~i';
		$updated = preg_replace( $search, HCaptcha::form( $args ) . "\n" . '$0', $output, 1 );
		$output  = is_string( $updated ) ? $updated : $output;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	/**
	 * Verify hCaptcha before WooCommerce submits the withdrawal request.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		if ( ! $this->is_confirmation_request() || ! $this->has_valid_woocommerce_nonce() ) {
			return;
		}

		$error_message = API::verify(
			[
				'nonce_name'   => self::NONCE,
				'nonce_action' => self::ACTION,
				'expected_id'  => $this->get_expected_id(),
			]
		);

		if ( null === $error_message ) {
			return;
		}

		wc_add_notice( $error_message, 'error' );

		// Return to the review screen and prevent WooCommerce from submitting the request.
		$_POST[ self::WC_ACTION_FIELD ] = self::WC_ACTION_REVIEW;
	}

	/**
	 * Check whether the current request confirms an order withdrawal.
	 *
	 * @return bool
	 */
	private function is_confirmation_request(): bool {
		return Request::is_post() &&
			self::WC_ACTION_CONFIRM === Request::filter_input( INPUT_POST, self::WC_ACTION_FIELD );
	}

	/**
	 * Check the WooCommerce order withdrawal nonce.
	 *
	 * @return bool
	 */
	private function has_valid_woocommerce_nonce(): bool {
		$nonce = Request::filter_input( INPUT_POST, self::WC_NONCE );
		$nonce = is_string( $nonce ) ? $nonce : '';

		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, self::WC_NONCE_ACTION );
	}

	/**
	 * Get the expected hCaptcha widget id.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => 'order_withdrawal',
		];
	}

	/**
	 * Check whether the WooCommerce order withdrawal review template is rendered.
	 *
	 * @param string $template_name Template name.
	 * @param array  $args          Template args.
	 *
	 * @return bool
	 */
	private function is_review_template( string $template_name, array $args ): bool {
		return self::TEMPLATE_NAME === $template_name && self::WC_ACTION_REVIEW === ( $args['screen'] ?? '' );
	}
}
