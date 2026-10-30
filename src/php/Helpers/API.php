<?php
/**
 * API class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use HCaptcha\AntiSpam\AntiSpam;
use HCaptcha\AntiSpam\DisposableEmail;
use WP_Error;

/**
 * Class Request.
 */
class API {
	/**
	 * Common field names used by integrations without a form-specific entry map.
	 */
	private const ENTRY_FIELD_MAP = [
		'username'   => [ 'username', 'user_login', 'log', 'edd_user_login', 'user_name' ],
		'first_name' => [ 'first_name', 'billing_first_name' ],
		'last_name'  => [ 'last_name', 'billing_last_name' ],
	];

	/**
	 * Result of the hCaptcha widget verification.
	 *
	 * @var string|null
	 * @noinspection PhpPropertyOnlyWrittenInspection
	 */
	private static ?string $result = null;

	/**
	 * Error codes of the hCaptcha widget verification.
	 *
	 * @var array
	 * @noinspection PhpPropertyOnlyWrittenInspection
	 */
	private static array $error_codes = [];

	/**
	 * Normalized siteverify response.
	 *
	 * @var array
	 */
	private static array $siteverify_response = [];

	/**
	 * Context of the last completed verification in this request.
	 *
	 * @var array
	 */
	private static array $request_context = [];

	/**
	 * Guard and remote proof outcomes, independent of per-entry policy and result filters.
	 *
	 * @var array
	 */
	private static array $request_result = [];

	/**
	 * Server-owned proof from a previously verified, single-use integration request.
	 *
	 * @var array
	 */
	private static array $preverified_context = [];

