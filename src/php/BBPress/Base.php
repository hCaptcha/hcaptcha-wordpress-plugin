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
		$args = [
			'action' => static::ACTION,
			'name'   => static::NAME,
			'id'     => $this->get_expected_id(),
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
		$error_message = API::verify( $this->get_entry() );

		if ( null !== $error_message ) {
			bbp_add_error( 'hcap_error', $error_message );

			return false;
		}

		return true;
	}

	/**
	 * Get hCaptcha verification entry.
	 *
	 * @return array
	 */
	protected function get_entry(): array {
		return [
			'nonce_name'   => static::NAME,
			'nonce_action' => static::ACTION,
			'expected_id'  => $this->get_expected_id(),
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	protected function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => str_replace( 'hcaptcha_bbp_', '', static::ACTION ),
		];
	}
}
