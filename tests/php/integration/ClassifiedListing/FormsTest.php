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

		$result = ( new Register() )->verify( new WP_Error(), '', '', '', [] );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Anti-spam check failed.', $result->get_error_message( 'spam' ) );
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
