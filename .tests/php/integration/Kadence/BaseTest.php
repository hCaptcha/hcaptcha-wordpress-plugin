<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Kadence\Base;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;

/**
 * Test Kadence Base.
 *
 * @group kadence
 * @group kadence-base
 */
class BaseTest extends HCaptchaWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Base();

		$subject->init_hooks();

		// Check if the action is added.
		self::assertSame( 8, has_action( 'wp_print_footer_scripts', [ $subject, 'dequeue_kadence_captcha_api' ] ) );
	}

	/**
	 * Test dequeue_kadence_captcha_api() when hCaptcha is not replaced.
	 *
	 * @return void
	 */
	public function test_dequeue_kadence_captcha_api_not_replaced(): void {
		$subject = new Base();

		// Enqueue the scripts to test if they are dequeued.
		$handles = [
			'kadence-blocks-recaptcha',
			'kadence-blocks-google-recaptcha-v2',
			'kadence-blocks-google-recaptcha-v3',
			'kadence-blocks-hcaptcha',
		];

		foreach ( $handles as $handle ) {
			wp_enqueue_script( $handle, 'https://example.com/' . $handle . '.js', [], '1.0.0', true );
		}

		// Check if the scripts are enqueued.
		foreach ( $handles as $handle ) {
			self::assertTrue( wp_script_is( $handle ) );
		}

		// Ensure the scripts are not dequeued when hCaptcha is not replaced.
		$subject->dequeue_kadence_captcha_api();

		$handles = [
			'kadence-blocks-recaptcha',
			'kadence-blocks-google-recaptcha-v2',
			'kadence-blocks-google-recaptcha-v3',
			'kadence-blocks-hcaptcha',
		];

		foreach ( $handles as $handle ) {
			self::assertTrue( wp_script_is( $handle ) );
		}
	}

	/**
	 * Test dequeue_kadence_captcha_api() when hCaptcha is replaced.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_dequeue_kadence_captcha_api_replaced(): void {
		$subject = new Base();

		$this->set_protected_property( $subject, 'has_hcaptcha', true );

		// Enqueue the scripts to test if they are dequeued.
		$handles = [
			'kadence-blocks-recaptcha',
			'kadence-blocks-google-recaptcha-v2',
			'kadence-blocks-google-recaptcha-v3',
			'kadence-blocks-hcaptcha',
		];

		foreach ( $handles as $handle ) {
			wp_enqueue_script( $handle, 'https://example.com/' . $handle . '.js', [], '1.0.0', true );
		}

		// Check if the scripts are enqueued.
		foreach ( $handles as $handle ) {
			self::assertTrue( wp_script_is( $handle ) );
		}

		// Call the method to dequeue the scripts.
		$subject->dequeue_kadence_captcha_api();

		// Check if the scripts are dequeued.
		foreach ( $handles as $handle ) {
			self::assertFalse( wp_script_is( $handle ) );
		}
	}
}