	/**
	 * Recheck local policy after a trusted integration atomically redeems remote proof.
	 *
	 * @param array $entry               Verification entry.
	 * @param array $siteverify_response Normalized, successful siteverify response.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify_preverified( array $entry, array $siteverify_response ): ?string {
		if ( true !== ( $siteverify_response['success'] ?? false ) ) {
			return hcap_get_error_messages()['fail'];
		}

		$proof = [
			'expected_id' => (array) ( $entry['expected_id'] ?? [] ),
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'widget_id'   => wp_unslash( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] ?? '' ),
			'response'    => self::get_hcaptcha_response_sanitized( $entry['h-captcha-response'] ?? null ),
			'siteverify'  => self::normalize_siteverify_response( $siteverify_response ),
		];

		if ( '' === $proof['response'] || ! is_string( $proof['widget_id'] ) || '' === $proof['widget_id'] ) {
			return hcap_get_error_messages()['fail'];
		}

		self::$preverified_context = $proof;

		try {
			return self::verify( $entry );
		} finally {
			self::$preverified_context = [];
		}
	}

	/**
	 * Verify hCaptcha and AntiSpam response.
	 *
	 * @param array $entry Entry.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify( array $entry = [] ): ?string {
		$entry = self::add_submission_data( $entry );

		$entry = wp_parse_args(
			$entry,
			[
				'nonce_name'         => null, // If nonce name and action are null, nonce won't be checked.
				'nonce_action'       => null, // If nonce name and action are null, nonce won't be checked.
				'h-captcha-response' => null, // If null, will be taken from $_POST.
				'form_date_gmt'      => null, // Form date in GMT.
				'data'               => [], // Form data for antispam checks.
				'post_data'          => [], // Contains data to set in global POST for verifying hCaptcha response.
				'expected_id'        => [], // Expected hCaptcha widget id.
			]
		);

		$entry['data'] = EntryData::without_sensitive_fields( (array) $entry['data'] );

		if ( array_key_exists( 'email_addresses', $entry ) ) {
			$entry = EntryData::with_emails( $entry, EntryData::get_emails( $entry ) );
		}

		self::set_global_post_data( $entry );

		$result = self::verify_widget_id( $entry['expected_id'] );
		$result = $result ?? self::verify_nonce(
			$entry['nonce_name'],
			$entry['nonce_action'],
			$entry['expected_id']
		);

		// Init AntiSpam object and add hcap_verify_request hook.
		if ( null === $result ) {
			( new AntiSpam( EntryData::for_antispam( $entry ) ) )->init();

			$result = self::verify_request( $entry['h-captcha-response'], $entry );
		}

		self::unset_global_post_data( $entry );

		return $result;
	}

	/**
	 * Add safe submitted fields while preserving data selected by the integration.
	 *
	 * @param array $entry Verification entry.
	 *
	 * @return array
	 */
	private static function add_submission_data( array $entry ): array {
		$has_data       = array_key_exists( 'data', $entry );
		$data           = $has_data ? (array) $entry['data'] : [];
		$content_fields = array_diff( array_keys( EntryData::without_sensitive_fields( $data ) ), [ 'email' ] );

		if ( $content_fields ) {
			return $entry;
		}

		$submitted_data = array_key_exists( 'post_data', $entry )
			? EntryData::from_array( (array) $entry['post_data'], self::ENTRY_FIELD_MAP )
			: EntryData::from_post( self::ENTRY_FIELD_MAP );

		// Keep an unselected email as form content, not as the address for email policy checks.
		if ( isset( $submitted_data['email'] ) ) {
			$email = $submitted_data['email'];
			unset( $submitted_data['email'] );

			if ( ! EntryData::get_emails( $entry ) ) {
				EntryData::add_field( $submitted_data, 'submitted_email', $email, 'email' );
			}
		}
		$entry['data'] = array_replace( $submitted_data, $data );

		if ( ! $has_data && ! array_key_exists( 'email_addresses', $entry ) ) {
			$entry = EntryData::with_emails( $entry, [] );
		}

		return $entry;
	}
	/**
	 * Verify POST and return an error message as HTML.
	 *
	 * @param string $name        Nonce field name.
	 * @param string $action      Nonce action name.
	 * @param array  $expected_id Server-selected form identity.
	 * @param array  $entry       Verification entry with native form data.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify_post_html( string $name = HCAPTCHA_NONCE, string $action = HCAPTCHA_ACTION, array $expected_id = [], array $entry = [] ): ?string {
		$message = self::verify_post( $name, $action, $expected_id, $entry );

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
	 * Verify POST data.
	 *
	 * @param string $name        Nonce field name.
	 * @param string $action      Nonce action name.
	 * @param array  $post_data   Post data.
	 * @param array  $expected_id Server-selected form identity.
	 * @param array  $entry       Verification entry with native form data.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify_post_data( string $name = HCAPTCHA_NONCE, string $action = HCAPTCHA_ACTION, array $post_data = [], array $expected_id = [], array $entry = [] ): ?string {
		$entry['nonce_name']   = $name;
		$entry['nonce_action'] = $action;
		$entry['post_data']    = $post_data;
		$entry['expected_id']  = $expected_id;

		return self::verify( $entry );
	}

	/**
	 * Verify POST.
	 *
	 * @param string $name        Nonce field name.
	 * @param string $action      Nonce action name.
	 * @param array  $expected_id Server-selected form identity.
	 * @param array  $entry       Verification entry with native form data.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public static function verify_post( string $name = HCAPTCHA_NONCE, string $action = HCAPTCHA_ACTION, array $expected_id = [], array $entry = [] ): ?string {
		$entry['nonce_name']   = $name;
		$entry['nonce_action'] = $action;
		$entry['expected_id']  = $expected_id;

		return self::verify( $entry );
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
		$hp_name        = self::get_hp_name( $entry['post_data'] ?? [] );

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
		if ( ! $entry['post_data'] ) {
			return;
		}

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
		if ( ! $entry['post_data'] ) {
			return;
		}

		$keys = self::get_hcaptcha_post_keys( $entry );

		foreach ( $keys as $key ) {
			unset( $_POST[ $key ] );
		}
	}

	/**
	 * Verify that posted hCaptcha widget id belongs to the expected form.
	 *
	 * @param array $expected_id Expected hCaptcha widget id.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	private static function verify_widget_id( array $expected_id = [] ): ?string {
		if ( ! $expected_id ) {
			return null;
		}

		$expected_id = [
			'source'  => (array) ( $expected_id['source'] ?? [] ),
			'form_id' => $expected_id['form_id'] ?? 0,
		];

		$info = HCaptcha::decode_id_info();
		$id   = $info['id'];

		// Honeypot status is signed form metadata, not part of the form identity.
		unset( $id['honeypot'] );

		if (
			// Nonce is checked in verify().
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			empty( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] ) ||
			! $info['valid'] ||
			$expected_id !== $id
		) {
			$errors = hcap_get_error_messages();

			return self::filtered_result( $errors['bad-signature'], [ 'bad-signature' ], $expected_id );
		}

		return null;
	}

	// phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
	/**
	 * Verify hCaptcha request.
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 * @param array       $entry             Entry data.
	 *
	 * @return null|string Null on success, error message on failure.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public static function verify_request( $hcaptcha_response = null, array $entry = [] ): ?string {
		$expected_id = (array) ( $entry['expected_id'] ?? [] );
		$result      = self::verify_widget_id( $expected_id );

		if ( null !== $result ) {
			return $result;
		}

		$hcaptcha_response = self::get_hcaptcha_response_sanitized( $hcaptcha_response );
		$context           = [
			'expected_id' => $expected_id,
			// This value is compared only, never interpreted as a trusted identity.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'widget_id'   => wp_unslash( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] ?? '' ),
			'response'    => $hcaptcha_response,
		];
		$preverified       = self::$preverified_context;

		if ( $preverified && array_intersect_key( $preverified, $context ) !== $context ) {
			return self::filtered_result( hcap_get_error_messages()['fail'], [ 'fail' ], $expected_id );
		}

		if ( $preverified || ! hcaptcha()->has_result || ! self::can_reuse_request_result( $context ) ) {
			hcaptcha()->has_result = true;
			self::$request_context = $context;
			self::$request_result  = self::verify_request_once( $hcaptcha_response, $entry, (bool) $preverified );
		}

		// Email policy belongs to this entry, while timing and remote proof are single-use.
		$verify_email = self::$request_result['verify_email'];

		if ( $expected_id !== self::$request_context['expected_id'] ) {
			$verify_email = HCaptcha::is_protection_enabled( $expected_id );
		}

		if ( $verify_email && ! self::check_disposable_email( $entry ) ) {
			return self::filtered_result(
				hcap_get_error_messages()['disposable-email'],
				[ 'disposable-email' ],
				$expected_id,
				self::$request_result['siteverify']
			);
		}

		if ( self::$request_result['remote_pending'] ) {
			self::$request_result = array_merge(
				self::$request_result,
				$preverified
					? self::verification_outcome( null, [], $preverified['siteverify'] )
					: self::process_request( self::get_params( $hcaptcha_response ) ),
				[
					'verify_email'   => true,
					'remote_pending' => false,
				]
			);
		}

		return self::filtered_result(
			self::$request_result['result'],
			self::$request_result['error_codes'],
			self::$request_result['expected_id'] ?? $expected_id,
			self::$request_result['siteverify']
		);
	}
	// phpcs:enable Generic.Metrics.CyclomaticComplexity.TooHigh

	/**
	 * Reuse a result only for its form, or the same remotely verified challenge.
	 *
	 * @param array $context Current verification context.
	 *
	 * @return bool
	 */
	private static function can_reuse_request_result( array $context ): bool {
		if ( ! self::$request_result ) {
			return false;
		}

		if ( $context === self::$request_context ) {
			return true;
		}

		// An exemption has no remote proof and must stay bound to its expected form.
		if ( true !== ( self::$request_result['siteverify']['success'] ?? false ) ) {
			return false;
		}

		$previous = self::$request_context;
		unset( $context['expected_id'], $previous['expected_id'] );

		return $context === $previous;
	}

