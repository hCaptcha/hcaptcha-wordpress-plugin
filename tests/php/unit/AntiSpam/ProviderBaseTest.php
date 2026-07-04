<?php
/**
 * ProviderBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\AntiSpam;

use HCaptcha\AntiSpam\ProviderBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;

/**
 * Test ProviderBase class.
 *
 * @group antispam
 */
class ProviderBaseTest extends HCaptchaTestCase {

	/**
	 * Test abstract provider contract through a concrete test provider.
	 */
	public function test_provider_contract(): void {
		$subject = new class() extends ProviderBase {
			/**
			 * Has the provider been configured with a valid API key?
			 *
			 * @return bool
			 */
			public static function is_configured(): bool {
				return true;
			}

			/**
			 * Verify entry.
			 *
			 * @param array $entry Entry data.
			 *
			 * @return string|null
			 */
			public function verify( array $entry ): ?string {
				return $entry['result'] ?? null;
			}
		};

		self::assertTrue( $subject::is_configured() );
		self::assertSame( 'spam', $subject->verify( [ 'result' => 'spam' ] ) );
		self::assertNull( $subject->verify( [] ) );
	}
}
