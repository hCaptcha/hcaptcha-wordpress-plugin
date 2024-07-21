<?php
/**
 * FixTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Divi;

use HCaptcha\Divi\Fix;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test Fix class.
 *
 * @group divi
 */
class FixTest extends HCaptchaTestCase {

	/**
	 * Test init().
	 */
	public function test_init(): void {
		$mock = Mockery::mock( Fix::class )->makePartial();
		$mock->shouldReceive( 'init_hooks' )->with()->once();

		$mock->init();
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks(): void {
		$subject = new Fix();

		WP_Mock::expectActionAdded( 'init', [ $subject, 'register_autoload' ], - PHP_INT_MAX );

		$subject->init_hooks();
	}

	/**
	 * Test register_autoload().
	 */
	public function test_register_autoload(): void {
		$autoload = FunctionMocker::replace( 'spl_autoload_register' );

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'ET_BUILDER_THEME' === $constant_name;
			}
		);

		$subject = new Fix();
		$subject->register_autoload();

		$autoload->wasCalledOnce();
	}

	/**
	 * Test register_autoload() without the Divi theme.
	 */
	public function test_register_autoload_without_divi_theme(): void {
		$autoload = FunctionMocker::replace( 'spl_autoload_register' );

		$subject = new Fix();
		$subject->register_autoload();

		$autoload->wasNotCalled();
	}

	/**
	 * Test prevent_loading_of_wp_test_case().
	 */
	public function test_prevent_loading_of_wp_test_case(): void {
		$subject = new Fix();

		$codeception_wp_test_case = 'Codeception\TestCase\WPTestCase';
		self::assertFalse( class_exists( $codeception_wp_test_case, false ) );

		self::assertNull( $subject->prevent_loading_of_wp_test_case( 'SomeClass' ) );
		self::assertFalse( class_exists( $codeception_wp_test_case, false ) );

		self::assertTrue( $subject->prevent_loading_of_wp_test_case( $codeception_wp_test_case ) );
		self::assertTrue( class_exists( $codeception_wp_test_case, false ) );
	}
}
