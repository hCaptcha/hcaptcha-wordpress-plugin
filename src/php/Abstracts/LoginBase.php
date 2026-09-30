<?php
/**
 * LoginBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\LoginAttempts;
use WP_Error;
use WP_User;

/**
 * Class LoginBase
 */
abstract class LoginBase {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_login';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_login_nonce';

	/**
	 * Legacy login attempts the data option name.
	 */
	public const LOGIN_DATA = 'hcaptcha_login_data';

	/**
	 * User IP.
	 *
	 * @var string
	 */
	protected $ip;

	/**
	 * Login attempts store.
	 *
	 * @var LoginAttempts
	 */
	protected LoginAttempts $login_attempts;

	/**
	 * Login attempts record captured before authentication.
	 *
	 * @var string|null
	 */
	protected ?string $login_attempts_reset_token = null;

	/**
	 * The last native WordPress failed-login event recorded in this request.
	 *
	 * All enabled LoginBase integrations receive wp_login_failed. Deduplicating
	 * that action prevents one authentication failure from incrementing once per
	 * enabled integration.
	 *
	 * @var int
	 */
	private static int $last_wp_login_failed_action = 0;

	/**
	 * The hCaptcha was shown by the current class.
	 *
	 * @var bool
	 */
	protected bool $hcaptcha_shown = false;

	/**
	 * Login form shown.
	 *
	 * @var bool
	 */
	private bool $login_form_shown = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ip             = hcap_get_user_ip();
		$this->login_attempts = new LoginAttempts();
		$this->login_attempts->retire_legacy();

		if ( 0 < $this->get_login_limit() ) {
			$this->login_attempts_reset_token = $this->login_attempts->get_reset_token( $this->ip, time() );
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'hcap_signature', [ $this, 'display_signature' ] );
		add_action( 'login_form', [ $this, 'display_signature' ], PHP_INT_MAX );
		add_filter( 'login_form_middle', [ $this, 'add_signature' ], PHP_INT_MAX, 2 );
		add_filter( 'wp_authenticate_user', [ $this, 'check_signature' ], PHP_INT_MAX, 2 );
		add_filter( 'authenticate', [ $this, 'hide_login_error' ], 100, 3 );
		add_filter( 'hcap_auto_verify_unmatched_form', [ $this, 'defer_auto_verification' ], 10, 3 );
		add_filter( 'hcap_wp_login_can_skip_verification', [ $this, 'allow_wp_login_skip_verification' ] );

		add_action( 'wp_login', [ $this, 'login' ], 10, 2 );
		add_action( 'wp_login_failed', [ $this, 'login_failed' ] );

