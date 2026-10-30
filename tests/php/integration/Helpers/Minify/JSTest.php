<?php
/**
 * JSTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

namespace HCaptcha\Tests\Integration\Helpers\Minify;

use HCaptcha\Helpers\Minify\JS;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Class JSTest.
 *
 * @group helpers
 * @group helpers-minify
 * @group helpers-minify-js
 */
class JSTest extends HCaptchaWPTestCase {

	/**
	 * Test the scoped parent constructor with JavaScript passed as an argument.
	 *
	 * @noinspection PhpMethodParametersCountMismatchInspection
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function test_minify(): void {
		$subject = new JS( 'const answer = 42;' );

		self::assertSame( 'const answer=42', $subject->minify() );
	}

	/**
	 * Test canImportFile().
	 *
	 * @return void
	 */
	public function test_canImportFile(): void {
		$subject = Mockery::mock( JS::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Some CSS.
		self::assertFalse( $subject->canImportFile( '<script>const a = "some js";</script>' ) );

		// Some file.
		self::assertTrue( $subject->canImportFile( __FILE__ ) );
	}
}
