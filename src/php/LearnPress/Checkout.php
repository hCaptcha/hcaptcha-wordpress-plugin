<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\LearnPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use LP_Checkout;
use WP_Error;

/**
 * Class Checkout
 */
class Checkout {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_learn_press_checkout';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_learn_press_checkout_nonce';

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
		add_action( 'learn-press/payment-form', [ $this, 'add_hcaptcha' ] );
		add_filter( 'learn-press/validate-checkout-fields', [ $this, 'verify' ], 20, 3 );
	}

	/**
	 * Add hCaptcha.
	 */
	public function add_hcaptcha(): void {
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
	 * Verify checkout form.
	 *
	 * @param array|mixed $errors      Checkout errors.
	 * @param array       $fields      Checkout fields.
	 * @param LP_Checkout $lp_checkout LearnPress checkout object.
	 *
	 * @return array|WP_Error
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, $fields, LP_Checkout $lp_checkout ) {
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $errors;
		}

		return HCaptcha::add_error_message( $errors, $error_message );
	}
}
