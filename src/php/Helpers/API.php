<?php
/**
 * API class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use HCaptcha\AntiSpam\AntiSpam;
use WP_Error;

/**
 * Class Request.
 */
class API {
	/**
	 * Result of the hCaptcha widget verification.
	 *
	 * @var string|null
	 */
	private static ?string $result = null;

	/**
	 * Error codes of the hCaptcha widget verification.
	 *
	 * @var array
	 */
	private static array $error_codes = [];

	/**
	 * Verify hCaptcha and AntiSpam response.
	 *
	 * @param array $entry Entry.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify( array $entry = [] ): ?string {
		$entry = wp_parse_args(
			$entry,
			[
				'nonce_name'         => null, // If nonce name and action are null, nonce won't be checked.
				'nonce_action'       => null, // If nonce name and action are null, nonce won't be checked.
				'h-captcha-response' => null, // If null, will be taken from $_POST.
				'form_date_gmt'      => null, // Form date in GMT.
				'data'               => [], // Form data for antispam checks.
				'post_data'          => [], // Contains data to set in global POST for verifying hCaptcha response.
			]
		);

		if ( $entry['post_data'] ) {
			self::set_global_post_data( $entry );
		}

		$result = self::verify_nonce( $entry['nonce_name'], $entry['nonce_action'] );

		if ( null !== $result ) {
			return $result;
		}

		// Init AntiSpam object and add hcap_verify_request hook.
		( new AntiSpam( $entry ) )->init();

		$result = self::verify_request( $entry['h-captcha-response'] );

		if ( $entry['post_data'] ) {
			self::unset_global_post_data( $entry );
		}

		return $result;
	}
	/**
	 * Verify POST and return an error message as HTML.
	 *
	 * @param string $name   Nonce field name.
	 * @param string $action Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify_post_html( string $name = HCAPTCHA_NONCE, string $action = HCAPTCHA_ACTION ): ?string {
		$message = self::verify_post( $name, $action );

		if ( null === $message ) {
			return null;
		}

		$header = _n(
			'hCaptcha error:',
			'hCaptcha errors:',
			substr_count( $message, ';' ) + 1,
			'hcaptcha-for-forms-and-more'
		);

		if ( false === strpos( $message, $header ) ) {
			$message = $header . ' ' . $message;
		}

		return wp_kses_post( str_replace( $header, '<strong>' . $header . '</strong>', $message ) );
	}

	/**
	 * Verify POST.
	 *
	 * @param string $name   Nonce field name.
	 * @param string $action Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify_post( string $name = HCAPTCHA_NONCE, string $action = HCAPTCHA_ACTION ): ?string {
		$result = self::verify_nonce( $name, $action );

		return $result ?? self::verify_request();
	}

	/**
	 * Verify POST data.
	 *
	 * @param string $name      Nonce field name.
	 * @param string $action    Nonce action name.
	 * @param array  $post_data Post data.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify_post_data( string $name = HCAPTCHA_NONCE, string $action = HCAPTCHA_ACTION, array $post_data = [] ): ?string {
		$entry = [
			'nonce_name' => $name,
			'post_data'  => $post_data,
		];

		self::set_global_post_data( $entry );

		$result = self::verify_nonce( $name, $action );
		$result = $result ?? self::verify_request();

		self::unset_global_post_data( $entry );

		return $result;
	}

	/**
	 * Get POST keys for verifying hCaptcha response.
	 *
	 * @param array $entry Entry.
	 *
	 * @return array POST keys.
	 */
	private static function get_hcaptcha_post_keys( array $entry ): array {
		$response_name  = 'h-captcha-response';
		$widget_id_name = 'hcaptcha-widget-id';
		$hp_sig_name    = 'hcap_hp_sig';
		$token_name     = 'hcap_fst_token';
		$hp_name        = self::get_hp_name( $entry['post_data'] );

		return [
			$entry['nonce_name'],
			$response_name,
			$widget_id_name,
			$hp_sig_name,
			$token_name,
			$hp_name,
		];
	}

	/**
	 * Set hCaptcha POST data for verifying hCaptcha response.
	 *
	 * @param array $entry Entry.
	 *
	 * @return void
	 */
	private static function set_global_post_data( array $entry ): void {
		$post_data = $entry['post_data'];
		$keys      = self::get_hcaptcha_post_keys( $entry );

		foreach ( $keys as $key ) {
			if ( isset( $post_data[ $key ] ) ) {
				$_POST[ $key ] = $post_data[ $key ];
			}
		}
	}

