<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\SimpleMembership;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\SimpleMembership\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the Simple Membership password reset integration.
 *
 * @group simple-membership
 * @group simple-membership-lost-password
 */
class LostPasswordTest extends HCaptchaWPTestCase {
	/**
	 * Ignore other shortcodes and print reset form spacing.
	 */
	public function test_other_shortcode_and_inline_styles(): void {
		$subject = new LostPassword();

		self::assertSame( 'other', $subject->add_hcaptcha( 'other', 'other', [], [] ) );
		ob_start();
		$subject->print_inline_styles();
		self::assertStringContainsString( '#swpm-pw-reset-form .h-captcha', ob_get_clean() );
	}

	/**
	 * A native reset request with an empty submit marker is ignored.
	 */
	public function test_empty_link_reset_submit_is_ignored(): void {
		FunctionMocker::replace( 'filter_input', '0' );
		$subject = new LostPassword();

		$subject->verify_link_reset();

		self::assertFalse( get_transient( 'swpm-passsword-reset-error' ) );
	}

	/**
	 * A valid final reset proof leaves the native handler installed.
	 */
	public function test_valid_link_reset_proof_continues(): void {
		FunctionMocker::replace( 'filter_input', 'submit' );
		$this->prepare_verify_post( 'hcaptcha_simple_membership_register_nonce', 'hcaptcha_simple_membership_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'simple-membership/simple-wp-membership.php' ],
				'form_id' => 'lost_password',
			]
		);
		$subject                               = new LostPassword();

		$subject->verify_link_reset();

		self::assertFalse( get_transient( 'swpm-passsword-reset-error' ) );
	}


	/**
	 * Prepare the protected form.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'simple_membership_status', [ 'lost_pass' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Both the first and final reset handlers must have their verification hooks.
	 *
	 * @return void
	 */
	public function test_reset_hooks(): void {
		$subject = new LostPassword();

		self::assertSame( 9, has_action( 'wp', [ $subject, 'verify_link_reset' ] ) );
		self::assertSame( 10, has_filter( 'swpm_validate_pass_reset_form_submission', [ $subject, 'verify' ] ) );
	}

	/**
	 * The final native template receives the same protected identity as verify().
	 *
	 * @return void
	 */
	public function test_final_reset_widget_requires_proof(): void {
		$subject = new LostPassword();
		$id      = [
			'source'  => [ 'simple-membership/simple-wp-membership.php' ],
			'form_id' => 'lost_password',
		];
		$output  = $subject->add_hcaptcha(
			'<form><div class="swpm-pw-reset-submit-button"></div></form>',
			'swpm_reset_form',
			[],
			[]
		);

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );

		$this->prepare_verify_post( 'hcaptcha_simple_membership_register_nonce', 'hcaptcha_simple_membership_register', null );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]              = HCaptcha::widget_id_value( $id );
		$_POST['hcaptcha_simple_membership_register_nonce'] = wp_create_nonce( 'hcaptcha_simple_membership_register' );

		self::assertSame( 'Please complete the hCaptcha.', $subject->verify() );
	}

	/**
	 * A valid answer preserves the native final-reset continuation.
	 *
	 * @return void
	 */
	public function test_final_reset_valid_answer(): void {
		$subject = new LostPassword();
		$this->prepare_verify_post( 'hcaptcha_simple_membership_register_nonce', 'hcaptcha_simple_membership_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'simple-membership/simple-wp-membership.php' ],
				'form_id' => 'lost_password',
			]
		);

		self::assertSame( '', $subject->verify() );
	}
}