		add_action( 'hcap_delay_api', [ $this, 'delay_api' ], 0 );
	}

	/**
	 * Display signature.
	 *
	 * @return void
	 */
	public function display_signature(): void {
		$this->login_form_shown = true;

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
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function add_signature( $content, $args ): string {
		$content = (string) $content;

		ob_start();
		$this->display_signature();

		return $content . ob_get_clean();
	}

	/**
	 * Defer an unmatched auto-verified request to its signed login verifier.
	 *
	 * @param array|null|mixed $registered_form Registered auto-verified form.
	 * @param string           $path            Request path.
	 * @param string           $widget_id       Submitted widget ID.
	 *
	 * @return array|null|mixed
	 */
	public function defer_auto_verification( $registered_form, string $path, string $widget_id ) {
		$login_path = untrailingslashit( (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) );
		$id_info    = HCaptcha::decode_id_info();

		if (
			$login_path === $path &&
			$widget_id &&
			$id_info['valid'] &&
			$this->get_expected_id() === $id_info['id'] &&
			HCaptcha::widget_id_value( $id_info['id'] ) === $widget_id &&
			$this->is_login_verification_owner()
		) {
			return null;
		}

		return $registered_form;
	}

	/**
	 * Allow shared WordPress login verification to defer to the signed owner.
	 *
	 * The owner remains responsible for validating hCaptcha later in the same
	 * wp_authenticate_user filter chain.
	 *
	 * @param bool|mixed $can_skip Whether native WordPress login verification can be skipped.
	 *
	 * @return bool
	 */
	public function allow_wp_login_skip_verification( $can_skip ): bool {
		return $can_skip || $this->is_login_verification_owner();
	}

	/**
	 * Whether this signed owner still participates in the authentication chain.
	 *
	 * Integrations can remove another login verifier. Its signature must not
	 * authorize delegation after its verification callback has been removed.
	 *
	 * @return bool
	 */
	private function is_login_verification_owner(): bool {
		return false !== has_filter( 'wp_authenticate_user', [ $this, 'check_signature' ] ) &&
			null === HCaptcha::check_signature( static::class, 'login' );
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous callback failed authentication.
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

		if ( $check && $this->can_skip_login_verification() ) {
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
	 * Whether a valid signature can skip login verification.
	 *
	 * The signature identifies the rendering integration but does not preserve
	 * a threshold-dependent authorization decision. Always use the current bounded
	 * login-attempt state for the submitting requester. No client-held adaptive
	 * exemption remains, so signature age or session cannot extend that exemption.
	 * Above the threshold, only delegation to a signed owner in the same filter
	 * chain can skip this handler; the owner must verify its challenge.
	 *
	 * @return bool
	 */
	protected function can_skip_login_verification(): bool {
		return ! $this->is_login_limit_exceeded() ||
			apply_filters( 'hcap_wp_login_can_skip_verification', false );
	}

	/**
	 * Hides the login error when the relevant setting on.
	 *
	 * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated.
	 *                                        WP_Error or null otherwise.
	 * @param string                $username Username or email address.
	 * @param string                $password User password.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function hide_login_error( $user, $username, $password ) {
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}

		$settings = hcaptcha()->settings();

		if ( ! $settings || ! $settings->is_on( 'hide_login_errors' ) ) {
			return $user;
		}

		$ignore_codes = [ 'empty_username', 'empty_password' ];

		if ( in_array( $user->get_error_code(), $ignore_codes, true ) ) {
			return $user;
		}

		$codes         = $user->get_error_codes();
		$messages      = $user->get_error_messages();
		$hcap_messages = hcap_get_error_messages();

		foreach ( $codes as $i => $code ) {
			if ( ! ( array_key_exists( $code, $hcap_messages ) && $hcap_messages[ $code ] === $messages[ $i ] ) ) {
				// Remove all non-hCaptcha messages.
				$user->remove( $code );
			}
		}

		if ( ! $user->has_errors() ) {
			$user->add( 'login_error', __( 'Login failed.', 'hcaptcha-for-forms-and-more' ) );
		}

		return $user;
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
	public function login( string $user_login, WP_User $user ): void {
		$this->login_attempts->reset( $this->ip, $this->login_attempts_reset_token );
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
	public function login_failed( string $username, $error = null ): void {
		if ( doing_action( 'wp_login_failed' ) ) {
			$action_count = did_action( 'wp_login_failed' );

			if ( self::$last_wp_login_failed_action === $action_count ) {
				return;
			}

			self::$last_wp_login_failed_action = $action_count;
		}

		$login_limit = $this->get_login_limit();

		if ( 0 === $login_limit ) {
			return;
		}

		$this->login_attempts->increment( $this->ip, time(), $this->get_login_interval() );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_hcaptcha();
	}

	/**
	 * Get hCaptcha.
	 *
	 * @return string
	 */
	protected function get_hcaptcha(): string {
		if ( ! $this->is_login_limit_exceeded() ) {
			return '';
		}

		$args = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$this->hcaptcha_shown = true;

		return HCaptcha::form( $args );
	}

	/**
	 * Whether we process the native WP login form created in wp-login.php.
	 *
	 * @return bool
	 */
	protected function is_wp_login_form(): bool {
		return (
			did_action( 'login_init' ) &&
			( did_action( 'login_form_login' ) || did_action( 'login_form_entered_recovery_mode' ) ) &&
			HCaptcha::did_filter( 'login_link_separator' )
		);
	}

	/**
	 * Check whether the login limit is exceeded.
	 *
	 * @return bool
	 */
	protected function is_login_limit_exceeded(): bool {
		$login_limit = $this->get_login_limit();
		$count       = 0 === $login_limit ? 0 : $this->login_attempts->read( $this->ip, time() );

		/**
		 * Filters the login limit exceeded status.
		 *
		 * @param bool $is_login_limit_exceeded The protection status of a form.
		 */
		return apply_filters( 'hcap_login_limit_exceeded', $count >= $login_limit );
	}

	/**
	 * Get the bounded login failure limit.
	 *
	 * @return int
	 */
	private function get_login_limit(): int {
		$settings       = hcaptcha()->settings();
		$login_limit    = (int) ( $settings ? $settings->get( 'login_limit' ) : 0 );
		$login_interval = (int) ( $settings ? $settings->get( 'login_interval' ) : 0 );
		$max_minutes    = intdiv( LoginAttempts::MAX_TTL, MINUTE_IN_SECONDS );

		if ( $login_limit > 0 && $login_interval > $max_minutes ) {
			return 0;
		}

		return min( max( 0, $login_limit ), LoginAttempts::MAX_FAILURES );
	}

	/**
	 * Get the bounded login interval in seconds.
	 *
	 * Invalid or excessive values fail-safe by retaining state for the maximum
	 * supported interval.
	 *
	 * @return int
	 */
	private function get_login_interval(): int {
		$settings       = hcaptcha()->settings();
		$login_interval = (int) ( $settings ? $settings->get( 'login_interval' ) : 0 );
		$max_minutes    = intdiv( LoginAttempts::MAX_TTL, MINUTE_IN_SECONDS );

		return min( max( 1, $login_interval ), $max_minutes ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function login_base_verify( $user, string $password ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = API::verify( $this->get_login_entry() );

		if ( null === $error_message ) {
			return $user;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
	}

	/**
	 * Get hCaptcha verification data for a login form.
	 *
	 * @return array
	 */
	protected function get_login_entry(): array {
		return [
			'nonce_name'   => static::NONCE,
			'nonce_action' => static::ACTION,
			'data'         => EntryData::from_post(
				[
					'username' => [ 'log', 'username', 'user_login', 'eael-user-login' ],
				]
			),
			'expected_id'  => $this->get_expected_id(),
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	protected function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => 'login',
		];
	}

	/**
	 * Filters delay time for the hCaptcha API script.
	 *
	 * @param int|mixed $delay Number of milliseconds to delay hCaptcha API script.
	 *                         Any negative value means delay until user interaction.
	 *
	 * @return int
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function delay_api( $delay ): int {
		// Do not delay API request on login forms for compatibility with password managers.
		return $this->login_form_shown ? 0 : (int) $delay;
	}
}
