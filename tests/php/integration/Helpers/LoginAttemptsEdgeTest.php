<?php
/**
 * LoginAttemptsEdgeTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Exercise malformed and failed-storage login attempt states against WordPress options.
 */
class LoginAttemptsEdgeTest extends HCaptchaWPTestCase {
	/**
	 * Remove any bounded records after each test.
	 */
	public function tearDown(): void {
		LoginAttempts::delete_all();

		parent::tearDown();
	}

	/**
	 * Malformed stored records and reset tokens fail closed.
	 */
	public function test_malformed_record_and_reset_token(): void {
		$subject = new LoginAttempts();
		$address = '198.51.100.95';
		$name    = $this->option_name( $subject, $address );
		$now     = time();
		update_option( $name, 'corrupt', false );

		self::assertSame( LoginAttempts::MAX_FAILURES, $subject->read( $address, $now ) );
		$subject->reset( $address, 'invalid-reset-token' );
		self::assertSame( 'corrupt', get_option( $name ) );

		$decode = $this->set_method_accessibility( $subject, 'decode' );
		self::assertNull( $decode->invoke( $subject, str_repeat( 'a', 64 ) . '|0|' . ( $now + 60 ) ) );
	}

	/**
	 * An expired reset snapshot is discarded and cannot clear later failures.
	 */
	public function test_expired_reset_snapshot_is_discarded(): void {
		$subject = new LoginAttempts();
		$address = '198.51.100.96';
		$now     = time();
		$name    = $this->option_name( $subject, $address );
		$hash    = $this->address_hash( $subject, $address );
		$encode  = $this->set_method_accessibility( $subject, 'encode' );
		update_option( $name, $encode->invoke( $subject, $hash, 2, $now - 1 ), false );

		self::assertNull( $subject->get_reset_token( $address, $now ) );
		self::assertFalse( get_option( $name ) );
	}

	/**
	 * Two failed compare-and-delete attempts keep a stale slot saturated.
	 *
	 * @noinspection SqlResolve
	 */
	public function test_expired_record_with_failed_delete_stays_saturated(): void {
		global $wpdb;

		$subject = new LoginAttempts();
		$address = '198.51.100.97';
		$now     = time();
		$name    = $this->option_name( $subject, $address );
		$hash    = $this->address_hash( $subject, $address );
		$encode  = $this->set_method_accessibility( $subject, 'encode' );
		$raw     = $encode->invoke( $subject, $hash, 2, $now - 1 );
		update_option( $name, $raw, false );
		$filter          = static function ( string $query ) use ( $name ): string {
			if ( false !== stripos( $query, 'DELETE FROM' ) && false !== strpos( $query, "'" . $name . "'" ) ) {
				return 'SELECT * FROM hcaptcha_missing_login_table';
			}

			return $query;
		};
		$suppress_errors = $wpdb->suppress_errors();
		add_filter( 'query', $filter, PHP_INT_MAX );

		try {
			self::assertSame( LoginAttempts::MAX_FAILURES, $subject->read( $address, $now ) );
		} finally {
			remove_filter( 'query', $filter, PHP_INT_MAX );
			$wpdb->suppress_errors( $suppress_errors );
		}

		self::assertSame( $raw, get_option( $name ) );
	}

	/**
	 * Calculate the production slot key for a trusted address.
	 *
	 * @param LoginAttempts $subject Login attempt store.
	 * @param string        $address Trusted address.
	 *
	 * @return string
	 */
	private function option_name( LoginAttempts $subject, string $address ): string {
		$hash = $this->address_hash( $subject, $address );

		return $this->set_method_accessibility( $subject, 'option_name' )->invoke( $subject, $hash );
	}

	/**
	 * Calculate the production address hash used in a stored record.
	 *
	 * @param LoginAttempts $subject Login attempt store.
	 * @param string        $address Trusted address.
	 *
	 * @return string
	 */
	private function address_hash( LoginAttempts $subject, string $address ): string {
		return $this->set_method_accessibility( $subject, 'address_hash' )->invoke( $subject, $address );
	}
}
