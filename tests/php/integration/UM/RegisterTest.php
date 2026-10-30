<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UM;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\UM\Register;

/**
 * Class RegisterTest.
 *
 * @group um-register
 * @group um
 */
class RegisterTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ultimate-member/ultimate-member.php';

	/**
	 * Hooks to replay after loading Ultimate Member.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Tear down the test.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		UM()->form()->errors = null;

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = $this->get_subject();

		self::assertSame(
			100,
			has_action( 'um_get_form_fields', [ $subject, 'add_um_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_hcaptcha_form_edit_field', [ $subject, 'display_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_submit_form_errors_hook__registration', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test registration email reaches anti-spam entry without the password.
	 *
	 * @return void
	 */
	public function test_entry_data(): void {
		$subject = $this->get_subject();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke(
			$subject,
			[
				'user_email'    => 'new@example.com',
				'first_name'    => 'Jane',
				'last_name'     => 'Doe',
				'user_password' => 'do-not-copy',
			]
		);

		self::assertSame( 'new@example.com', $entry['data']['email'] );
		self::assertSame( [ 'new@example.com' ], $entry['email_addresses'] );
		self::assertSame( 'Jane Doe', $entry['data']['name'] );
		self::assertArrayNotHasKey( 'user_password', $entry['data'] );
	}

	/**
	 * Registration checks the address Ultimate Member will store.
	 *
	 * @param array       $submitted_data Submitted form data.
	 * @param string|null $expected_email Address selected by Ultimate Member.
	 * @param bool        $blocked        Whether the selected address is disposable.
	 *
	 * @return void
	 * @dataProvider dp_registration_email_policy
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_registration_email_policy( array $submitted_data, ?string $expected_email, bool $blocked ): void {
		$this->prepare_verify_post( 'hcaptcha_um_register_nonce', 'hcaptcha_um_register' );
		hcaptcha()->settings()->set( 'ultimate_member_status', [ 'register' ] );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'blocked.test' => true ] );
		$_POST['form_id']                      = '3140';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'ultimate-member/ultimate-member.php' ],
				'form_id' => 3140,
			]
		);

		$checked = [];
		$record  = static function ( $disposable, string $address ) use ( &$checked ): bool {
			$checked[] = $address;

			return $disposable;
		};
		add_filter( 'hcap_is_disposable_email', $record, 10, 2 );

		try {
			$this->get_subject()->verify( $submitted_data, [ 'mode' => 'register' ] );

			self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : null, UM()->form()->errors['hcaptcha'] ?? null );
			self::assertSame( null === $expected_email ? [] : [ $expected_email ], $checked );
		} finally {
			remove_filter( 'hcap_is_disposable_email', $record );
		}
	}

	/**
	 * Native registration email selections.
	 *
	 * @return array
	 */
	public function dp_registration_email_policy(): array {
		return [
			'email username without user_email'           => [
				[ 'username' => 'person@blocked.test' ],
				'person@blocked.test',
				true,
			],
			'email username overrides allowed user_email' => [
				[
					'username'   => 'person@blocked.test',
					'user_email' => 'reader@example.com',
				],
				'person@blocked.test',
				true,
			],
			'allowed email username overrides blocked user_email' => [
				[
					'username'   => 'reader@example.com',
					'user_email' => 'person@blocked.test',
				],
				'reader@example.com',
				false,
			],
			'plain username uses user_email'              => [
				[
					'username'   => 'reader',
					'user_email' => 'person@blocked.test',
				],
				'person@blocked.test',
				true,
			],
			'user_login is not the email alias'           => [
				[
					'user_login' => 'person@blocked.test',
					'user_email' => 'reader@example.com',
				],
				'reader@example.com',
				false,
			],
			'extra email is not the account address'      => [
				[
					'user_login' => 'reader',
					'email'      => 'person@blocked.test',
				],
				null,
				false,
			],
		];
	}

	/**
	 * Native form field suffixes are resolved before registration verification.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_registration_email_after_native_beautify(): void {
		$submitted_data = UM()->form()->beautify(
			[
				'form_id'         => '3140',
				'username-3140'   => 'person@blocked.test',
				'user_email-3140' => 'reader@example.com',
			]
		);
		$subject        = $this->get_subject();
		$entry          = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $submitted_data );

		self::assertSame( [ 'person@blocked.test' ], $entry['email_addresses'] );
		self::assertSame( 'person@blocked.test', $entry['data']['email'] );
		self::assertArrayNotHasKey( 'username-3140', $submitted_data );
	}

	/**
	 * Get subject.
	 *
	 * @return Register
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_subject(): Register {
		$subject = new Register();

		UM()->fields()->set_mode = $subject::UM_MODE;

		return $subject;
	}
}
