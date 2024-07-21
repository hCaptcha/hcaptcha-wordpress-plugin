<?php
/**
 * EmailOptinTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Divi;

use HCaptcha\Divi\EmailOptin;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class EmailOptinTest
 *
 * @group divi
 */
class EmailOptinTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		wp_dequeue_script( EmailOptin::HANDLE );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new EmailOptin();

		self::assertSame( 10, has_filter( 'et_pb_signup_form_field_html_submit_button', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_et_pb_submit_subscribe_form', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_et_pb_submit_subscribe_form', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$wrap              = '<p class="et_pb_newsletter_button_wrap">';
		$html              = <<<HTML
<form>
	$wrap
</form>
HTML;
		$hcap_form         = $this->get_hcap_form(
			[
				'action' => EmailOptin::ACTION,
				'name'   => EmailOptin::NONCE,
				'id'     => [
					'source'  => [ 'Divi' ],
					'form_id' => 'email_optin',
				],
			]
		);
		$expected          = str_replace( $wrap, $hcap_form . "\n" . $wrap, $html );
		$single_name_field = 'some';

		FunctionMocker::replace( 'et_core_is_fb_enabled', false );

		$subject = new EmailOptin();

		self::assertSame( $expected, $subject->add_captcha( $html, $single_name_field ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$this->prepare_hcaptcha_get_verify_message_html( EmailOptin::NONCE, EmailOptin::ACTION );

		$subject = new EmailOptin();

		$subject->verify();
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		$error_message = '<strong>hCaptcha error:</strong> The hCaptcha is invalid.';

		$et_core_die = FunctionMocker::replace( 'et_core_die' );

		$this->prepare_hcaptcha_get_verify_message_html( EmailOptin::NONCE, EmailOptin::ACTION, false );

		$subject = new EmailOptin();

		$subject->verify();

		$et_core_die->wasCalledWithOnce( [ esc_html( $error_message ) ] );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		hcaptcha()->form_shown = true;

		self::assertFalse( wp_script_is( EmailOptin::HANDLE ) );

		$subject = new EmailOptin();

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( EmailOptin::HANDLE ) );
	}

	/**
	 * Test enqueue_scripts() when form was not shown.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_when_form_was_not_shown(): void {
		self::assertFalse( wp_script_is( EmailOptin::HANDLE ) );

		$subject = new EmailOptin();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( EmailOptin::HANDLE ) );
	}
}
