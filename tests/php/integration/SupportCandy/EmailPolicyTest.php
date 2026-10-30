<?php
/**
 * EmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\SupportCandy;

use HCaptcha\SupportCandy\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WPSC_DF_Customer_Email;

/**
 * Exercise the native email policy through the production API.
 */
class EmailPolicyTest extends HCaptchaWPTestCase {
	/**
	 * Native plugin entry file required for the read-only email getter.
	 *
	 * @var string
	 */
	protected static string $plugin = 'supportcandy/supportcandy.php';

	/**
	 * Prepare deterministic form protection.
	 */
	public function setUp(): void {
		parent::setUp();

		// Load the real read-only getter without the plugin's database installation lifecycle.
		require_once WP_PLUGIN_DIR . '/supportcandy/includes/custom-field-types/class-wpsc-df-customer-email.php';

		hcaptcha()->settings()->set( 'supportcandy_status', [ 'form' ] );
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
		$_POST['email']      = $email;
		$_POST['user_email'] = 'safe@example.com';
		self::assertSame( $email, WPSC_DF_Customer_Email::get_tff_value( 'email' ) );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function (): void {};
			}
		);
		$this->prepare_verify_post( 'hcaptcha_support_candy_new_topic_nonce', 'hcaptcha_support_candy_new_topic' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'supportcandy_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'supportcandy/supportcandy.php' ],
				'form_id' => 'form',
			]
		);

		ob_start();
		( new Form() )->verify();
		$output = (string) ob_get_clean();

		self::assertSame(
			$blocked ? wp_json_encode(
				[
					'success' => false,
					'data'    => hcap_get_error_messages()['disposable-email'],
				]
			) : '',
			$output
		);
	}

	/**
	 * Native address controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable email'  => [ 'user@disposable.example', true ],
			'permanent email'   => [ 'safe@example.com', false ],
			'email-free data'   => [ '', false ],
			'quoted disposable' => [ '"local@part"@disposable.example', true ],
			'quoted subdomain'  => [ '"local@part"@sub.disposable.example', true ],
			'quoted permanent'  => [ '"local@disposable.example"@example.com', false ],
		];
	}
}
