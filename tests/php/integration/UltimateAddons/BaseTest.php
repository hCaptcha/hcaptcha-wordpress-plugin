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

namespace HCaptcha\Tests\Integration\UltimateAddons;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\UltimateAddons\Base;
use Mockery;

/**
 * Class BaseTest
 *
 * @group ultimate-addons
 * @group ultimate-addons-base
 */
class BaseTest extends HCaptchaWPTestCase {

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		wp_enqueue_script(
			'uael-google-recaptcha',
			'src',
			[],
			'v1.0.0',
			true
		);

		$subject = Mockery::mock( Base::class )->makePartial();

		// By default, form_shown is false -> the script should NOT be enqueued.
		$subject->enqueue_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-ultimate-addons' ) );
		self::assertTrue( wp_script_is( 'uael-google-recaptcha' ) );

		// When form_shown is true -> the script should be enqueued.
		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();
		self::assertTrue( wp_script_is( 'hcaptcha-ultimate-addons' ) );
		self::assertFalse( wp_script_is( 'uael-google-recaptcha' ) );
	}

	/**
	 * Tests add_type_module().
	 *
	 * @return void
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag      = '<script src="https://test.test/a.js">some</script>';
		$expected = '<script type="module" src="https://test.test/a.js">some</script>';
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$subject = Mockery::mock( Base::class )->makePartial();

		// The wrong handle should not modify the tag.
		self::assertSame( $tag, $subject->add_type_module( $tag, 'some-handle', '' ) );

		// Ultimate Addons handle should add type="module".
		self::assertSame( $expected, $subject->add_type_module( $tag, 'hcaptcha-ultimate-addons', '' ) );
	}
}
