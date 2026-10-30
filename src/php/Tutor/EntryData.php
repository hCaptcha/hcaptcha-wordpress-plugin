<?php
/**
 * EntryData class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tutor;

use HCaptcha\Helpers\EntryData as FormEntryData;
use Tutor\Models\BillingModel;

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
		unset( $data['email'] );

		// Tutor updates only supplied fields and retains the current customer's other billing values.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email_supplied = array_key_exists( 'billing_email', $_POST );

		if ( class_exists( BillingModel::class ) && ! $email_supplied && get_current_user_id() ) {
			$billing = ( new BillingModel() )->get_info( get_current_user_id() );

			if ( $billing && isset( $billing->billing_email ) && is_string( $billing->billing_email ) ) {
				$data['billing_email'] = $billing->billing_email;
			}
		}

		if ( isset( $data['billing_email'] ) && is_string( $data['billing_email'] ) ) {
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
