<?php
/**
 * PayPalGrantTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\GiveWP;

use HCaptcha\GiveWP\PayPalGrant;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test persistence and rejection of PayPal's single-use verified proof.
 *
 * @group givewp
 */
class PayPalGrantTest extends HCaptchaWPTestCase {
	/**
	 * Invalid proof cannot issue a grant and an empty token cannot redeem one.
	 */
	public function test_invalid_proof_is_not_granted(): void {
		$binding = [ 'form_id' => 42 ];

		self::assertFalse( PayPalGrant::issue( '', $binding, [ 'success' => true ] ) );
		self::assertFalse( PayPalGrant::issue( 'response', $binding, [ 'success' => false ] ) );
		self::assertFalse(
			PayPalGrant::issue(
				'non-json-proof',
				$binding,
				[
					'success' => true,
					'invalid' => INF,
				]
			)
		);
		self::assertNull( PayPalGrant::redeem( '', $binding ) );
	}

	/**
	 * The first grant schedules bounded cleanup of expired proof records.
	 */
	public function test_grant_schedules_cleanup(): void {
		$response = 'cleanup-' . wp_generate_password( 16, false );
		$binding  = [ 'form_id' => 42 ];
		wp_clear_scheduled_hook( 'hcap_givewp_paypal_cleanup' );

		try {
			self::assertTrue( PayPalGrant::issue( $response, $binding, [ 'success' => true ] ) );
			self::assertNotFalse( wp_next_scheduled( 'hcap_givewp_paypal_cleanup' ) );
			self::assertSame( [ 'success' => true ], PayPalGrant::redeem( $response, $binding ) );
		} finally {
			wp_clear_scheduled_hook( 'hcap_givewp_paypal_cleanup' );
		}
	}

	/**
	 * A malformed database payload cannot be redeemed as a verified grant.
	 */
	public function test_malformed_stored_payload_is_rejected(): void {
		$subject  = new PayPalGrant();
		$response = 'malformed-' . wp_generate_password( 16, false );
		$name     = $this->set_method_accessibility( $subject, 'option_name' )->invoke( null, $response );
		$value    = sprintf( '%010d', time() + 1200 ) . '|{invalid';
		add_option( $name, $value, '', 'no' );

		try {
			self::assertNull( PayPalGrant::redeem( $response, [ 'form_id' => 42 ] ) );
		} finally {
			delete_option( $name );
		}
	}

	/**
	 * Cleanup removes an expired grant without touching newer records.
	 */
	public function test_cleanup_removes_expired_grant(): void {
		global $wpdb;

		$subject  = new PayPalGrant();
		$response = 'expired-' . wp_generate_password( 16, false );
		$name     = $this->set_method_accessibility( $subject, 'option_name' )->invoke( null, $response );
		$value    = sprintf( '%010d', time() - 1 ) . '|{}';
		add_option( $name, $value, '', 'no' );

		try {
			PayPalGrant::cleanup();
			// Cleanup writes directly to the database, bypassing WordPress's option cache.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $name ) );
			self::assertNull( $stored );
		} finally {
			delete_option( $name );
		}
	}
}
