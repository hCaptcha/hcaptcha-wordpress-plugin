<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Abstracts\LoginBase;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Whether bbPress is rendering a login form.
	 *
	 * @var bool
	 */
	private bool $bbpress_login_form = false;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'bbp_get_template_part', [ $this, 'mark_template_login_form' ], 10, 3 );
		add_filter( 'bbp_login_widget_title', [ $this, 'mark_widget_login_form' ] );
		add_action( 'login_form', [ $this, 'add_bbpress_captcha' ] );
	}

	/**
	 * Mark a bbPress login form rendered from a template part.
	 *
	 * @param array       $templates Possible template files.
	 * @param string      $slug      Template slug.
	 * @param string|null $name      Template name.
	 *
	 * @return array
	 */
	public function mark_template_login_form( array $templates, string $slug, ?string $name ): array {
		if ( 'form' === $slug && 'user-login' === $name && ! is_user_logged_in() ) {
			$this->bbpress_login_form = true;
		}

		return $templates;
	}

	/**
	 * Mark a bbPress login widget form.
	 *
	 * @param mixed $title Widget title.
	 *
	 * @return mixed
	 */
	public function mark_widget_login_form( $title ) {
		if ( ! is_user_logged_in() ) {
			$this->bbpress_login_form = true;
		}

		return $title;
	}

	/**
	 * Add hCaptcha to a marked bbPress login form.
	 *
	 * @return void
	 */
	public function add_bbpress_captcha(): void {
		if ( ! $this->bbpress_login_form ) {
			return;
		}

		$this->bbpress_login_form = false;

		// Check the login status, because the class is always loading when bbPress is active.
		if ( hcaptcha()->settings()->is( 'bbp_status', 'login' ) ) {
			$this->add_captcha();
		}
	}
}
