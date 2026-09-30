<?php
/**
 * LoginAttempts class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Bounded failed-login storage.
 *
 * The store uses 1024 deterministic slots instead of attacker-controlled option
 * names. Each slot contains one record of at most 92 bytes, its counter saturates
 * at 1,000,000, and its TTL is clamped between one minute and ten years. A live
 * collision fails closed, while an expired slot can be reused.
 *
 * The increment interface normally uses one atomic database statement followed
 * by one keyed read, so concurrent failures cannot overwrite each other. An
 * active or absent read uses one query, expiry cleanup uses at most three,
 * increment uses two unless a transient lock error requires a bounded retry,
 * and reset uses at most one. The database remains authoritative when a
 * persistent object cache is installed; cache operations only invalidate option
 * entries. None of these costs depends on historical population.
 */
final class LoginAttempts {

	/**
	 * Maximum number of address slots stored for one site.
	 */
	public const SLOT_COUNT = 1024;

	/**
	 * Maximum stored count for one address.
	 */
	public const MAX_FAILURES = 1000000;

	/**
	 * Minimum record lifetime in seconds.
	 */
	public const MIN_TTL = MINUTE_IN_SECONDS;

	/**
	 * Maximum record lifetime in seconds (ten years).
	 *
	 * LoginBase treats a larger configured interval as immediate-CAPTCHA mode.
	 */
	public const MAX_TTL = 315360000;

	/**
	 * Maximum encoded record size in bytes.
	 */
	public const MAX_RECORD_BYTES = 92;

	/**
	 * Login attempt option prefix.
	 */
	public const OPTION_PREFIX = 'hcaptcha_login_attempt_';

	/**
	 * Legacy retirement marker.
	 */
	public const RETIREMENT_OPTION = 'hcaptcha_login_data_retired';

	/**
	 * Legacy login data option.
	 */
	private const LEGACY_OPTION = 'hcaptcha_login_data';

	/**
	 * Stored record pattern.
	 */
	private const RECORD_PATTERN = '/^([a-f0-9]{64})\|([0-9]{1,7})\|([0-9]{1,19})$/D';

	/**
	 * MySQL stored record pattern.
	 */
	private const MYSQL_RECORD_PATTERN = '^[a-f0-9]{64}[|][0-9]{1,7}[|][0-9]{1,19}$';

