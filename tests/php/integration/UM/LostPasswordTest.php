<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UM;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\UM\LostPassword;

/**
 * Class LostPasswordTest.
 *
 * @group um-lost-password
 * @group um
 */
class LostPasswordTest extends HCaptchaPluginWPTestCase {

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
			has_action( 'um_reset_password_errors_hook', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_after_password_reset_fields', [ $subject, 'um_after_password_reset_fields' ] )
		);
	}

	/**
	 * Test um_after_password_reset_fields().
	 *
	 * @return void
	 */
	public function test_um_after_password_reset_fields(): void {
		$subject = $this->get_subject();

		$expected = $subject->display_captcha( '', $subject::UM_MODE );

		ob_start();
		$subject->um_after_password_reset_fields( [] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * A recovery request cannot borrow a signed exemption from another UM form.
	 *
	 * @param string $submitted_form_id Submitted form id.
	 *
	 * @return void
	 * @dataProvider dp_foreign_form_ids
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_recovery_rejects_foreign_form_exemption( string $submitted_form_id ): void {
		$exempt = $this->exempt_form( 3140 );

		try {
			$this->prepare_verify_post( 'hcaptcha_um_password_nonce', 'hcaptcha_um_password', null );
			$_POST['form_id']                      = $submitted_form_id;
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->widget_id( 3140 );

			$this->get_subject()->verify( [], [ 'mode' => 'password' ] );

			self::assertSame( 'Bad hCaptcha signature!', UM()->form()->errors['hcaptcha'] ?? null );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}

	/**
	 * Foreign numeric form ids, including a coercible malformed one.
	 *
	 * @return array
	 */
	public function dp_foreign_form_ids(): array {
		return [
			'exact'     => [ '3140' ],
			'malformed' => [ '3140junk' ],
		];
	}

	/**
	 * A server-selected recovery exemption remains valid despite a posted form id.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_recovery_uses_its_own_exemption(): void {
		$exempt = $this->exempt_form( 'password' );

		try {
			$this->prepare_verify_post( 'hcaptcha_um_password_nonce', 'hcaptcha_um_password', null );
			$_POST['form_id']                      = '3140';
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->widget_id( 'password' );

			$this->get_subject()->verify( [], [ 'mode' => 'password' ] );

			self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}

	/**
	 * A numeric id from a custom template cannot alter recovery policy.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_recovery_ignores_custom_numeric_form_id(): void {
		$exempt = $this->exempt_form( 'password' );

		try {
			$subject = $this->get_subject();
			$subject->set_form_id( [ 'form_id' => 8181 ] );
			$markup = $subject->display_captcha( '', 'password' );
			self::assertStringContainsString( esc_attr( $this->widget_id( 'password' ) ), $markup );
			self::assertStringNotContainsString( esc_attr( $this->widget_id( 8181 ) ), $markup );

			$this->prepare_verify_post( 'hcaptcha_um_password_nonce', 'hcaptcha_um_password', null );
			$_POST['form_id']                      = '3140';
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->widget_id( 'password' );

			$subject->verify( [], [ 'mode' => 'password' ] );

			self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}

	/**
	 * The signed recovery form still requires a response when protected.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_protected_recovery_requires_response(): void {
		$this->prepare_verify_post( 'hcaptcha_um_password_nonce', 'hcaptcha_um_password', null );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->widget_id( 'password' );

		$this->get_subject()->verify( [], [ 'mode' => 'password' ] );

		self::assertTrue( UM()->form()->has_error( 'hcaptcha' ) );
	}

	/**
	 * Recovery selects the last native username field and resolves login before email.
	 *
	 * @param bool $blocked Whether the native stored recipient is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_native_recovery_email_policy( bool $blocked ): void {
		$email = $blocked ? 'recipient@blocked.test' : 'recipient@allowed.test';
		$user  = get_userdata( $this->factory()->user->create( [ 'user_email' => $email ] ) );
		$this->prepare_verify_post( 'hcaptcha_um_password_nonce', 'hcaptcha_um_password' );
		hcaptcha()->settings()->set( 'ultimate_member_status', [ 'lost_pass' ] );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'ultimate-member/ultimate-member.php' ],
				'form_id' => 'password',
			]
		);
		$checked                               = [];
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'recipient@blocked.test' === $address;
			},
			10,
			2
		);
		$subject = $this->get_subject();
		$subject->verify(
			[
				'username_b'    => 'unknown',
				'username_b-99' => ' ' . $user->user_login . ' ',
				'user_email'    => 'forged@allowed.test',
			]
		);

		self::assertSame( $blocked ? 'Please use a permanent email address.' : null, UM()->form()->errors['hcaptcha'] ?? null );
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

	/**
	 * Exempt only the specified UM form.
	 *
	 * @param int|string $form_id Form id.
	 *
	 * @return callable
	 */
	private function exempt_form( $form_id ): callable {
		$exempt = static function ( $protect, $source, $candidate ) use ( $form_id ) {
			return [ 'ultimate-member/ultimate-member.php' ] === $source && $form_id === $candidate ? false : $protect;
		};

		add_filter( 'hcap_protect_form', $exempt, 10, 3 );

		return $exempt;
	}

	/**
	 * Sign an Ultimate Member widget id.
	 *
	 * @param int|string $form_id Form id.
	 *
	 * @return string
	 */
	private function widget_id( $form_id ): string {
		return HCaptcha::widget_id_value(
			[
				'source'  => [ 'ultimate-member/ultimate-member.php' ],
				'form_id' => $form_id,
			]
		);
	}

	/**
	 * Get subject.
	 *
	 * @return LostPassword
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_subject(): LostPassword {
		$subject = new LostPassword();

		UM()->fields()->set_mode = $subject::UM_MODE;

		return $subject;
	}
}
