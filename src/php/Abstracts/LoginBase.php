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
		add_action( 'wp_login', [ $this, 'login' ], 10, 2 );
		add_action( 'wp_login_failed', [ $this, 'login_failed' ], 10 );
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
	 * Protect form filter.
	 *
	 * @param bool|mixed $value   The protection status of a form.
	 * @param string[]   $source  The source of the form (plugin, theme, WordPress Core).
	 * @param int|string $form_id Form id.
	 *
	 * @return bool
	 */
	public function protect_form( $value, array $source, $form_id ): bool {
		if ( 'login' === $form_id && HCaptcha::get_class_source( static::class ) === $source ) {
			return false;
		}

		return (bool) $value;
	}
}
