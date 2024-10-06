<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EssentialAddons;

use HCaptcha\EssentialAddons\Base;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use Patchwork\Exceptions\NonNullToVoid;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class BaseTest
 *
 * @group essential-addons
 * @group essential-addons-login
 */
class BaseTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_GET['elementor-preview'] );

		parent::tearDown();
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = Mockery::mock( Base::class );

		$subject->makePartial();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'admin-essential-addons' ) );

		$_GET['elementor-preview'] = '4242';

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'admin-essential-addons' ) );
	}

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 */
	public function test_print_hcaptcha_scripts(): void {
		$subject = Mockery::mock( Base::class );

		$subject->makePartial();

		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->print_hcaptcha_scripts( true ) );

		$_GET['elementor-preview'] = '4242';

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->print_hcaptcha_scripts( true ) );
	}
}