	/**
	 * Verify one form's local guards after validating its identity.
	 *
	 * @param string $hcaptcha_response Sanitized hCaptcha response.
	 * @param array  $entry             Entry data.
	 * @param bool   $preverified       Whether a trusted integration redeemed remote proof.
	 *
	 * @return array
	 */
	private static function verify_request_once( string $hcaptcha_response, array $entry, bool $preverified = false ): array {
		$expected_id = (array) ( $entry['expected_id'] ?? [] );

		// Apply the form's protection settings before checking its timing token.
		$protection_enabled = HCaptcha::is_protection_enabled( $expected_id );
		$fst_check          = self::check_fst_token( $preverified );

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

			return self::verification_outcome( $result, $error_codes );
		}

		// Protection is not enabled.
		if ( ! $protection_enabled ) {
			return self::verification_outcome( null, [] );
		}

		$settings = hcaptcha()->settings();

		// The hCaptcha credentials are not configured.
		if ( '' === $settings->get_site_key() || '' === $settings->get_secret_key() ) {
			$error_codes = [ 'missing-keys' ];

			return self::verification_outcome( hcap_get_error_messages()['missing-keys'], $error_codes ) + [ 'expected_id' => [] ];
		}

		// Check the honeypot field.
		if ( ! self::check_honeypot_field() ) {
			$result      = hcap_get_error_messages()['spam'];
			$error_codes = [ 'spam' ];

			return self::verification_outcome( $result, $error_codes );
		}
		// The hCaptcha response field is empty.
		if ( '' === $hcaptcha_response ) {
			$result      = hcap_get_error_messages()['empty'];
			$error_codes = [ 'empty' ];

			return self::verification_outcome( $result, $error_codes );
		}

