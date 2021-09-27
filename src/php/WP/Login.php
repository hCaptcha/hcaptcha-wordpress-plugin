<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use WP_Error;
use WP_User;

/**
 * Class Login
 */
class Login {

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
		add_action( 'login_form', [ $this, 'add_captcha' ] );
		add_action( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );

		add_action( 'login_head', [ $this, 'login_head' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_login', 'hcaptcha_login_nonce' );
	}

	/**
	 * Verify login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous
	 *                                   callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, $password ) {
		$error_message = hcaptcha_get_verify_message_html(
			'hcaptcha_login_nonce',
			'hcaptcha_login'
		);

		if ( null === $error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', __( 'Invalid Captcha', 'hcaptcha-for-forms-and-more' ), 400 );
	}

	/**
	 * Print styles to fit hcaptcha widget to the login form.
	 */
	public function login_head() {
		?>
		<style>
			@media (max-width: 349px) {
				.h-captcha {
					display: flex;
					justify-content: center;
				}
			}
			@media (min-width: 350px) {
				#login {
					width: 350px;
				}
			}
		</style>
		<?php
	}
}
