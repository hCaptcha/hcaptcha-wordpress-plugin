<?php
/**
 * FunctionsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

// phpcs:disable PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound

namespace HCaptcha\Tests\Unit\includes;

use HCaptcha\Tests\Unit\HCaptchaTestCase;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test functions file.
 *
 * @group functions
 */
class FunctionsTest extends HCaptchaTestCase {

	/**
	 * Setup test class.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		WP_Mock::userFunction( 'add_shortcode' )->with( 'hcaptcha', 'hcap_shortcode' )->once();

		require_once PLUGIN_PATH . '/src/php/includes/functions.php';
	}

	/**
	 * Test hcap_min_suffix().
	 *
	 * @return void
	 */
	public function test_hcap_min_suffix() {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) use ( &$script_debug ) {
				if ( 'SCRIPT_DEBUG' === $constant_name ) {
					return $script_debug;
				}

				return false;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( &$script_debug ) {
				if ( 'SCRIPT_DEBUG' === $name ) {
					return $script_debug;
				}

				return false;
			}
		);

		$script_debug = false;

		self::assertSame( '.min', hcap_min_suffix() );

		$script_debug = true;

		self::assertSame( '', hcap_min_suffix() );
	}
}
