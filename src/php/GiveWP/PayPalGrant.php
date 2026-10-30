<?php
/**
 * Single-use proof for GiveWP PayPal order creation.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GiveWP;

use JsonException;

/**
 * Carries an already verified hCaptcha response to GiveWP's final donation request.
 */
class PayPalGrant {
	/**
	 * Private options prefix.
	 */
	private const PREFIX = 'hcap_givewp_paypal_';

	/**
	 * Grant lifetime, in seconds.
	 */
	private const LIFETIME = 1200;

	/**
	 * Issue one grant for a remotely verified response.
	 *
	 * @param string $response   hCaptcha response token.
	 * @param array  $binding    Canonical donation identity.
	 * @param array  $siteverify Normalized siteverify response.
	 *
	 * @return bool Whether the grant was stored.
	 */
	public static function issue( string $response, array $binding, array $siteverify ): bool {
		global $wpdb;

		if ( '' === $response || true !== ( $siteverify['success'] ?? false ) ) {
			return false;
		}

		self::cleanup();

		$expires = time() + self::LIFETIME;
		$payload = wp_json_encode(
			[
				'binding'    => self::binding_hash( $binding ),
				'siteverify' => $siteverify,
			]
		);

		if ( ! is_string( $payload ) ) {
			return false;
		}

		$value = sprintf( '%010d', $expires ) . '|' . $payload;

		// A unique option_name makes grant issuance atomic; add_option() can update duplicates.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$stored = $wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => self::option_name( $response ),
				'option_value' => $value,
				'autoload'     => 'no',
			],
			[ '%s', '%s', '%s' ]
		);

		if ( 1 !== $stored ) {
			return false;
		}

		if ( ! wp_next_scheduled( 'hcap_givewp_paypal_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'hcap_givewp_paypal_cleanup' );
		}

		return true;
	}

	/**
	 * Atomically consume a matching grant before final donation processing.
	 *
	 * @param string $response hCaptcha response token.
	 * @param array  $binding  Canonical donation identity.
	 *
	 * @return array|null Verified siteverify response, or null on failure.
	 */
	public static function redeem( string $response, array $binding ): ?array {
		global $wpdb;

		if ( '' === $response ) {
			return null;
		}

		$name = self::option_name( $response );
		// Read the database directly so a stale option cache cannot resurrect a claimed grant.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
				$name
			)
		);

		if ( ! is_string( $value ) || strlen( $value ) < 12 || '|' !== $value[10] ) {
			return null;
		}

		$expires = substr( $value, 0, 10 );
		try {
			$grant = json_decode( substr( $value, 11 ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$grant = null;
		}

		if (
			! is_array( $grant ) ||
			! is_string( $grant['binding'] ?? null ) ||
			true !== ( $grant['siteverify']['success'] ?? false ) ||
			! ctype_digit( $expires ) ||
			(int) $expires < time() ||
			! hash_equals( $grant['binding'], self::binding_hash( $binding ) )
		) {
			return null;
		}

		// The exact-value predicate allows only one concurrent claimant to succeed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$claimed = $wpdb->delete(
			$wpdb->options,
			[
				'option_name'  => $name,
				'option_value' => $value,
			],
			[ '%s', '%s' ]
		);

		return 1 === $claimed ? $grant['siteverify'] : null;
	}

	/**
	 * Remove at most 50 expired grants per invocation.
	 *
	 * @return void
	 */
	public static function cleanup(): void {
		global $wpdb;

		// Bypass option cache for the bounded expired-row sweep.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s AND LEFT(option_value, 10) <= %s LIMIT 50",
				$wpdb->esc_like( self::PREFIX ) . '%',
				sprintf( '%010d', time() )
			)
		);

		foreach ( $expired as $grant ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$wpdb->options,
				[
					'option_name'  => $grant->option_name,
					'option_value' => $grant->option_value,
				],
				[ '%s', '%s' ]
			);
		}
	}

	/**
	 * Derive a private option name without persisting the response token.
	 *
	 * @param string $response hCaptcha response token.
	 *
	 * @return string
	 */
	private static function option_name( string $response ): string {
		return self::PREFIX . hash_hmac( 'sha256', $response, wp_salt() );
	}

	/**
	 * Hash the donor and form binding so options contain no personal data.
	 *
	 * @param array $binding Canonical donation identity.
	 *
	 * @return string
	 */
	private static function binding_hash( array $binding ): string {
		return hash_hmac( 'sha256', wp_json_encode( $binding ), wp_salt() );
	}
}
