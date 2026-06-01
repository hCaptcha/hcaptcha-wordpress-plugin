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

		hcaptcha()->form_shown = false;

		parent::tearDown();
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

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = Mockery::mock( Base::class );

		$subject->makePartial();

		hcaptcha()->form_shown = false;

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-essential-addons' ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-essential-addons' ) );

		$script = wp_scripts()->registered['hcaptcha-essential-addons'];

		self::assertSame( HCAPTCHA_URL . '/assets/js/hcaptcha-essential-addons.min.js', $script->src );
		self::assertSame( [ 'jquery', 'wp-hooks' ], $script->deps );
	}
}
