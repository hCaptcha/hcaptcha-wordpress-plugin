<?php
/**
 * ForgotPasswordEmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UsersWP;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\UsersWP\ForgotPassword;

/**
 * Test native UsersWP recovery recipients.
 *
 * @group users-wp
 */
class ForgotPasswordEmailPolicyTest extends HCaptchaWPTestCase {
	/**
	 * Recovery uses the native account selector, rather than an extra email alias.
	 *
	 * @param bool $blocked Whether the stored recipient is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 */
	public function test_native_recipient_policy( bool $blocked ): void {
		$email   = $blocked ? 'recipient@blocked.test' : 'recipient@allowed.test';
		$user_id = $this->factory()->user->create( [ 'user_email' => $email ] );
		$user    = get_userdata( $user_id );
		$checked = [];
		$this->prepare_verify_post( 'hcaptcha_users_wp_forgot_password_nonce', 'hcaptcha_users_wp_forgot_password' );
		hcaptcha()->settings()->set( 'users_wp_status', [ 'forgot' ] );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'userswp/userswp.php' ],
				'form_id' => 'forgot',
			]
		);
		$_POST['user_email']                   = 'forged@allowed.test';
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'recipient@blocked.test' === $address;
			},
			10,
			2
		);
		$native = [ 'native_validation' => true ];
		$result = ( new ForgotPassword() )->verify( $native, 'forgot', [ 'email' => ' ' . $user->user_login . ' ' ] );

		if ( $blocked ) {
			$this->assertWPError( $result );
			self::assertSame( '<strong>hCaptcha error:</strong> Please use a permanent email address.', $result->get_error_message() );
		} else {
			self::assertSame( $native, $result );
		}

		self::assertSame( [ $email ], $checked );
	}

	/**
	 * Native recipient policy outcomes.
	 *
	 * @return array
	 */
	public function dp_email_policy(): array {
		return [
			'blocked recipient' => [ true ],
			'allowed recipient' => [ false ],
		];
	}
}
