<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\PaidMembershipsPro;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Checkout.
 */
class Checkout {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_pmpro_checkout';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_pmpro_checkout_nonce';

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
		add_action( 'pmpro_checkout_before_submit_button', [ $this, 'add_captcha' ] );
		add_action( 'pmpro_checkout_after_parameters_set', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'checkout',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify login form.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		global $pmpro_msg, $pmpro_msgt;

		if ( ! pmpro_was_checkout_form_submitted() ) {
			return;
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return;
		}

		$pmpro_msg  = $error_message;
		$pmpro_msgt = 'pmpro_error';
	}
}