	/**
	 * Unset hCaptcha POST data after verifying hCaptcha response.
	 *
	 * @param array $entry Entry.
	 *
	 * @return void
	 */
	private static function unset_global_post_data( array $entry ): void {
		$keys = self::get_hcaptcha_post_keys( $entry );

		foreach ( $keys as $key ) {
			unset( $_POST[ $key ] );
		}
	}

	/**
	 * Verify hCaptcha request.
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 *
	 * @return null|string Null on success, error message on failure.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public static function verify_request( $hcaptcha_response = null ): ?string {
		if ( null === $hcaptcha_response ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$hcaptcha_response = isset( $_POST['h-captcha-response'] )
				? filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS )
				: null;
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		// Do not make a remote request more than once.
		if ( hcaptcha()->has_result ) {
			return self::filtered_result( self::$result, self::$error_codes );
		}

		hcaptcha()->has_result = true;

		/**
		 * Filters the user IP to check whether it is denylisted.
		 * For denylisted IPs, any form submission fails.
		 *
		 * @param bool   $denylisted IP is denylisted.
		 * @param string $ip         IP string.
		 */
		$denylisted = apply_filters( 'hcap_blacklist_ip', false, hcap_get_user_ip( false ) );

		// The request is denylisted.
		if ( $denylisted ) {
			$result      = hcap_get_error_messages()['fail'];
			$error_codes = [ 'fail' ];

			return self::filtered_result( $result, $error_codes );
		}

		// Protection is not enabled.
		if ( ! HCaptcha::is_protection_enabled() ) {
			return self::filtered_result( null, [] );
		}

		// Check the honeypot field.
		if ( ! self::check_honeypot_field() ) {
			$result      = hcap_get_error_messages()['spam'];
			$error_codes = [ 'spam' ];

			return self::filtered_result( $result, $error_codes );
		}

		// Check the form submit time token.
		$check = self::check_fst_token();

		if ( is_wp_error( $check ) ) {
			$result      = $check->get_error_message();
			$error_codes = $check->get_error_codes();

			return self::filtered_result( $result, $error_codes );
		}

		$hcaptcha_response_sanitized = htmlspecialchars(
			filter_var( $hcaptcha_response, FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
			ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
		);

		// The hCaptcha response field is empty.
		if ( '' === $hcaptcha_response_sanitized ) {
			$result      = hcap_get_error_messages()['empty'];
			$error_codes = [ 'empty' ];

			return self::filtered_result( $result, $error_codes );
		}

		$params = [
			'secret'   => hcaptcha()->settings()->get_secret_key(),
			'response' => $hcaptcha_response_sanitized,
		];

		$ip = hcap_get_user_ip();

		if ( $ip ) {
			$params['remoteip'] = $ip;
		}

		return self::process_request( $params );
	}

