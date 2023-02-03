<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\WP;

use WordfenceLS\Controller_WordfenceLS;
use WP_Error;
use WP_User;

/**
 * Class Login
 */
class Login {

	/**
	 * Login attempts data option name.
	 */
	const LOGIN_DATA = 'hcaptcha_login_data';

	/**
	 * User IP.
	 *
	 * @var string
	 */
	private $ip;

	/**
	 * Login attempts data.
	 *
	 * @var array
	 */
	private $login_data;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ip         = hcap_get_user_ip();
		$this->login_data = get_option( self::LOGIN_DATA, [] );

		if ( ! isset( $this->login_data[ $this->ip ] ) ) {
			$this->login_data[ $this->ip ] = [];
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_action( 'login_form', [ $this, 'add_captcha' ] );
		add_action( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
		add_action( 'wp_login', [ $this, 'login' ], 10, 2 );
		add_action( 'wp_login_failed', [ $this, 'login_failed' ], 10, 2 );
		add_filter( 'woocommerce_login_credentials', [ $this, 'remove_filter_wp_authenticate_user' ] );
		add_action( 'um_submit_form_errors_hook_login', [ $this, 'remove_filter_wp_authenticate_user' ] );
		add_filter( 'wpforms_user_registration_process_login_process_credentials', [ $this, 'remove_filter_wp_authenticate_user' ] );

		if ( ! class_exists( Controller_WordfenceLS::class ) ) {
			return;
		}

		add_action( 'login_enqueue_scripts', [ $this, 'remove_wordfence_scripts' ], 0 );
		add_filter( 'wordfence_ls_require_captcha', [ $this, 'wordfence_ls_require_captcha' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		if ( $this->is_login_limit_exceeded() ) {
			hcap_form_display( 'hcaptcha_login', 'hcaptcha_login_nonce' );
		}
	}

	/**
	 * Check whether the login limit is exceeded.
	 *
	 * @return bool
	 */
	private function is_login_limit_exceeded() {
		$now            = time();
		$login_limit    = (int) hcaptcha()->settings()->get( 'login_limit' );
		$login_interval = (int) hcaptcha()->settings()->get( 'login_interval' );
		$count          = count(
			array_filter(
				$this->login_data[ $this->ip ],
				static function ( $time ) use ( $now, $login_interval ) {
					return $time > $now - $login_interval * MINUTE_IN_SECONDS;
				}
			)
		);

		return $count >= $login_limit;
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
		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = hcaptcha_get_verify_message_html(
			'hcaptcha_login_nonce',
			'hcaptcha_login'
		);

		if ( null === $error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}

	/**
	 * Clear attempts data on successful login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       WP_User object of the logged-in user.
	 *
	 * @return void
	 */
	public function login( $user_login, $user ) {
		unset( $this->login_data[ $this->ip ] );

		update_option( self::LOGIN_DATA, $this->login_data );
	}

	/**
	 * Update attempts data on failed login.
	 *
	 * @param string   $username Username or email address.
	 * @param WP_Error $error    A WP_Error object with the authentication failure details.
	 *
	 * @return void
	 */
	public function login_failed( $username, $error ) {
		$this->login_data[ $this->ip ][] = time();

		update_option( self::LOGIN_DATA, $this->login_data );
	}

	/**
	 * Remove standard WP login captcha if we do logging in via WC.
	 *
	 * @param array $credentials Credentials.
	 *
	 * @return array
	 */
	public function remove_filter_wp_authenticate_user( $credentials ) {
		remove_filter( 'wp_authenticate_user', [ $this, 'verify' ] );

		return $credentials;
	}


	/**
	 * Remove Wordfence login scripts.
	 *
	 * @return void
	 */
	public function remove_wordfence_scripts() {
		$controller_wordfence_ls = Controller_WordfenceLS::shared();

		remove_action( 'login_enqueue_scripts', [ $controller_wordfence_ls, '_login_enqueue_scripts' ] );
	}

	/**
	 * Do not require Wordfence captcha.
	 *
	 * @return false
	 */
	public function wordfence_ls_require_captcha() {

		return false;
	}
}
