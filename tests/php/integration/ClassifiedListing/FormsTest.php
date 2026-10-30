<?php
/**
 * FormsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ClassifiedListing;

use HCaptcha\ClassifiedListing\Contact;
use HCaptcha\ClassifiedListing\Login;
use HCaptcha\ClassifiedListing\LostPassword;
use HCaptcha\ClassifiedListing\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test the Classified Listing integration.
 *
 * @group classified-listing
 */
class FormsTest extends HCaptchaWPTestCase {
	/**
	 * Leave unrelated template parts outside the contact form buffer.
	 */
	public function test_contact_ignores_other_template_parts(): void {
		$subject = new Contact();
		$level   = ob_get_level();

		$subject->before_template_part( 'other', '', [] );
		$subject->after_template_part( 'other', '', [] );

		self::assertSame( $level, ob_get_level() );
	}

	/**
	 * A valid native registration proof preserves the existing error object.
	 */
	public function test_registration_accepts_valid_proof(): void {
		$this->prepare_verify_post( 'hcaptcha_classified_listing_register_nonce', 'hcaptcha_classified_listing_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'classified-listing/classified-listing.php' ],
				'form_id' => 'register',
			]
		);
		$errors                                = new WP_Error();

		self::assertSame( $errors, ( new Register() )->verify( $errors, 'safe@example.com', 'guest', '', [ 'rtcl-register' => 'Register' ] ) );
	}

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set(
			'classified_listing_status',
			[ 'contact', 'login', 'lost_pass', 'register' ]
		);
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that all rendered forms contain honeypot fields.
	 *
	 * @return void
	 */
	public function test_forms_contain_honeypot(): void {
		$subjects = [ new Login(), new LostPassword(), new Register() ];

		foreach ( $subjects as $subject ) {
			ob_start();
			$subject->add_captcha();
			$output = (string) ob_get_clean();

			self::assertStringContainsString( 'name="hcap_hp_test"', $output );
			self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
			self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		}

		$contact = new Contact();

		ob_start();
		$contact->before_template_part( 'listing/email-to-seller-form', '', [] );
		echo '<form><button type="submit">Submit</button></form>';
		$contact->after_template_part( 'listing/email-to-seller-form', '', [] );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertTrue( wp_script_is( 'hcaptcha-classified-listing' ) );
	}

	/**
	 * Test that a filled honeypot blocks registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_blocks_registration(): void {
		$this->prepare_verify_post(
			'hcaptcha_classified_listing_register_nonce',
			'hcaptcha_classified_listing_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'classified-listing/classified-listing.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['rtcl-register']                = 'Register';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result = ( new Register() )->verify( new WP_Error(), '', '', '', $_POST );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Anti-spam check failed.', $result->get_error_message( 'spam' ) );
	}

	/**
	 * Both native login handlers call this filter before wp_signon.
	 */
	public function test_native_login_filter_rejects_missing_hcaptcha_without_form_marker(): void {
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );
		$subject = new Login();

		self::assertSame( 10, has_filter( 'rtcl_process_login_errors', [ $subject, 'verify' ] ) );
		self::assertFalse( has_filter( 'wp_authenticate_user', [ $subject, 'verify' ] ) );

		$result = apply_filters( 'rtcl_process_login_errors', new WP_Error(), 'synthetic-user', 'synthetic-password' );

		self::assertSame( 'bad-signature', $result->get_error_code() );
	}

	/**
	 * Adaptive login protection still permits native attempts below its threshold.
	 */
	public function test_native_login_filter_preserves_below_threshold_result(): void {
		add_filter( 'hcap_login_limit_exceeded', '__return_false' );
		new Login();
		$errors = new WP_Error();

		$result = apply_filters( 'rtcl_process_login_errors', $errors, 'synthetic-user', 'synthetic-password' );

		self::assertSame( $errors, $result );
	}

	/**
	 * The localized non-AJAX submit label cannot disable account registration protection.
	 */
	public function test_registration_accepts_any_native_nonempty_submit_marker(): void {
		$post = [ 'rtcl-register' => 'S’inscrire' ];

		$result = ( new Register() )->verify( new WP_Error(), 'safe@example.com', 'synthetic-user', '', $post );

		self::assertSame( 'bad-signature', $result->get_error_code() );
	}

	/**
	 * Native account registration AJAX does not require a submit-button marker.
	 */
	public function test_native_ajax_registration_rejects_missing_hcaptcha_without_marker(): void {
		// Simulate the server-owned action around the native validation callback.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'][] = 'wp_ajax_nopriv_rtcl_registration_request';

		try {
			$result = ( new Register() )->verify( new WP_Error(), 'safe@example.com', 'synthetic-user', '', [] );
			self::assertSame( 'bad-signature', $result->get_error_code() );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			array_pop( $GLOBALS['wp_current_filter'] );
		}
	}

	/**
	 * Guest listing registration shares the filter but has no account form widget.
	 */
	public function test_guest_listing_registration_is_not_forced_through_account_widget(): void {
		$_POST['rtcl-register'] = 'Register';
		$errors                 = new WP_Error();

		$result = ( new Register() )->verify( $errors, 'safe@example.com', 'synthetic-user', '', [] );

		self::assertSame( $errors, $result );
	}

	/**
	 * Test that a filled honeypot blocks the contact form.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_blocks_contact(): void {
		$this->prepare_verify_post(
			'hcaptcha_classified_listing_contact_nonce',
			'hcaptcha_classified_listing_contact'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'classified-listing/classified-listing.php' ],
				'form_id' => 'contact',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$error = new WP_Error();

		( new Contact() )->verify( $error, [] );

		self::assertSame( 'Anti-spam check failed.', $error->get_error_message( 'spam' ) );
	}
}
