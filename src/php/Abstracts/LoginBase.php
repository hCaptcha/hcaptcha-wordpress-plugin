<?php
/**
 * LoginBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class LoginBase
 */
abstract class LoginBase {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_login';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_login_nonce';

	/**
	 * Login attempts data option name.
	 */
	const LOGIN_DATA = 'hcaptcha_login_data';

	/**
	 * User IP.
	 *
	 * @var string
	 */
	protected $ip;

	/**
	 * Login attempts data.
	 *
	 * @var array
	 */
	protected $login_data;

	/**
	 * The hCaptcha was shown by the current class.
	 *
	 * @var bool
	 */
	protected $hcaptcha_shown = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ip         = hcap_get_user_ip();
		$this->login_data = get_option( self::LOGIN_DATA, [] );

		if ( ! isset( $this->login_data[ $this->ip ] ) || ! is_array( $this->login_data[ $this->ip ] ) ) {
			$this->login_data[ $this->ip ] = [];
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		add_action( 'hcap_signature', [ $this, 'display_signature' ] );
		add_action( 'login_form', [ $this, 'display_signature' ], PHP_INT_MAX );
		add_filter( 'login_form_middle', [ $this, 'add_signature' ], PHP_INT_MAX, 2 );
		add_filter( 'wp_authenticate_user', [ $this, 'check_signature' ], PHP_INT_MAX, 2 );

		add_action( 'wp_login', [ $this, 'login' ], 10, 2 );
		add_action( 'wp_login_failed', [ $this, 'login_failed' ] );
	}

	/**
	 * Display signature.
	 *
	 * @return void
	 */
	public function display_signature() {
		HCaptcha::display_signature( static::class, 'login', $this->hcaptcha_shown );
	}

	/**
	 * Add signature.
	 *
	 * @param string|mixed $content Content to display. Default empty.
	 * @param array        $args    Array of login form arguments.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_signature( $content, array $args ): string {
		$content = (string) $content;

		ob_start();
		$this->display_signature();

		return $content . ob_get_clean();
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object
	 *                                   if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function check_signature( $user, string $password ) {
		if ( ! $this->is_wp_login_form() ) {
			return $user;
		}

		$check = HCaptcha::check_signature( static::class, 'login' );

		if ( $check ) {
			return $user;
		}

		if ( false === $check ) {
			$code          = 'bad-signature';
			$error_message = hcap_get_error_messages()[ $code ];

			return new WP_Error( $code, $error_message, 400 );
		}

		return $this->login_base_verify( $user, $password );
	}

	/**
	 * Clear attempts data on successful login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       WP_User object of the logged-in user.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function login( string $user_login, WP_User $user ) {
		unset( $this->login_data[ $this->ip ] );

		update_option( self::LOGIN_DATA, $this->login_data, false );
	}

	/**
	 * Update attempts data on failed login.
	 *
	 * @param string        $username Username or email address.
	 * @param WP_Error|null $error    A WP_Error object with the authentication failure details.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function login_failed( string $username, $error = null ) {
		$this->login_data[ $this->ip ][] = time();

		$now            = time();
		$login_interval = (int) hcaptcha()->settings()->get( 'login_interval' );

		foreach ( $this->login_data as & $login_datum ) {
			$login_datum = array_values(
				array_filter(
					$login_datum,
					static function ( $time ) use ( $now, $login_interval ) {
						return $time > $now - $login_interval * MINUTE_IN_SECONDS;
					}
				)
			);
		}

		unset( $login_datum );

		update_option( self::LOGIN_DATA, $this->login_data, false );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha() {
		if ( ! $this->is_login_limit_exceeded() ) {
			return;
		}

		$args = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'login',
			],
		];

		HCaptcha::form_display( $args );

		$this->hcaptcha_shown = true;
	}

	/**
	 * Whether we process the native WP login form created in wp-login.php.
	 *
	 * @return bool
	 */
	protected function is_wp_login_form(): bool {
		return (
			did_action( 'login_init' ) &&
			did_action( 'login_form_login' ) &&
			HCaptcha::did_filter( 'login_link_separator' )
		);
	}

	/**
	 * Check whether the login limit is exceeded.
	 *
	 * @return bool
	 */
	protected function is_login_limit_exceeded(): bool {
		$now               = time();
		$login_limit       = (int) hcaptcha()->settings()->get( 'login_limit' );
		$login_interval    = (int) hcaptcha()->settings()->get( 'login_interval' );
		$login_data_for_ip = $this->login_data[ $this->ip ] ?? [];
		$count             = count(
			array_filter(
				$login_data_for_ip,
				static function ( $time ) use ( $now, $login_interval ) {
					return $time > $now - $login_interval * MINUTE_IN_SECONDS;
				}
			)
		);

		/**
		 * Filters the login limit exceeded status.
		 *
		 * @param bool $is_login_limit_exceeded The protection status of a form.
		 */
		return apply_filters( 'hcap_login_limit_exceeded', $count >= $login_limit );
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object
	 *                                   if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function login_base_verify( $user, string $password ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $user;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
	}
}
