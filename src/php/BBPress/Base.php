<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Base constructor.
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
		add_action( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ] );
		add_action( static::VERIFY_HOOK, [ $this, 'verify' ] );
	}

	/**
	 * Add captcha to the form.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$form_id = str_replace( 'hcaptcha_bbp_', '', static::ACTION );
		$args    = [
			'action' => static::ACTION,
			'name'   => static::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify captcha.
	 *
	 * @return bool
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): bool {
		$error_message = API::verify_post( static::NAME, static::ACTION );

		if ( null !== $error_message ) {
			bbp_add_error( 'hcap_error', $error_message );

			return false;
		}

		return true;
	}
}
