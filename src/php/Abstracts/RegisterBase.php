<?php
/**
 * RegisterBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

/**
 * Class RegisterBase.
 */
abstract class RegisterBase extends FormOwnerBase {

	/**
	 * Request owner filter.
	 */
	protected const OWNER_FILTER = 'hcap_registration_request_owner';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		$this->init_owner_hooks();
		$this->init_auto_verify_hooks();
	}

	/**
	 * Whether the current request is a WordPress registration action.
	 *
	 * @return bool
	 */
	protected function is_owner_action(): bool {
		return $this->is_wp_login_action( 'register' );
	}
}
