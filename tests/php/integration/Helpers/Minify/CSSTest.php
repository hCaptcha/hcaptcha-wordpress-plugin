<?php
/**
 * CSSTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

namespace HCaptcha\Tests\Integration\Helpers\Minify;

use HCaptcha\Helpers\Minify\CSS;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Class CSSTest.
 *
 * @group helpers
 * @group helpers-minify
 * @group helpers-minify-css
 */
class CSSTest extends HCaptchaWPTestCase {

	/**
	 * Test canImportFile().
	 *
	 * @return void
	 */
	public function test_canImportFile(): void {
		$subject = Mockery::mock( CSS::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Some CSS.
		self::assertFalse( $subject->canImportFile( '.some-css { display: block; }' ) );

		// Some file.
		self::assertTrue( $subject->canImportFile( __FILE__ ) );
	}
}
