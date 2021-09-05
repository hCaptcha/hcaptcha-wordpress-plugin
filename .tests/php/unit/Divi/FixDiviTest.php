<?php
/**
 * FixDiviTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\FixDivi;

use HCaptcha\Divi\FixDivi;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test FixDivi class.
 *
 * @group divi
 */
class FixDiviTest extends HCaptchaTestCase {

	/**
	 * Test init().
	 */
	public function test_init() {
		$mock = Mockery::mock( FixDivi::class )->makePartial();
		$mock->shouldReceive( 'init_hooks' )->with()->once();

		$mock->init();
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks() {
		$subject = new FixDivi();

		WP_Mock::expectActionAdded( 'init', [ $subject, 'register_autoload' ], - PHP_INT_MAX );

		$subject->init_hooks();
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

	/**
	 * Test prevent_loading_of_wp_test_case().
	 */
	public function test_prevent_loading_of_wp_test_case() {
		$subject = new FixDivi();

		$codeception_wp_test_case = 'Codeception\TestCase\WPTestCase';
		self::assertFalse( class_exists( $codeception_wp_test_case, false ) );

		self::assertNull( $subject->prevent_loading_of_wp_test_case( 'SomeClass' ) );
		self::assertFalse( class_exists( $codeception_wp_test_case, false ) );

		self::assertTrue( $subject->prevent_loading_of_wp_test_case( $codeception_wp_test_case ) );
		self::assertTrue( class_exists( $codeception_wp_test_case, false ) );
	}
}
