<?php
/**
 * FixDiviTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\FixDivi;

use HCaptcha\Divi\FixDivi;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test FixDivi class.
 *
 * @group divi
 */
class FixDiviTest extends HCaptchaTestCase {

	/**
	 * Test init() and init_hooks().
	 */
	public function test_init_and_init_hooks() {
		$subject = new FixDivi();
		$subject->init();

//		self::expectActionAdd

		self::assertSame(
			- PHP_INT_MAX,
			has_action( 'init', [ $subject, 'register_autoload' ] )
		);
	}

	/**
	 * Test register_autoload().
	 */
	public function est_register_autoload() {
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
	public function est_register_autoload_without_divi_theme() {
		$autoload = FunctionMocker::replace( 'spl_autoload_register' );

		$subject = new FixDivi();
		$subject->register_autoload();

		$autoload->wasNotCalled();
	}
}
