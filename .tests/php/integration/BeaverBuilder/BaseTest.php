<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BeaverBuilder;

use HCaptcha\BeaverBuilder\Base;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Class BaseTest
 *
 * @group beaver-builder
 * @group beaver-builder-base
 */
class BaseTest extends HCaptchaWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = Mockery::mock( Base::class )->makePartial();

		$subject->init_hooks();

		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );
	}

	/**
	 * Test add_hcap_form().
	 *
	 * @return void
	 */
	public function test_add_hcap_form(): void {
		$button    = '<div class="fl-button-wrap some"><button class="fl-button">Submit</button></div>';
		$out       = 'some output ' . $button . ' more output';
		$args      = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [],
				'form_id' => 'login',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$hcaptcha  = '<div class="fl-input-group fl-hcaptcha">' . $hcap_form . '</div>';
		$expected  = 'some output ' . $hcaptcha . $button . ' more output';

		$subject = Mockery::mock( Base::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( $expected, $subject->add_hcap_form( $out, null ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = Mockery::mock( Base::class )->makePartial();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-beaver-builder' ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-beaver-builder' ) );
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

		// Wrong tag.
		self::assertSame( $tag, $subject->add_type_module( $tag, 'some-handle', '' ) );

		// Beaver Builder tag.
		self::assertSame( $expected, $subject->add_type_module( $tag, 'hcaptcha-beaver-builder', '' ) );
	}
}
