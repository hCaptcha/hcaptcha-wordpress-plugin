<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\WC\LostPassword;
use WC_Shortcode_My_Account;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * LostPasswordTest class.
 *
 * @group wc-lost-password
 * @group wc
 */
class LostPasswordTest extends WooCommerceTestCase {

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new LostPassword();

		self::assertSame(
			10,
			has_action( 'woocommerce_lostpassword_form', [ $subject, 'add_captcha' ] )
		);
	}

	/**
	 * Test add_captcha().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUnusedLocalVariableInspection
	 */
	public function test_add_captcha(): void {
		$args     = [
			'action' => 'hcaptcha_wc_lost_password',
			'name'   => 'hcaptcha_wc_lost_password_nonce',
			'id'     => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'lost_password',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new LostPassword();

		ob_start();

		wc_get_template( 'myaccount/form-lost-password.php' );

		$output = ob_get_clean();

		self::assertStringContainsString( 'woocommerce-ResetPassword', $output );
		self::assertSame( 1, substr_count( $output, $expected ) );
		self::assertLessThan( strpos( $output, 'name="wc_reset_password"' ), strpos( $output, $expected ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$validation_error           = new WP_Error( 'some error' );
		$expected                   = clone $validation_error;
		$_POST['wc_reset_password'] = 'true';

		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password' );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Native WooCommerce recovery passes its resolved account to policy.
	 *
	 * @param bool $blocked Whether the native stored address is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_native_recovery_email_policy( bool $blocked ): void {
		$email = $blocked ? 'recipient@blocked.test' : 'recipient@allowed.test';
		$user  = get_userdata( $this->factory()->user->create( [ 'user_email' => $email ] ) );
		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password' );
		$this->prepare_widget_id();
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST['wc_reset_password'] = 'true';
		$_POST['user_login']        = $user->user_login;
		$_POST['email']             = 'forged@allowed.test';
		$checked                    = [];
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'recipient@blocked.test' === $address;
			},
			10,
			2
		);
		add_filter( 'pre_wp_mail', '__return_true' );
		$native_recipient = null;
		$disable_email    = static function ( $enabled, $email_object, $notification ) use ( &$native_recipient ): bool {
			$native_recipient = $notification->get_recipient();

			// Keep native reset-key generation and recipient selection, without rendering email in the unconfigured test database.
			return false;
		};
		add_filter( 'woocommerce_email_enabled_customer_reset_password', $disable_email, 10, 3 );
		wc_clear_notices();
		new LostPassword();

		try {
			self::assertSame( ! $blocked, WC_Shortcode_My_Account::retrieve_password() );
			self::assertSame( [ $email ], $checked );
			self::assertSame( $blocked ? null : $email, $native_recipient );

			if ( $blocked ) {
				self::assertSame( 'Please use a permanent email address.', wc_get_notices( 'error' )[0]['notice'] );
				self::assertSame( '', get_userdata( $user->ID )->user_activation_key );
			} else {
				self::assertNotSame( '', get_userdata( $user->ID )->user_activation_key );
			}
		} finally {
			remove_filter( 'woocommerce_email_enabled_customer_reset_password', $disable_email );
			wc_clear_notices();
		}
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
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified_with_arbitrary_handler_value(): void {
		$validation_error           = new WP_Error( 'some error' );
		$expected                   = clone $validation_error;
		$_POST['wc_reset_password'] = '1';

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password', false );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() when widget id is bad.
	 */
	public function test_verify_bad_widget_id(): void {
		$validation_error           = new WP_Error( 'some error' );
		$expected                   = clone $validation_error;
		$_POST['wc_reset_password'] = 'true';

		$expected->add( 'bad-signature', 'Bad hCaptcha signature!' );

		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'lost_password',
			]
		);

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'CSS'
	.woocommerce-ResetPassword .h-captcha {
		margin-top: 0.5rem;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new LostPassword();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Prepare widget id.
	 *
	 * @param array $id The hCaptcha widget id.
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'woocommerce/woocommerce.php' ],
			'form_id' => 'lost_password',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
}
