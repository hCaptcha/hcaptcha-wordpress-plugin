<?php
/**
 * FormSubmitTimeStore class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use WP_Error;

/**
 * Bounded single-use Form Submit Time token storage.
 *
 * The store keeps at most 1,000 outstanding tokens for one site and at most
 * 10 for one trusted client identity. All token records share one non-autoloaded
 * option, while one fixed database advisory lock serializes allocation and
 * consumption. Thus, storage and limiter bookkeeping use at most one option
 * row regardless of the number of clients or requests. Direct database reads
 * and cache invalidation keep the database authoritative when a persistent
 * object cache is enabled.
 */
final class FormSubmitTimeStore {

	/**
	 * Maximum outstanding tokens for one site.
	 */
	public const MAX_OUTSTANDING = 1000;

	/**
	 * Maximum outstanding tokens for one client identity.
	 */
	public const MAX_PER_CLIENT = 10;

	/**
	 * Maximum seconds to wait for the registry lock.
	 */
	private const LOCK_TIMEOUT = 1;

	/**
	 * Registry option name.
	 */
	public const REGISTRY_OPTION = 'hcaptcha_fst_tokens';

	/**
	 * Reserve storage for a token.
	 *
	 * A valid outstanding token owned by the same client may be replaced in the same database writing.
	 * This prevents browser rebinds from consuming extra quota without weakening the single-use contract.
	 *
	 * @param string $signature        Token signature.
	 * @param array  $payload          Signed token payload.
	 * @param string $client_id        Hashed trusted client identity.
	 * @param int    $expires_at       Token expiration time.
	 * @param int    $now              Current Unix timestamp.
	 * @param string $replace_signature Optional signature to replace.
	 * @param array  $replace_payload   Optional payload to replace.
	 *
	 * @return true|WP_Error True on success, error otherwise.
	 */
	public function reserve(
		string $signature,
		array $payload,
		string $client_id,
		int $expires_at,
		int $now,
		string $replace_signature = '',
		array $replace_payload = []
	) {
		$lock = $this->acquire_lock();

		if ( '' === $lock ) {
			return $this->storage_error();
		}

		try {
			$registry = $this->read_registry();

			if ( is_wp_error( $registry ) ) {
				return $registry;
			}

			$original_registry = $registry;
			$registry          = $this->remove_expired( $registry, $now );

			if ( isset( $registry[ $signature ] ) ) {
				return $this->storage_error();
			}

			if ( $this->can_replace( $registry, $replace_signature, $replace_payload, $payload, $client_id ) ) {
				unset( $registry[ $replace_signature ] );
			}

			$client_count = 0;

			foreach ( $registry as $record ) {
				if ( hash_equals( (string) $record['client_id'], $client_id ) ) {
					++$client_count;
				}
			}

			if ( $client_count >= self::MAX_PER_CLIENT || count( $registry ) >= self::MAX_OUTSTANDING ) {
				if ( $registry !== $original_registry && ! $this->write_registry( $registry ) ) {
					return $this->storage_error();
				}

				return new WP_Error(
					'fst-rate-limited',
					__( 'Too many outstanding form timing tokens.', 'hcaptcha-for-forms-and-more' )
				);
			}

			$registry[ $signature ] = [
				'payload'    => $payload,
				'client_id'  => $client_id,
				'expires_at' => $expires_at,
			];

			return $this->write_registry( $registry ) ? true : $this->storage_error();
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * Determine whether an outstanding record can be replaced by its owner.
	 *
	 * @param array  $registry          Token registry.
	 * @param string $replace_signature Signature to replace.
	 * @param array  $replace_payload   Payload to replace.
	 * @param array  $payload           Payload for the new token.
	 * @param string $client_id         Hashed trusted client identity.
	 *
	 * @return bool Whether the record is an exact same-client match.
	 */
	private function can_replace(
		array $registry,
		string $replace_signature,
		array $replace_payload,
		array $payload,
		string $client_id
	): bool {
		if (
			'' === $replace_signature ||
			! isset( $registry[ $replace_signature ]['payload'], $registry[ $replace_signature ]['client_id'] ) ||
			! is_array( $registry[ $replace_signature ]['payload'] ) ||
			! is_string( $registry[ $replace_signature ]['client_id'] )
		) {
			return false;
		}

		return (string) ( $replace_payload['post_id'] ?? '' ) === (string) ( $payload['post_id'] ?? '' ) &&
			$replace_payload === $registry[ $replace_signature ]['payload'] &&
			hash_equals( $registry[ $replace_signature ]['client_id'], $client_id );
	}

	/**
	 * Check whether a token is outstanding.
	 *
	 * @param string $signature Token signature.
	 * @param array  $payload   Signed token payload.
	 *
	 * @return bool|WP_Error True when outstanding, false when absent, or an error.
	 */
	public function has( string $signature, array $payload ) {
		$registry = $this->read_registry();

		if ( is_wp_error( $registry ) ) {
			return $registry;
		}

		return isset( $registry[ $signature ]['payload'] ) &&
			is_array( $registry[ $signature ]['payload'] ) &&
			$payload === $registry[ $signature ]['payload'];
	}

	/**
	 * Atomically consume an outstanding token and remove all expired records.
	 *
	 * @param string $signature Token signature.
	 * @param array  $payload   Signed token payload.
	 *
	 * @return true|WP_Error True on success, error otherwise.
	 */
	public function consume( string $signature, array $payload ) {
		$lock = $this->acquire_lock();

		if ( '' === $lock ) {
			return $this->storage_error();
		}

		try {
			$registry = $this->read_registry();

			if ( is_wp_error( $registry ) ) {
				return $registry;
			}

			$original_registry = $registry;
			$token_exists      = isset( $registry[ $signature ]['payload'] ) &&
				is_array( $registry[ $signature ]['payload'] ) &&
				$payload === $registry[ $signature ]['payload'];
			$registry          = $this->remove_expired( $registry, time() );

			if ( ! $token_exists ) {
				if ( $registry !== $original_registry && ! $this->write_registry( $registry ) ) {
					return $this->storage_error();
				}

				return hcap_get_wp_error( 'fst-replayed-or-expired' );
			}

			unset( $registry[ $signature ] );

			return $this->write_registry( $registry ) ? true : $this->storage_error();
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * Atomically consume a token from the pre-registry transient store.
	 *
	 * The same fixed lock serializes legacy verification during the short upgrade window,
	 * so two submissions cannot consume one old token.
	 *
	 * @param string $transient Legacy transient name.
	 * @param array  $payload   Signed token payload.
	 *
	 * @return true|WP_Error True on success, error otherwise.
	 * @noinspection PhpUnused
	 */
	public function consume_legacy( string $transient, array $payload ) {
		$lock = $this->acquire_lock();

		if ( '' === $lock ) {
			return $this->storage_error();
		}

		try {
			if ( get_transient( $transient ) !== $payload ) {
				return hcap_get_wp_error( 'fst-replayed-or-expired' );
			}

			return delete_transient( $transient ) ? true : $this->storage_error();
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * Delete all store data.
	 *
	 * @return void
	 */
	public static function delete_all(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name = %s",
				self::REGISTRY_OPTION
			)
		);

		self::clear_option_cache( self::REGISTRY_OPTION );
	}

	/**
	 * Remove expired and malformed records.
	 *
	 * The input is rejected before this method when it exceeds the global cap,
	 * so cleanup work is always bounded by MAX_OUTSTANDING.
	 *
	 * @param array $registry Token registry.
	 * @param int   $now      Current Unix timestamp.
	 *
	 * @return array Clean registry.
	 */
	private function remove_expired( array $registry, int $now ): array {
		foreach ( $registry as $signature => $record ) {
			if (
				! is_string( $signature ) ||
				! is_array( $record ) ||
				! isset( $record['payload'], $record['client_id'], $record['expires_at'] ) ||
				! is_array( $record['payload'] ) ||
				! is_string( $record['client_id'] ) ||
				(int) $record['expires_at'] < $now
			) {
				unset( $registry[ $signature ] );
			}
		}

		return $registry;
	}

	/**
	 * Read and validate the registry without populating the option cache.
	 *
	 * @return array|WP_Error Registry or storage error.
	 */
	private function read_registry() {
		$raw = $this->read_raw( self::REGISTRY_OPTION );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		if ( null === $raw ) {
			return [];
		}

		$registry = maybe_unserialize( $raw );

		if ( ! is_array( $registry ) || count( $registry ) > self::MAX_OUTSTANDING ) {
			return $this->storage_error();
		}

		return $registry;
	}

	/**
	 * Persist the registry.
	 *
	 * @param array $registry Token registry.
	 *
	 * @return bool Whether the write succeeded.
	 */
	private function write_registry( array $registry ): bool {
		global $wpdb;

		if ( [] === $registry ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name = %s",
					self::REGISTRY_OPTION
				)
			);
		} else {
			$query = $wpdb->prepare(
				"INSERT INTO $wpdb->options (option_name, option_value, autoload)
				VALUES (%s, %s, %s)
				ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)",
				self::REGISTRY_OPTION,
				maybe_serialize( $registry ),
				'no'
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $query );
		}

		self::clear_option_cache( self::REGISTRY_OPTION );

		return false !== $result;
	}

	/**
	 * Acquire the fixed registry advisory lock.
	 *
	 * MySQL releases named locks when the connection ends, including after a
	 * request abort. The one-second bound lets ordinary concurrent tabs queue
	 * briefly while keeping contention fail closed.
	 *
	 * @return string Lock name, or an empty string on failure.
	 */
	private function acquire_lock(): string {
		global $wpdb;

		$lock_name = 'hcaptcha_fst_' . md5( DB_NAME . '|' . $wpdb->options );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$acquired = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, %d)',
				$lock_name,
				self::LOCK_TIMEOUT
			)
		);

		return '1' === (string) $acquired ? $lock_name : '';
	}

	/**
	 * Release the fixed registry advisory lock.
	 *
	 * @param string $lock Lock name.
	 *
	 * @return void
	 */
	private function release_lock( string $lock ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_var(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s)',
				$lock
			)
		);
	}

	/**
	 * Read an option value without populating the option cache.
	 *
	 * @param string $option_name Option name.
	 *
	 * @return string|null|WP_Error Raw value, null when absent, or a storage error.
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function read_raw( string $option_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		if ( '' !== $wpdb->last_error ) {
			return $this->storage_error();
		}

		return $value;
	}

	/**
	 * Clear option caches after a direct database writing.
	 *
	 * @param string $option_name Option name.
	 *
	 * @return void
	 * @noinspection PhpSameParameterValueInspection
	 */
	private static function clear_option_cache( string $option_name ): void {
		wp_cache_delete( $option_name, 'options' );

		$notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( is_array( $notoptions ) && isset( $notoptions[ $option_name ] ) ) {
			unset( $notoptions[ $option_name ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}

	/**
	 * Create a fail-closed storage error.
	 *
	 * @return WP_Error Storage error.
	 */
	private function storage_error(): WP_Error {
		return new WP_Error(
			'fst-storage-unavailable',
			__( 'Form timing token storage is unavailable.', 'hcaptcha-for-forms-and-more' )
		);
	}
}
