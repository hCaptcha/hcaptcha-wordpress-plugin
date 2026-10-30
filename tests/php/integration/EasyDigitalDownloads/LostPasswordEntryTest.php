<?php
/**
 * LostPasswordEntryTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use HCaptcha\EasyDigitalDownloads\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test entry data from EDD's selected recovery request.
 */
class LostPasswordEntryTest extends HCaptchaWPTestCase {
	/**
	 * The EDD router's data supplies the entry even if ambient POST differs.
	 *
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_native_request_data_is_used(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Preserve the test request method.
		$method                    = $_SERVER['REQUEST_METHOD'] ?? null;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'ambient_field' => 'unrelated' ];
		$actual                    = [];

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\API::verify_post',
			static function ( string $name, string $action, array $expected_id, array $entry ) use ( &$actual ): ?string {
				$actual = $entry;

				return null;
			}
		);

		try {
			( new LostPassword() )->verify(
				[
					'edd_lost-password_nonce' => wp_create_nonce( 'edd-lost-password-nonce' ),
					'user_login'              => 'alice@example.com',
					'profile_note'            => 'Please help',
					'payment_card'            => 'private-payment-data',
				]
			);
		} finally {
			if ( null === $method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $method;
			}
		}

		self::assertSame(
			[
				'data'            => [
					'user_login'   => 'alice@example.com',
					'profile_note' => 'Please help',
				],
				'email_addresses' => [],
			],
			$actual
		);
	}
}