		// Check the form submit time token.
		if ( is_wp_error( $fst_check ) ) {
			$result      = $fst_check->get_error_message();
			$error_codes = $fst_check->get_error_codes();

			return self::verification_outcome( $result, $error_codes );
		}

		$outcome                   = self::verification_outcome( null, [] );
		$outcome['verify_email']   = true;
		$outcome['remote_pending'] = true;

		return $outcome;
	}

	/**
	 * Create an unfiltered local or remote verification outcome.
	 *
	 * @param string|null $result              Result.
	 * @param array       $error_codes         Error codes.
	 * @param array       $siteverify_response Normalized remote response.
	 *
	 * @return array
	 */
	private static function verification_outcome( ?string $result, array $error_codes, array $siteverify_response = [] ): array {
		return [
			'result'         => $result,
			'error_codes'    => $error_codes,
			'siteverify'     => $siteverify_response,
			'verify_email'   => false,
			'remote_pending' => false,
		];
	}

	/**
	 * Get the normalized response from the latest siteverify request.
	 *
	 * @return array
	 */
	public static function get_siteverify_response(): array {
		return self::$siteverify_response;
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
	 * Process request.
	 *
	 * @param array $params      Request parameters.
	 * @return array
	 */
	private static function process_request( array $params ): array {
		// Process API request.
		$raw_response = wp_remote_post(
			hcaptcha()->get_verify_url(),
			[ 'body' => $params ]
		);

		if ( is_wp_error( $raw_response ) ) {
			$result      = implode( "\n", $raw_response->get_error_messages() );
			$error_codes = $raw_response->get_error_codes();

			return self::verification_outcome( $result, $error_codes );
		}

		$raw_body = wp_remote_retrieve_body( $raw_response );

		$fail_message = hcap_get_error_messages()['fail'];

		if ( empty( $raw_body ) ) {
			// Verification request failed.
			$result      = $fail_message;
			$error_codes = [ 'fail' ];

			return self::verification_outcome( $result, $error_codes );
		}

		$body                = Utils::json_decode_arr( $raw_body );
		$siteverify_response = self::normalize_siteverify_response( $body );

		if ( ! isset( $body['success'] ) || true !== (bool) $body['success'] ) {
			// Verification request is not verified.
			$error_codes        = $body['error-codes'] ?? [];
			$hcap_error_message = hcap_get_error_message( $error_codes );
			$result             = $hcap_error_message ?: $fail_message;
			$error_codes        = $hcap_error_message ? $error_codes : [ 'fail' ];

			return self::verification_outcome( $result, $error_codes, $siteverify_response );
		}

		// Success.
		return self::verification_outcome( null, [], $siteverify_response );
	}

	/**
	 * Normalize the siteverify response.
	 *
	 * @param array $body Decoded siteverify response.
	 *
	 * @return array
	 */
	private static function normalize_siteverify_response( array $body ): array {
		return [
			'success'      => isset( $body['success'] ) && true === (bool) $body['success'],
			'challenge_ts' => is_scalar( $body['challenge_ts'] ?? null )
				? sanitize_text_field( (string) $body['challenge_ts'] )
				: '',
			'hostname'     => is_scalar( $body['hostname'] ?? null )
				? sanitize_text_field( (string) $body['hostname'] )
				: '',
			'credit'       => array_key_exists( 'credit', $body ) ? (bool) $body['credit'] : null,
			'error-codes'  => self::sanitize_siteverify_array( $body['error-codes'] ?? [] ),
		];
	}

	/**
	 * Sanitize an array from the siteverify response.
	 *
	 * @param mixed $value Value.
	 *
	 * @return array
	 */
	private static function sanitize_siteverify_array( $value ): array {
		$value = is_array( $value ) ? $value : [];
		$value = array_filter( $value, 'is_scalar' );

		return array_values( array_map( 'sanitize_text_field', $value ) );
	}

	/**
	 * Verify nonce.
	 *
	 * @param string|null $name        Nonce field name.
	 * @param string|null $action      Nonce action name.
	 * @param array       $expected_id Expected hCaptcha widget id.
	 *
	 * @return string|null
	 */
	private static function verify_nonce( ?string $name = HCAPTCHA_NONCE, ?string $action = HCAPTCHA_ACTION, array $expected_id = [] ): ?string {
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
			HCaptcha::is_protection_enabled( $expected_id ) &&
			! wp_verify_nonce( $nonce, $action )
		) {
			$errors      = hcap_get_error_messages();
			$result      = $errors['bad-nonce'];
			$error_codes = [ 'bad-nonce' ];

			return self::filtered_result( $result, $error_codes, $expected_id );
		}

		return null;
	}

	/**
	 * Get filtered result.
	 *
	 * @param string|null $result              Result.
	 * @param array       $error_codes         Error codes.
	 * @param array       $expected_id         Expected hCaptcha widget id.
	 * @param array       $siteverify_response Normalized siteverify response.
	 *
	 * @return string|null
	 */
	public static function filtered_result(
		?string $result,
		array $error_codes,
		array $expected_id = [],
		array $siteverify_response = []
	): ?string {
		/**
		 * Filters the result of request verification.
		 *
		 * @since 4.15.0 The `$error_codes` parameter was deprecated.
		 *
		 * @param string|null $result     The result of verification. The null means success.
		 * @param string[]    $deprecated Not used.
		 * @param object      $error_info Error info. Contains error codes, expected widget id, and siteverify response.
		 */
		$error_info = (object) [
			'codes'       => $error_codes,
			'expected_id' => $expected_id,
			'siteverify'  => $siteverify_response,
		];

		$result = apply_filters( 'hcap_verify_request', $result, $error_codes, $error_info );

		$result = null === $result ? null : esc_html( (string) $result );

		self::$result              = $result;
		self::$error_codes         = $error_codes;
		self::$siteverify_response = $siteverify_response;

		return $result;
	}

	/**
	 * Validates the honeypot field for spam prevention.
	 *
	 * @return bool True if the honeypot field is valid and empty, false otherwise.
	 */
	private static function check_honeypot_field(): bool {
		$honeypot = hcaptcha()->settings()->is_on( 'honeypot' );
		$id_info  = HCaptcha::decode_id_info();
		$id       = $id_info['id'];

		if ( $id_info['valid'] && array_key_exists( 'honeypot', $id ) && is_bool( $id['honeypot'] ) ) {
			$honeypot = $id['honeypot'];
		}

		if ( ! $honeypot ) {
			return true;
		}

		// Honeypot check: require valid signature and ensure the honeypot field is empty.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Missing
		$hp_name      = self::get_hp_name( $_POST );
		$hp_value     = Request::filter_input( INPUT_POST, $hp_name );
		$hp_signature = Request::filter_input( INPUT_POST, 'hcap_hp_sig' );

		// Verify nonce for logged-in users only.
		$nonce_verified = ! is_user_logged_in() || wp_verify_nonce( $hp_signature, $hp_name );

		$check = $hp_name && $nonce_verified && is_string( $hp_value ) && '' === trim( $hp_value );

		/**
		 * Filters the result of the honeypot field check.
		 *
		 * @param bool $check True if the honeypot field is valid and empty, false otherwise.
		 */
		return (bool) apply_filters( 'hcap_check_honeypot_field', $check );
	}

	/**
	 * Check and consume the Form Submit Time token.
	 *
	 * The token is consumed before any later verification branch can return, so
	 * every form submission remains single-use, including early failures.
	 *
	 * @param bool $preverified Whether this is an atomically redeemed second check.
	 *
	 * @return true|WP_Error
	 */
	private static function check_fst_token( bool $preverified = false ) {
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

		if ( $preverified ) {
			return apply_filters( 'hcap_verify_fst_token', true );
		}

		/**
		 * Filters the result of the Form Submit Time token verification.
		 *
		 * @param true|WP_Error $check True, if the Form Submit Time token is valid. WP_Error object otherwise.
		 */
		return apply_filters( 'hcap_verify_fst_token', $fst_obj->verify_token( $min_submit_time ) );
	}

	/**
	 * Check if email is NOT disposable.
	 * Return true if the email is not disposable.
	 *
	 * @param array $entry Entry data.
	 *
	 * @return bool
	 */
	private static function check_disposable_email( array $entry ): bool {
		if ( ! hcaptcha()->settings()->is_on( 'disposable_email' ) ) {
			return true;
		}

		// Init DisposableEmail object and verify the email.
		return ( new DisposableEmail() )->verify( $entry );
	}

	/**
	 * Get request parameters.
	 *
	 * @param string $hcaptcha_response_sanitized Sanitized hCaptcha response.
	 *
	 * @return array
	 */
	private static function get_params( string $hcaptcha_response_sanitized ): array {
		$params = [
			'secret'   => hcaptcha()->settings()->get_secret_key(),
			'response' => $hcaptcha_response_sanitized,
			'sitekey'  => hcaptcha()->settings()->get_site_key(),
		];

		$ip = hcap_get_user_ip( false );

		if ( $ip ) {
			$params['remoteip'] = $ip;
		}

		return $params;
	}

	/**
	 * Get sanitized hCaptcha response.
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 *
	 * @return string
	 */
	private static function get_hcaptcha_response_sanitized( ?string $hcaptcha_response ): string {
		if ( null === $hcaptcha_response ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$hcaptcha_response = isset( $_POST['h-captcha-response'] )
				? filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS )
				: null;
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		return htmlspecialchars(
			filter_var( $hcaptcha_response, FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
			ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
		);
	}
}
