<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Affiliates;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Login
 */
class Login extends LoginBase {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_filter( 'login_form_middle', [ $this, 'add_affiliates_captcha' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $content Content to display. Default empty.
	 * @param array        $args    Array of login form arguments.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_affiliates_captcha( $content, array $args ): string {
		$content = (string) $content;

		if ( ! $this->is_affiliates_login_form() ) {
			return $content;
		}

		ob_start();
		$this->add_captcha();

		return $content . ob_get_clean();
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.affiliates-dashboard .h-captcha {
		margin-top: 2rem;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Whether we process the Affiliates login form.
	 *
	 * @return bool
	 */
	private function is_affiliates_login_form(): bool {
		return did_action( 'affiliates_dashboard_before_section' );
	}
}
