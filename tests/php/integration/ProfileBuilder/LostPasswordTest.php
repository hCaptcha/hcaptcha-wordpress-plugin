<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ProfileBuilder;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\ProfileBuilder\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test the Profile Builder lost-password integration.
 *
 * @group profile-builder
 * @group profile-builder-lost-password
 */
class LostPasswordTest extends HCaptchaWPTestCase {
	/**
	 * Leave unrelated shortcodes and incomplete recovery requests unchanged.
	 */
	public function test_verify_ignores_unrelated_requests(): void {
		$subject = new LostPassword();

		self::assertSame( 'before', $subject->verify( 'before', 'other', [], [] ) );
		self::assertSame( 'before', $subject->verify( 'before', 'wppb-recover-password', [], [] ) );
		self::assertSame( 'message', $subject->recover_password_displayed_message1( 'message' ) );
	}

	/**
	 * Request method before the test.
	 *
	 * @var string|null
	 */
	private ?string $request_method = null;

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->request_method = $_SERVER['REQUEST_METHOD'] ?? null;

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'profile_builder_status', 'lost_pass' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Restore the request method after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ( null === $this->request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}

		parent::tearDown();
	}

	/**
	 * Test the lost-password form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$output = ( new LostPassword() )->add_captcha( '<form><p class="form-submit">Submit</p></form>' );
		$id     = [
			'source'  => [ 'profile-builder/index.php' ],
			'form_id' => 'lost_password',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test a filled honeypot blocks password recovery.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_profile_builder_lost_password_nonce',
			'hcaptcha_profile_builder_lost_password'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['action']                       = 'recover_password';
		$_POST['username_email']               = 'person@example.com';

		$subject = new LostPassword();

		self::assertFalse( $subject->verify( false, 'wppb-recover-password', [], [] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		self::assertSame( '', $_POST['username_email'] );
		self::assertSame(
			'<p class="wppb-warning">Anti-spam check failed.</p>',
			$subject->recover_password_displayed_message1( 'message' )
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		self::assertSame( 'person@example.com', $_POST['username_email'] );
	}

	/**
	 * A valid recovery proof leaves the native shortcode output unchanged.
	 */
	public function test_verify_accepts_valid_recovery_proof(): void {
		$this->prepare_verify_post( 'hcaptcha_profile_builder_lost_password_nonce', 'hcaptcha_profile_builder_lost_password' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['action']                       = 'recover_password';

		self::assertSame( 'before', ( new LostPassword() )->verify( 'before', 'wppb-recover-password', [], [] ) );
	}

	/**
	 * A failed recovery without a login name still stops native processing.
	 */
	public function test_verify_rejects_failed_recovery_without_username(): void {
		$_POST['action'] = 'recover_password';

		$subject = new LostPassword();
		$subject->verify( false, 'wppb-recover-password', [], [] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		self::assertSame( '', $_POST['username_email'] );
	}

	/**
	 * The native final reset validation must reject a missing hCaptcha proof.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_final_reset_rejects_missing_captcha(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'action2' => 'recover_password2' ];
		$user                      = $this->factory()->user->create_and_get();
		new LostPassword();

		$message = apply_filters( 'wppb_recover_password_extra_validation', '', $user );

		self::assertSame( hcap_get_error_messages()['bad-signature'], $message );
	}

	/**
	 * A solved reset-stage widget still permits native password validation.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_final_reset_accepts_valid_captcha(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'action2' => 'recover_password2' ];
		$this->prepare_verify_post( 'hcaptcha_profile_builder_lost_password_nonce', 'hcaptcha_profile_builder_lost_password' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'lost_password',
			]
		);

		$user = $this->factory()->user->create_and_get();
		new LostPassword();

		self::assertSame( '', apply_filters( 'wppb_recover_password_extra_validation', '', $user ) );
	}

	/**
	 * A signed widget with a failed siteverify result cannot reset a password.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_final_reset_rejects_failed_captcha(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'action2' => 'recover_password2' ];
		$this->prepare_verify_post( 'hcaptcha_profile_builder_lost_password_nonce', 'hcaptcha_profile_builder_lost_password', false );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'lost_password',
			]
		);

		$user = $this->factory()->user->create_and_get();
		new LostPassword();

		self::assertSame( hcap_get_error_messages()['fail'], apply_filters( 'wppb_recover_password_extra_validation', '', $user ) );
	}

	/**
	 * A valid proof from another form cannot authorize password reset.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_final_reset_rejects_foreign_widget(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'action2' => 'recover_password2' ];
		$this->prepare_verify_post( 'hcaptcha_profile_builder_lost_password_nonce', 'hcaptcha_profile_builder_lost_password' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'register',
			]
		);

		$user = $this->factory()->user->create_and_get();
		new LostPassword();

		self::assertSame( hcap_get_error_messages()['bad-signature'], apply_filters( 'wppb_recover_password_extra_validation', '', $user ) );
	}

	/**
	 * Native key, user, password, and other validation errors take precedence.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_final_reset_preserves_native_error(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [ 'action2' => 'recover_password2' ];
		$user                      = $this->factory()->user->create_and_get();
		new LostPassword();

		self::assertSame( 'Native validation error.', apply_filters( 'wppb_recover_password_extra_validation', 'Native validation error.', $user ) );
		self::assertSame( '', apply_filters( 'wppb_recover_password_extra_validation', '', new WP_Error( 'invalid_key', 'Invalid key.' ) ) );
	}

	/**
	 * Other methods and action markers are not reset-stage submissions.
	 *
	 * @return void
	 * @noinspection MultiAssignmentUsageInspection
	 */
	public function test_final_reset_ignores_other_requests(): void {
		$user = $this->factory()->user->create_and_get();
		new LostPassword();

		foreach (
			[
				[ 'GET', [ 'action2' => 'recover_password2' ] ],
				[ 'POST', [ 'action' => 'recover_password' ] ],
				[ 'POST', [ 'action2' => [ 'recover_password2' ] ] ],
				[ 'POST', [] ],
			] as $request
		) {
			$_SERVER['REQUEST_METHOD'] = $request[0];
			$_POST                     = $request[1];
			self::assertSame( '', apply_filters( 'wppb_recover_password_extra_validation', '', $user ) );
		}
	}
}
