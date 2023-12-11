<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\GravityForms;

use HCaptcha\GravityForms\Base;
use HCaptcha\GravityForms\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test GravityForms.
 *
 * @group gravityforms
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init hooks.
	 *
	 * @param bool $mode_auto Auto mode.
	 *
	 * @dataProvider dp_test_constructor_and_init_hooks
	 */
	public function test_constructor_and_init_hooks( bool $mode_auto ) {
		if ( $mode_auto ) {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'form' ] ] );
		} else {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [] ] );
		}

		hcaptcha()->init_hooks();

		$subject = new Form();

		if ( $mode_auto ) {
			self::assertSame( 10, has_filter( 'gform_submit_button', [ $subject, 'add_captcha' ] ) );
		} else {
			self::assertFalse( has_filter( 'gform_submit_button', [ $subject, 'add_captcha' ] ) );
		}

		self::assertSame( 10, has_filter( 'gform_validation', [ $subject, 'verify' ] ) );
		self::assertSame( 10, has_filter( 'gform_form_validation_errors', [ $subject, 'form_validation_errors' ] ) );
		self::assertSame(
			10,
			has_filter( 'gform_form_validation_errors_markup', [ $subject, 'form_validation_errors_markup' ] )
		);
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Data provider for test_constructor_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_constructor_and_init_hooks(): array {
		return [
			'auto mode'   => [ true ],
			'manual mode' => [ false ],
		];
	}

	/**
	 * Test add_captcha().
	 *
	 * @param bool $is_admin Admin mode.
	 *
	 * @dataProvider dp_test_add_captcha
	 */
	public function test_add_captcha( bool $is_admin ) {
		$form = [
			'id' => 23,
		];

		if ( $is_admin ) {
			$expected = '';
			set_current_screen( 'edit-post' );
		} else {
			$expected = $this->get_hcap_form( Base::ACTION, Base::NONCE );
		}

		$subject = new Form();

		self::assertSame( $expected, $subject->add_captcha( '', $form ) );
	}

	/**
	 * Data provider for test_add_captcha().
	 *
	 * @return array
	 */
	public function dp_test_add_captcha(): array {
		return [
			'admin'     => [ true ],
			'not admin' => [ false ],
		];
	}
}
