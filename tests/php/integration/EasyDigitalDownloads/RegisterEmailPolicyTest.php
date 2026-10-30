<?php
/**
 * RegisterEmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use HCaptcha\EasyDigitalDownloads\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use EDD\Utils\Messages;

/**
 * Exercise the native email policy through the production API.
 */
class RegisterEmailPolicyTest extends HCaptchaPluginWPTestCase {

	/**
	 * Native plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'easy-digital-downloads/easy-digital-downloads.php';

	/**
	 * Replay EDD's deferred bootstrap; registration projection does not need cart init callbacks.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'plugins_loaded' ];

	/**
	 * Replay bootstrap when WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Prepare deterministic form protection.
	 */
	public function setUp(): void {
		parent::setUp();

		Messages::clear();
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
	}

	/**
	 * Clear native error messages between projections.
	 */
	public function tearDown(): void {
		Messages::clear();

		parent::tearDown();
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
		$_POST['edd_action']     = 'user_register';
		$_POST['edd_user_email'] = $email;
		$_POST['email']          = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_register_nonce', 'hcaptcha_easy_digital_downloads_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'register',
			]
		);

		$subject = new Register();
		$result  = [];
		add_action(
			'edd_user_register',
			static function () use ( $subject, &$result ): void {
				$result = $subject->verify( [] );
			}
		);
		// The projection test omits submit, so the native handler returns before registration side effects.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		do_action( 'edd_user_register', $_POST );

		self::assertSame( $blocked ? [ 'disposable-email' => hcap_get_error_messages()['disposable-email'] ] : [], $result );
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
