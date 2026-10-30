<?php
/**
 * LostPasswordEmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\SimpleMembership;

use HCaptcha\SimpleMembership\LostPassword;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Exercise the native email policy through the production API.
 */
class LostPasswordEmailPolicyTest extends HCaptchaWPTestCase {

	/**
	 * Prepare deterministic form protection.
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'simple_membership_status', [ 'lost_pass' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
	}

	/**
	 * Reject only the authoritative native address when disposable.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native address.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		$_POST['swpm_reset_email'] = $email;
		$_POST['email']            = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_simple_membership_register_nonce', 'hcaptcha_simple_membership_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'simple_membership_status', [ 'lost_pass' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'simple-membership/simple-wp-membership.php' ],
				'form_id' => 'lost_password',
			]
		);

		$result = ( new LostPassword() )->verify();

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : '', $result );
	}

	/**
	 * Native address controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable email' => [ 'user@disposable.example', true ],
			'permanent email'  => [ 'safe@example.com', false ],
			'email-free data'  => [ '', false ],
		];
	}
}
