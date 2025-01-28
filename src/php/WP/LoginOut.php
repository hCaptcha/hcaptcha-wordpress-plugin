<?php
/**
 * LoginOut class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class LoginOut.
 * Supports wp_login_form() function and LoginOut block.
 */
class LoginOut extends LoginBase {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'login_form_middle', [ $this, 'add_wp_login_out_hcaptcha' ], 10, 2 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string|mixed $content Content to display. Default empty.
	 * @param array        $args    Array of login form arguments.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_wp_login_out_hcaptcha( $content, array $args ): string {
		$content = (string) $content;

		if ( ! $this->is_wp_login_out_form() ) {
			return $content;
		}

		ob_start();
		$this->add_captcha();

		return $content . ob_get_clean();
	}

	/**
	 * Whether we process the LoginOut form.
	 *
	 * @return bool
	 */
	private function is_wp_login_out_form(): bool {
		return HCaptcha::did_filter( 'login_form_defaults' );
	}
}