	/**
	 * Retire the legacy whole-map option without loading or deserializing it.
	 *
	 * @return void
	 */
	public function retire_legacy(): void {
		global $wpdb;

		// Direct queries avoid loading a potentially very large legacy value.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$retired = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_id FROM $wpdb->options WHERE option_name = %s LIMIT 1",
				self::RETIREMENT_OPTION
			)
		);

		if ( null !== $retired ) {
			return;
		}

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name = %s",
				self::LEGACY_OPTION
			)
		);

		if ( false !== $deleted ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO $wpdb->options (option_name, option_value, autoload) VALUES (%s, %s, %s)",
					self::RETIREMENT_OPTION,
					'1',
					'no'
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		wp_cache_delete( self::LEGACY_OPTION, 'options' );
		wp_cache_delete( self::RETIREMENT_OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Read the active failure count for an address.
	 *
	 * A live slot owned by another address or a malformed record is treated as
	 * saturated. This keeps slot exhaustion fail-safe.
	 *
	 * @param string $address Address resolved by the trusted IP resolver.
	 * @param int    $now     Current Unix timestamp.
	 *
	 * @return int
	 */
	public function read( string $address, int $now ): int {
		$address_hash = $this->address_hash( $address );
		$option_name  = $this->option_name( $address_hash );

		for ( $attempt = 0; $attempt < 2; ++$attempt ) {
			$read = $this->read_raw( $option_name );

			if ( ! $read['success'] ) {
				return self::MAX_FAILURES;
			}

			$raw    = $read['value'];
			$record = $this->decode( $raw );

			if ( null === $raw ) {
				return 0;
			}

			if ( null === $record ) {
				return self::MAX_FAILURES;
			}

			if ( $record['expires'] > $now ) {
				return hash_equals( $record['address'], $address_hash ) ? $record['count'] : self::MAX_FAILURES;
			}

			if ( $this->delete_raw( $option_name, $raw ) ) {
				return 0;
			}
		}

		return self::MAX_FAILURES;
	}

	/**
	 * Capture the live record that a successful request may reset.
	 *
	 * The returned value is an opaque compare-and-swap token. A reset using this
	 * token cannot delete failures recorded after the snapshot. Missing, expired,
	 * malformed, colliding, and unreadable records cannot be reset.
	 *
	 * @param string $address Address resolved by the trusted IP resolver.
	 * @param int    $now     Current Unix timestamp.
	 *
	 * @return string|null
	 */
	public function get_reset_token( string $address, int $now ): ?string {
		$address_hash = $this->address_hash( $address );
		$option_name  = $this->option_name( $address_hash );
		$read         = $this->read_raw( $option_name );

		if ( ! $read['success'] || null === $read['value'] ) {
			return null;
		}

		$raw    = $read['value'];
		$record = $this->decode( $raw );

		if ( null === $record || ! hash_equals( $record['address'], $address_hash ) ) {
			return null;
		}

		if ( $record['expires'] > $now ) {
			return $raw;
		}

		// A failed compare-and-delete means the record changed while it was
		// sampled. Never turn that concurrent state into a reset token.
		$this->delete_raw( $option_name, $raw );

		return null;
	}

	/**
	 * Atomically increment failures for an address.
	 *
	 * Counts use a conservative inactivity window: every failure moves expiry to
	 * now plus the configured interval. This can retain protection longer than
	 * the former timestamp list, but never grants additional CAPTCHA-free tries.
	 * The count saturates at MAX_FAILURES without growing the record.
	 *
	 * @param string $address Address resolved by the trusted IP resolver.
	 * @param int    $now     Current Unix timestamp.
	 * @param int    $ttl     Failure-window lifetime in seconds.
	 *
	 * @return int Active count, or MAX_FAILURES when the slot is unavailable.
	 */
	public function increment( string $address, int $now, int $ttl ): int {
		global $wpdb;

		$address_hash = $this->address_hash( $address );
		$option_name  = $this->option_name( $address_hash );
		$ttl          = min( max( self::MIN_TTL, $ttl ), self::MAX_TTL );
		$expires      = $now + $ttl;
		$new_record   = $this->encode( $address_hash, 1, $expires );
		$prefix       = $address_hash . '|';
		$suffix       = '|' . $expires;

		$query = $wpdb->prepare(
			"INSERT INTO $wpdb->options (option_name, option_value, autoload)
			VALUES (%s, %s, %s)
			ON DUPLICATE KEY UPDATE option_value = CASE
				WHEN option_value REGEXP %s
					AND CAST(SUBSTRING_INDEX(option_value, '|', -1) AS UNSIGNED) <= %d
					THEN %s
				WHEN option_value REGEXP %s
					AND SUBSTRING_INDEX(option_value, '|', 1) = %s
					THEN CONCAT(
						%s,
						LEAST(
							CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(option_value, '|', 2), '|', -1) AS UNSIGNED) + 1,
							%d
						),
						%s
					)
				ELSE option_value
			END",
			$option_name,
			$new_record,
			'no',
			self::MYSQL_RECORD_PATTERN,
			$now,
			$new_record,
			self::MYSQL_RECORD_PATTERN,
			$address_hash,
			$prefix,
			self::MAX_FAILURES,
			$suffix
		);

		$result = false;

		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $query );

			if ( false !== $result || ! preg_match( '/deadlock|lock wait timeout/i', (string) $wpdb->last_error ) ) {
				break;
			}

			if ( $attempt < 2 ) {
				usleep( 1000 * ( 1 << $attempt ) );
			}
		}

		wp_cache_delete( $option_name, 'options' );

		return false === $result ? self::MAX_FAILURES : $this->read( $address, $now );
	}

	/**
	 * Reset failures captured before authentication.
	 *
	 * The compare-and-swap token prevents a successful request from deleting a
	 * failure recorded concurrently after that request captured its state. It
	 * also prevents a colliding address from being affected.
	 *
	 * @param string      $address      Address resolved by the trusted IP resolver.
	 * @param string|null $reset_token  Opaque token returned by get_reset_token().
	 *
	 * @return void
	 */
	public function reset( string $address, ?string $reset_token ): void {
		global $wpdb;

		if ( null === $reset_token ) {
			return;
		}

		$address_hash = $this->address_hash( $address );
		$option_name  = $this->option_name( $address_hash );
		$record       = $this->decode( $reset_token );

		if ( null === $record || ! hash_equals( $record['address'], $address_hash ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s",
				$option_name,
				$reset_token
			)
		);

		wp_cache_delete( $option_name, 'options' );
	}

	/**
	 * Delete all bounded and legacy login-attempt data.
	 *
	 * @return void
	 */
	public static function delete_all(): void {
		global $wpdb;

		$like = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name = %s OR option_name = %s OR option_name LIKE %s",
				self::LEGACY_OPTION,
				self::RETIREMENT_OPTION,
				$like
			)
		);

		wp_cache_delete( self::LEGACY_OPTION, 'options' );
		wp_cache_delete( self::RETIREMENT_OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Read an option value without populating the options cache.
	 *
	 * @param string $option_name Option name.
	 *
	 * @return array{success: bool, value: string|null}
	 */
	private function read_raw( string $option_name ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		return [
			'success' => ! $wpdb->last_error,
			'value'   => null === $value ? null : (string) $value,
		];
	}

	/**
	 * Delete a record only when it has not changed since it was read.
	 *
	 * @param string $option_name Option name.
	 * @param string $raw         Expected raw value.
	 *
	 * @return bool
	 */
	private function delete_raw( string $option_name, string $raw ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s",
				$option_name,
				$raw
			)
		);

		if ( 1 === $deleted ) {
			wp_cache_delete( $option_name, 'options' );
		}

		return 1 === $deleted;
	}

	/**
	 * Decode a stored record.
	 *
	 * @param string|null $raw Raw option value.
	 *
	 * @return array{address: string, count: int, expires: int}|null
	 */
	private function decode( ?string $raw ): ?array {
		if ( null === $raw || ! preg_match( self::RECORD_PATTERN, $raw, $matches ) ) {
			return null;
		}

		$count   = (int) $matches[2];
		$expires = (int) $matches[3];

		if ( $count < 1 || $count > self::MAX_FAILURES || $expires < 1 ) {
			return null;
		}

		return [
			'address' => $matches[1],
			'count'   => $count,
			'expires' => $expires,
		];
	}

	/**
	 * Encode a stored record.
	 *
	 * @param string $address_hash Address hash.
	 * @param int    $count        Failure count.
	 * @param int    $expires      Expiration timestamp.
	 *
	 * @return string
	 */
	private function encode( string $address_hash, int $count, int $expires ): string {
		return $address_hash . '|' . $count . '|' . $expires;
	}

	/**
	 * Hash an address before storage.
	 *
	 * @param string $address Address.
	 *
	 * @return string
	 */
	private function address_hash( string $address ): string {
		// Keying prevents clients from deliberately selecting another address's slot.
		return hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) );
	}

	/**
	 * Get the deterministic slot option name.
	 *
	 * @param string $address_hash Address hash.
	 *
	 * @return string
	 */
	private function option_name( string $address_hash ): string {
		$slot = hexdec( substr( $address_hash, 0, 4 ) ) % self::SLOT_COUNT;

		return self::OPTION_PREFIX . sprintf( '%04d', $slot );
	}
}
