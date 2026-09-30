<?php
/**
 * EntryData class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tutor;

use HCaptcha\Helpers\EntryData as FormEntryData;

/**
 * Read non-secret Tutor LMS form fields for anti-spam checks.
 */
class EntryData {

	/**
	 * Get login form data.
	 *
	 * @return array
	 */
	public static function login(): array {
		return self::post_fields( [ 'log' ] );
	}

	/**
	 * Get registration form data.
	 *
	 * @return array
	 */
	public static function registration(): array {
		$data = self::post_fields( [ 'first_name', 'last_name', 'user_login', 'email' ] );

		return self::add_name( $data, 'first_name', 'last_name' );
	}

	/**
	 * Get lost-password form data.
	 *
	 * @return array
	 */
	public static function lost_password(): array {
		return self::post_fields( [ 'user_login' ] );
	}

	/**
	 * Get checkout form data.
	 *
	 * @return array
	 */
	public static function checkout(): array {
		$data = self::post_fields( [ 'billing_first_name', 'billing_last_name', 'billing_email' ] );

		if ( isset( $data['billing_email'] ) ) {
			$data['email'] = $data['billing_email'];
		}

		return self::add_name( $data, 'billing_first_name', 'billing_last_name' );
	}

	/**
	 * Read and sanitize scalar POST fields.
	 *
	 * @param string[] $fields Field names.
	 *
	 * @return array
	 */
	private static function post_fields( array $fields ): array {
		$field_map = [];

		foreach ( $fields as $field ) {
			$field_map[ $field ] = $field;
		}

		return FormEntryData::from_post( $field_map );
	}

	/**
	 * Add a name assembled from sanitized form fields.
	 *
	 * @param array  $data       Form data.
	 * @param string $first_name First name field.
	 * @param string $last_name  Last name field.
	 *
	 * @return array
	 */
	private static function add_name( array $data, string $first_name, string $last_name ): array {
		$name = trim( ( $data[ $first_name ] ?? '' ) . ' ' . ( $data[ $last_name ] ?? '' ) );

		if ( '' !== $name ) {
			$data['name'] = $name;
		}

		return $data;
	}
}
