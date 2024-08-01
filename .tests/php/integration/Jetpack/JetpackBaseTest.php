<?php
/**
 * JetpackBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Jetpack;

use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Class JetpackBaseTest.
 *
 * @group jetpack
 */
class JetpackBaseTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks.
	 */
	public function test_init_hooks(): void {
		$subject = new JetpackForm();

		self::assertSame(
			10,
			has_filter( 'the_content', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			0,
			has_filter( 'widget_text', [ $subject, 'add_captcha' ] )
		);

		self::assertSame(
			10,
			has_filter( 'widget_text', 'shortcode_unautop' )
		);
		self::assertSame(
			10,
			has_filter( 'widget_text', 'do_shortcode' )
		);

		self::assertSame(
			100,
			has_filter( 'jetpack_contact_form_is_spam', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test jetpack_verify().
	 */
	public function test_jetpack_verify(): void {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );

		$subject = new JetpackForm();

		self::assertFalse( $subject->verify() );
		self::assertTrue( $subject->verify( true ) );
	}

	/**
	 * Test jetpack_verify() not verified.
	 */
	public function test_jetpack_verify_not_verified(): void {
		$error = new WP_Error( 'invalid_hcaptcha', 'The hCaptcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack', false );

		$subject = new JetpackForm();

		self::assertEquals( $error, $subject->verify() );
		self::assertSame( 10, has_action( 'hcap_hcaptcha_content', [ $subject, 'error_message' ] ) );
	}

	/**
	 * Test error_message().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_error_message(): void {
		$hcaptcha_content = 'some content';
		$error_message    = 'some error message';

		$subject = new JetpackForm();

		self::assertSame( $hcaptcha_content, $subject->error_message( $hcaptcha_content ) );

		$this->set_protected_property( $subject, 'error_message', $error_message );

		$expected = $hcaptcha_content . '<div class="contact-form__input-error">
	<span class="contact-form__warning-icon">
		<span class="visually-hidden">Warning.</span>
		<i aria-hidden="true"></i>
	</span>
	<span>' . $error_message . '</span>
</div>';

		self::assertSame( $expected, $subject->error_message( $hcaptcha_content ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<CSS
	form.contact-form .grunion-field-wrap .h-captcha,
	form.wp-block-jetpack-contact-form .grunion-field-wrap .h-captcha {
		margin-bottom: 0;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new JetpackForm();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
