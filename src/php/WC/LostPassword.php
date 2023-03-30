<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class LostPassword
 *
 * This class uses verify hook in WP\LostPassword.
 */
class LostPassword {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_action( 'woocommerce_lostpassword_form', [ $this, 'add_captcha' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		$args = [
			'action' => 'hcaptcha_lost_password',
			'name'   => 'hcaptcha_lost_password_nonce',
		];

		HCaptcha::form_display( $args );
	}
}
