<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tutor;

use HCaptcha\Helpers\HCaptcha;
use Tutor\Ecommerce\CheckoutController;

/**
 * Class Checkout
 */
class Checkout {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_tutor_checkout';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_tutor_checkout_nonce';

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
		add_action( 'tutor_load_template_before', [ $this, 'template_before' ], 10, 2 );
		add_action( 'tutor_load_template_after', [ $this, 'template_after' ], 10, 2 );
		add_action( 'tutor_action_tutor_pay_now', [ $this, 'verify' ], 0 );
	}

	/**
	 * Before template.
	 *
	 * @param string|mixed $template  The template.
	 * @param array|mixed  $variables The variables.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function template_before( $template, $variables ): void {
		$template = (string) $template;

		if ( 'ecommerce.checkout' !== $template ) {
			return;
		}

		ob_start();
	}

	/**
	 * After template.
	 *
	 * @param string|mixed $template  The template.
	 * @param array|mixed  $variables The variables.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function template_after( $template, $variables ): void {
		$template = (string) $template;

		if ( 'ecommerce.checkout' !== $template ) {
			return;
		}

		$template = ob_get_clean();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'checkout',
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$search   = '<button type="submit"';
		$replace  = $hcaptcha . "\n" . $search;
		$template = str_replace( $search, $replace, $template );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $template;
	}

	/**
	 * Verify checkout form.
	 *
	 * @return void
	 */
	public function verify(): void {
		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null !== $error_message ) {
			$current_user_id = get_current_user_id();

			set_transient( CheckoutController::PAY_NOW_ERROR_TRANSIENT_KEY . $current_user_id, [ $error_message ] );
			remove_all_actions( 'tutor_action_tutor_pay_now' );
		}
	}
}
