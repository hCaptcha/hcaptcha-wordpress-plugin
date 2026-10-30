<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ProfileBuilder;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\ProfileBuilder\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Profile Builder registration integration.
 *
 * @group profile-builder
 * @group profile-builder-register
 */
class RegisterTest extends HCaptchaWPTestCase {
	/**
	 * Leave other forms and empty error notices unchanged.
	 */
	public function test_ignores_other_forms_and_empty_error(): void {
		$subject = new Register();

		self::assertSame( [ 'existing' ], $subject->verify( [ 'existing' ], [], [], 'edit' ) );
		self::assertSame( 'existing', $subject->general_top_error_message( 'existing' ) );
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
		hcaptcha()->settings()->set( 'profile_builder_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the registration form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$output = ( new Register() )->add_captcha( '<form><p class="form-submit">Submit</p></form>' );
		$id     = [
			'source'  => [ 'profile-builder/index.php' ],
			'form_id' => 'register',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test a filled honeypot blocks registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_profile_builder_register_nonce', 'hcaptcha_profile_builder_register' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$subject = new Register();
		$result  = $subject->verify( [], [], [], 'register' );

		self::assertSame( [ 'Anti-spam check failed.' ], $result );
		self::assertSame( '<p class="wppb-error">Anti-spam check failed.</p>', $subject->general_top_error_message( '' ) );
	}

	/**
	 * Validate only the native default email field with its processing filter.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param array  $fields  Native form schema.
	 * @param string $email   Native input email.
	 * @param bool   $rewrite Whether the native filter changes the address.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( array $fields, string $email, bool $rewrite, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		if ( $rewrite ) {
			add_filter(
				'wppb_before_processing_email_from_forms',
				static function (): string {
					return 'user@disposable.example';
				}
			);
		}
		$_POST['email'] = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_profile_builder_register_nonce', 'hcaptcha_profile_builder_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'profile_builder_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'register',
			]
		);

		$result = ( new Register() )->verify( [], $fields, [ 'email' => $email ], 'register' );

		self::assertSame( $blocked ? [ hcap_get_error_messages()['disposable-email'] ] : [], $result );
	}

	/**
	 * Native email schema and normalization controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		$native = [
			[
				'field'       => 'Default - E-mail',
				'field-title' => 'Secret contact',
			],
		];

		return [
			'disposable native field'  => [ $native, 'user@disposable.example', false, true ],
			'permanent native field'   => [ $native, 'safe@example.com', false, false ],
			'native processing filter' => [ $native, 'safe@example.com', true, true ],
			'no native email field'    => [ [ [ 'field' => 'Input' ] ], 'user@disposable.example', false, false ],
		];
	}
}
