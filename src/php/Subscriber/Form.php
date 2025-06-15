<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Subscriber;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_subscriber_form';

	/**
	 * Nonce name.
	 */
	private const NAME = 'hcaptcha_subscriber_form_nonce';

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
	private function init_hooks(): void {
		add_filter( 'sbscrbr_add_field', [ $this, 'add_captcha' ] );
		add_filter( 'sbscrbr_check', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha to the subscriber form.
	 *
	 * @param string|mixed $content Subscriber form content.
	 *
	 * @return string
	 */
	public function add_captcha( $content ): string {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'form',
			],
		];

		return $content . HCaptcha::form( $args );
	}

	/**
	 * Verify subscriber captcha.
	 *
	 * @param bool|mixed $check_result Check result.
	 *
	 * @return bool|string|mixed
	 * @noinspection NullCoalescingOperatorCanBeUsedInspection
	 */
	public function verify( $check_result ) {
		$error_message = API::verify_post( self::NAME, self::ACTION );

		if ( null !== $error_message ) {
			return $error_message;
		}

		return $check_result;
	}
}
