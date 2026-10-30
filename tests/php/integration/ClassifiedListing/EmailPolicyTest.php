<?php
/**
 * EmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ClassifiedListing;

use HCaptcha\ClassifiedListing\Contact;
use HCaptcha\ClassifiedListing\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Exercise native email policy through production callbacks.
 *
 * @group classified-listing
 */
class EmailPolicyTest extends HCaptchaWPTestCase {

	/**
	 * Enable a deterministic disposable domain.
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'classified_listing_status', [ 'contact', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
	}

	/**
	 * A submitted alias cannot replace the native callback address.
	 */
	public function test_registration_checks_native_callback_email(): void {
		$this->prepare_form( 'register' );
		$_POST['rtcl-register'] = 'Register';
		$_POST['email']         = 'safe@example.com';

		// The production API verifies the test nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result = ( new Register() )->verify( new WP_Error(), 'user@disposable.example', 'guest', '', $_POST );

		self::assertSame( hcap_get_error_messages()['disposable-email'], $result->get_error_message( 'disposable-email' ) );
	}

	/**
	 * Contact validation checks the native validated data.
	 */
	public function test_contact_checks_native_data_email(): void {
		$this->prepare_form( 'contact' );
		$_POST['email'] = 'safe@example.com';
		$error          = new WP_Error();

		( new Contact() )->verify( $error, [ 'email' => 'user@disposable.example' ] );

		self::assertSame( hcap_get_error_messages()['disposable-email'], $error->get_error_message( 'disposable-email' ) );
	}

	/**
	 * Legitimate native email and an email-free submission remain accepted.
	 *
	 * @dataProvider dp_test_allowed_contact
	 *
	 * @param array $data Native data.
	 */
	public function test_allowed_contact( array $data ): void {
		$this->prepare_form( 'contact' );
		$_POST['email'] = 'forged@disposable.example';
		$error          = new WP_Error();

		( new Contact() )->verify( $error, $data );

		self::assertFalse( $error->has_errors() );
	}

	/**
	 * Native contact email controls.
	 *
	 * @return array
	 */
	public function dp_test_allowed_contact(): array {
		return [
			'permanent email' => [ [ 'email' => 'safe@example.com' ] ],
			'email-free data' => [ [] ],
		];
	}

	/**
	 * Prepare a signed form and successful remote proof.
	 *
	 * @param string $form_id Native form ID.
	 */
	private function prepare_form( string $form_id ): void {
		$this->prepare_verify_post( 'hcaptcha_classified_listing_' . $form_id . '_nonce', 'hcaptcha_classified_listing_' . $form_id );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'classified_listing_status', [ 'contact', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'classified-listing/classified-listing.php' ],
				'form_id' => $form_id,
			]
		);
	}
}
