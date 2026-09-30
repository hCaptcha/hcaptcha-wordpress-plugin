<?php
/**
 * EntryData class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Maps submitted, non-secret form fields to anti-spam entry data.
 */
class EntryData {
	/**
	 * Remove fields identified as credentials, verification tokens, or payment data.
	 *
	 * @param array $data Form data.
	 *
	 * @return array
	 */
	public static function without_sensitive_fields( array $data ): array {
		$filtered = [];

		foreach ( $data as $key => $value ) {
			if ( is_resource( $value ) || is_object( $value ) || self::is_sensitive_field( (string) $key ) ) {
				continue;
			}

			$filtered[ $key ] = is_array( $value )
				? self::without_sensitive_fields( $value )
				: $value;
		}

		return $filtered;
	}

	/**
	 * Prepare an entry for anti-spam providers without request transport data.
	 *
	 * @param array $entry Verification entry.
	 *
	 * @return array
	 */
	public static function for_antispam( array $entry ): array {
		unset( $entry['post_data'] );

		return self::without_sensitive_fields( $entry );
	}

	/**
	 * Check whether a field name identifies a secret or payment value.
	 *
	 * @param string $key Field name.
	 *
	 * @return bool
	 */
	public static function is_sensitive_field( string $key ): bool {
		if ( preg_match( '/password|passwd|passcode|pwd|token|nonce|secret|captcha|csrf|cvv|cvc|csc|iban|swift|routing|stripe|paypal|authorization|authentication/i', $key ) ) {
			return true;
		}

		$key = (string) preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1_$2', $key );
		$key = (string) preg_replace( '/([a-z])([A-Z])/', '$1_$2', $key );

		return (bool) preg_match(
			'/(?:^|[^a-z0-9])(?:(?:user|customer|account)?password\d*|passwd|passcode|pwd|pass\d*|' .
			'(?:access|refresh|session|api)?token|nonce|secret|(?:h|re)?captcha|csrf|' .
			'verification|security|payment|card(?:number|holder|expiry|expiration|security|code)?|cvv|cvc|csc|' .
			'iban|swift|routing|sort[_-]?code|expiry|expiration|exp[_-]?(?:month|year|date)|stripe|paypal|' .
			'bank(?:account|details|number)?|(?:account|acct)[_-]?(?:number|num)|' .
			'credit(?:card)?|debit(?:card)?|transaction|authorization|authentication|pin|otp|2fa|auth|cc)' .
			'(?:$|[^a-z0-9])|(?:^|[^a-z0-9])(?:api|access|private|license)[_-]?key(?:$|[^a-z0-9])/i',
			$key
		);
	}

	/**
	 * Add a submitted field without overwriting another field with the same label.
	 *
	 * @param array  $data     Entry data.
	 * @param string $label    Field label or name.
	 * @param mixed  $value    Submitted value.
	 * @param string $field_id Stable field identifier.
	 *
	 * @return void
	 */
	public static function add_field( array &$data, string $label, $value, string $field_id = '' ): void {
		$key = trim( $label );

		if ( '' === $key ) {
			$key = $field_id;
		}

		if ( '' === $key ) {
			return;
		}

		if ( array_key_exists( $key, $data ) ) {
			$key .= ' [' . ( '' !== $field_id ? $field_id : count( $data ) ) . ']';
		}

		while ( array_key_exists( $key, $data ) ) {
			$key .= ' [2]';
		}

		$data[ $key ] = $value;
	}

	/**
	 * Add a composed name without replacing a submitted field named "name".
	 *
	 * @param array $data  Entry data.
	 * @param array $parts Name parts.
	 *
	 * @return void
	 */
	public static function add_name( array &$data, array $parts ): void {
		$name = trim( implode( ' ', array_filter( $parts, 'is_scalar' ) ) );

		if ( '' !== $name && ! array_key_exists( 'name', $data ) ) {
			$data['name'] = $name;
		}
	}

	/**
	 * Check whether a field type represents submitted content.
	 *
	 * @param string $type Field type.
	 *
	 * @return bool
	 */
	public static function is_content_field_type( string $type ): bool {
		return '' !== $type && ! in_array( strtolower( $type ), [ 'submit', 'button', 'reset', 'hidden', 'file', 'upload', 'html' ], true ) &&
			! self::is_sensitive_field( $type );
	}

