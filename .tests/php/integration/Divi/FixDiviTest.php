<?php
/**
 * FixDiviTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\FixDivi;

use HCaptcha\Divi\FixDivi;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test FixDivi class.
 *
 * @group bp2
 */
class FixDiviTest extends HCaptchaWPTestCase {

	/**
	 * Test init() and init_hooks().
	 */
	public function test_init_and_init_hooks() {
		$subject = new FixDivi();
		$subject->init();

		self::assertSame(
			- PHP_INT_MAX,
			has_action( 'init', [ $subject, 'register_autoload' ] )
		);
	}

	/**
	 * Test register_autoload().
	 */
	public function test_register_autoload() {
		$autoload = FunctionMocker::replace( 'spl_autoload_register' );

		FunctionMocker::replace(
			'defined',
			function ( $constant_name ) {
				return 'ET_BUILDER_THEME' === $constant_name;
			}
		);

		$subject = new FixDivi();
		$subject->register_autoload();

		$autoload->wasCalledOnce();
	}

	/**
	 * Test register_autoload() without divi theme.
	 */
	public function test_register_autoload_without_divi_theme() {
		$autoload = FunctionMocker::replace( 'spl_autoload_register' );

		$subject = new FixDivi();
		$subject->register_autoload();

		$autoload->wasNotCalled();
	}
}