	/**
	 * Get a honeypot field name.
	 *
	 * @param array $arr Array of form fields.
	 *
	 * @return string
	 */
	public static function get_hp_name( array $arr = [] ): string {
		foreach ( array_keys( $arr ) as $key ) {
			if ( 'hcap_hp_sig' !== $key && 0 === strpos( $key, 'hcap_hp_' ) ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Verify hCaptcha request.
	 *
	 * @deprecated 4.15.0 Use \HCaptcha\Helpers\API::verify_request().
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 *
	 * @return null|string Null on success, error message on failure.
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnused
	 */
	public static function request_verify( $hcaptcha_response = null ): ?string {
		_deprecated_function( __FUNCTION__, '4.15.0', '\HCaptcha\Helpers\API::verify_request()' );

		return self::verify_request( $hcaptcha_response );
	}

	/**
	 * Process request.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return string|null
	 */
	private static function process_request( array $params ): ?string {
		// Process API request.
		$raw_response = wp_remote_post(
			hcaptcha()->get_verify_url(),
			[ 'body' => $params ]
		);

		if ( is_wp_error( $raw_response ) ) {
			$result      = implode( "\n", $raw_response->get_error_messages() );
			$error_codes = $raw_response->get_error_codes();

			return self::filtered_result( $result, $error_codes );
		}

		$raw_body = wp_remote_retrieve_body( $raw_response );

		$fail_message = hcap_get_error_messages()['fail'];

		if ( empty( $raw_body ) ) {
			// Verification request failed.
			$result      = $fail_message;
			$error_codes = [ 'fail' ];

			return self::filtered_result( $result, $error_codes );
		}

		$body = Utils::json_decode_arr( $raw_body );

		if ( ! isset( $body['success'] ) || true !== (bool) $body['success'] ) {
			// Verification request is not verified.
			$error_codes        = $body['error-codes'] ?? [];
			$hcap_error_message = hcap_get_error_message( $error_codes );
			$result             = $hcap_error_message ?: $fail_message;
			$error_codes        = $hcap_error_message ? $error_codes : [ 'fail' ];

			return self::filtered_result( $result, $error_codes );
		}

		// Success.
		return self::filtered_result( null, [] );
	}

	/**
	 * Verify nonce.
	 *
	 * @param string|null $name Nonce field name.
	 * @param string|null $action Nonce action name.
	 *
	 * @return string|null
	 */
	private static function verify_nonce( ?string $name = HCAPTCHA_NONCE, ?string $action = HCAPTCHA_ACTION ): ?string {
		if ( null === $name && null === $action ) {
			// Do not verify nonce if we didn't request it.
			return null;
		}

		$nonce = isset( $_POST[ $name ] ) ?
			filter_var( wp_unslash( $_POST[ $name ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		// Verify nonce for logged-in users only.
		if (
			is_user_logged_in() &&
			HCaptcha::is_protection_enabled() &&
			! wp_verify_nonce( $nonce, $action )
		) {
			$errors      = hcap_get_error_messages();
			$result      = $errors['bad-nonce'];
			$error_codes = [ 'bad-nonce' ];

			return self::filtered_result( $result, $error_codes );
		}

		return null;
	}

	/**
	 * Get filtered result.
	 *
	 * @param string|null $result      Result.
	 * @param array       $error_codes Error codes.
	 *
	 * @return string|null
	 */
	private static function filtered_result( ?string $result, array $error_codes ): ?string {
		/**
		 * Filters the result of request verification.
		 *
		 * @since 4.15.0 The `$error_codes` parameter was deprecated.
		 *
		 * @param string|null $result     The result of verification. The null means success.
		 * @param string[]    $deprecated Not used.
		 * @param object      $error_info Error info. Contains error codes or empty array on success.
		 */
		$result = apply_filters( 'hcap_verify_request', $result, $error_codes, (object) [ 'codes' => $error_codes ] );

		$result = null === $result ? null : esc_html( (string) $result );

		self::$result      = $result;
		self::$error_codes = $error_codes;

		return $result;
	}

	/**
	 * Validates the honeypot field for spam prevention.
	 *
	 * @return bool True if the honeypot field is valid and empty, false otherwise.
	 */
	private static function check_honeypot_field(): bool {
		if ( ! hcaptcha()->settings()->is_on( 'honeypot' ) ) {
			return true;
		}

		// Honeypot check: require valid signature and ensure the honeypot field is empty.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Missing
		$hp_name      = self::get_hp_name( $_POST );
		$hp_value     = Request::filter_input( INPUT_POST, $hp_name );
		$hp_signature = Request::filter_input( INPUT_POST, 'hcap_hp_sig' );

		// Verify nonce for logged-in users only.
		$nonce_verified = ! is_user_logged_in() || wp_verify_nonce( $hp_signature, $hp_name );

		$check = $hp_name && $nonce_verified && '' === trim( $hp_value );

		/**
		 * Filters the result of the honeypot field check.
		 *
		 * @param bool $check True if the honeypot field is valid and empty, false otherwise.
		 */
		return (bool) apply_filters( 'hcap_check_honeypot_field', $check );
	}

	/**
	 * Check Form Submit Time token.
	 *
	 * @return true|WP_Error
	 */
	private static function check_fst_token() {
		if ( ! hcaptcha()->settings()->is_on( 'set_min_submit_time' ) ) {
			return true;
		}

		/**
		 * The Form Submit Time object.
		 *
		 * @var FormSubmitTime $fst_obj
		 */
		$fst_obj = hcaptcha()->get( FormSubmitTime::class );

		if ( ! $fst_obj ) {
			return hcap_get_wp_error( 'fst-no-object' );
		}

		$min_submit_time = hcaptcha()->settings()->get( 'min_submit_time' );

		/**
		 * Filters the result of the Form Submit Time token verification.
		 *
		 * @param true|WP_Error $check True, if the Form Submit Time token is valid. WP_Error object otherwise.
		 */
		return apply_filters( 'hcap_verify_fst_token', $fst_obj->verify_token( $min_submit_time ) );
	}
}