	/**
	 * Check field metadata for secret or payment indicators.
	 *
	 * @param string ...$fields Field names, types or labels.
	 *
	 * @return bool
	 */
	public static function has_sensitive_field( string ...$fields ): bool {
		foreach ( $fields as $field ) {
			if ( self::is_sensitive_field( $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check that a field has a usable name and no sensitive metadata.
	 *
	 * @param string $key       Entry key.
	 * @param string ...$fields Field names, types or labels.
	 *
	 * @return bool
	 */
	public static function is_safe_field( string $key, string ...$fields ): bool {
		return '' !== $key && ! self::has_sensitive_field( $key, ...$fields );
	}

	/**
	 * Get mapped and additional non-secret fields from the current POST request.
	 *
	 * @param array $field_map Entry key => submitted field name(s).
	 *
	 * @return array
	 */
	public static function from_post( array $field_map ): array {
		// The hCaptcha nonce is verified by API::verify() after this data is collected.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return self::from_array( wp_unslash( $_POST ), $field_map );
	}

	/**
	 * Get mapped and additional non-secret fields from a submission.
	 *
	 * Configured aliases use canonical entry keys. Additional non-secret fields keep their submitted names,
	 * so custom form fields remain available for anti-spam checks.
	 *
	 * @param array $submission Submitted form fields, already unslashed.
	 * @param array $field_map  Entry key => submitted field name(s).
	 *
	 * @return array
	 */
	public static function from_array( array $submission, array $field_map ): array {
		$data         = [];
		$mapped_names = [];

		foreach ( $field_map as $entry_key => $field_names ) {
			$mapped_names[] = (array) $field_names;
			$value          = self::first_value( $submission, (array) $field_names, $entry_key );

			if ( null !== $value ) {
				$data[ $entry_key ] = $value;
			}
		}

		$mapped_names = array_merge( ...$mapped_names );

		if ( ! isset( $data['name'] ) ) {
			$name = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );

			if ( '' !== $name ) {
				$data['name'] = $name;
			}
		}

		foreach ( $submission as $key => $value ) {
			$key = (string) $key;

			if ( in_array( $key, $mapped_names, true ) || ! self::is_safe_field( $key ) || self::is_transport_field( $key ) ) {
				continue;
			}

			$value = self::sanitize_value( $value );

			if ( null !== $value && '' !== $value && [] !== $value ) {
				self::add_field( $data, $key, $value );
			}
		}

		return $data;
	}

	/**
	 * Sanitize scalar and array field values while discarding objects.
	 *
	 * @param mixed $value Submitted value.
	 *
	 * @return array|string|null
	 */
	public static function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			$result = [];

			foreach ( self::without_sensitive_fields( $value ) as $key => $item ) {
				$sanitized = self::sanitize_value( $item );

				if ( null !== $sanitized ) {
					$result[ $key ] = $sanitized;
				}
			}

			return $result;
		}

		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : null;
	}

	/**
	 * Exclude request transport controls from custom form data.
	 *
	 * @param string $key Submitted field name.
	 *
	 * @return bool
	 */
	private static function is_transport_field( string $key ): bool {
		return 1 === preg_match( '/^(?:hcap[_-]|hcaptcha[_-]|g-recaptcha|_wp|wp-submit$|submit$|redirect|data$|post_data$|formData$|fields$|bbp_(?:topic|reply|forum)_id$)/i', $key );
	}

	/**
	 * Find the first non-empty scalar field from the configured aliases.
	 *
	 * @param array  $submission Submitted form fields.
	 * @param array  $field_names Submitted field names.
	 * @param string $entry_key Entry key.
	 *
	 * @return array|string|null
	 */
	private static function first_value( array $submission, array $field_names, string $entry_key ) {
		foreach ( $field_names as $field_name ) {
			$value = $submission[ $field_name ] ?? null;

			if ( is_array( $value ) && ! in_array( $entry_key, [ 'email', 'username', 'first_name', 'last_name', 'name' ], true ) ) {
				$value = self::sanitize_value( $value );

				if ( $value ) {
					return $value;
				}
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = 'email' === $entry_key
				? sanitize_email( (string) $value )
				: sanitize_text_field( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return null;
	}
}
