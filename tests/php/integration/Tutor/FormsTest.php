<?php
/**
 * FormsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Tutor;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\Tutor\Checkout;
use HCaptcha\Tutor\Login;
use HCaptcha\Tutor\Register;
use RuntimeException;
use Tutor\Ecommerce\CheckoutController;
use WP_Error;
use WP_User;

/**
 * Test hCaptcha in Tutor LMS Lite forms.
 *
 * @group tutor
 */
class FormsTest extends HCaptchaPluginWPTestCase {
	/**
	 * Ignore requests and templates outside Tutor's protected forms.
	 */
	public function test_non_matching_forms_are_ignored(): void {
		$user     = new WP_User( 1 );
		$errors   = new WP_Error();
		$checkout = new Checkout();
		$level    = ob_get_level();

		self::assertSame( $user, ( new Login() )->verify( $user, 'password' ) );
		self::assertSame( $errors, ( new Register() )->verify( $errors, '', '' ) );
		$checkout->template_before( 'other', [] );
		$checkout->template_after( 'other', [] );
		self::assertSame( $level, ob_get_level() );
	}

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'tutor/tutor.php';

	/**
	 * Skip Tutor's database installation inside the WordPress test transaction.
	 *
	 * @var string[]
	 */
	protected static array $plugin_silent_activation = [ 'tutor/tutor.php' ];

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the Tutor LMS Lite login template.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_lite_login_form(): void {
		hcaptcha()->settings()->set( 'tutor_status', 'login' );
		new Login();

		add_filter( 'hcap_delay_api_event', '__return_true' );
		ob_start();
		tutor_load_template( 'login-form' );
		$output = (string) ob_get_clean();
		remove_filter( 'hcap_delay_api_event', '__return_true' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringContainsString( 'id="tutor-login-form"', $output );
		self::assertStringContainsString( 'name="hcaptcha_login_nonce"', $output );
		self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $output );
	}

	/**
	 * Test the Tutor LMS Lite registration templates.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_lite_registration_forms(): void {
		update_option( 'users_can_register', 1 );
		hcaptcha()->settings()->set( 'tutor_status', 'register' );
		new Register();
		add_filter( 'hcap_delay_api_event', '__return_true' );

		$templates = [
			'dashboard.registration'            => 'tutor_student_reg_form_end',
			'dashboard.instructor.registration' => 'tutor_instructor_reg_form_end',
		];

		foreach ( $templates as $template => $hook ) {
			$stop = static function () {
				throw new RuntimeException( 'Tutor registration form rendered.' );
			};

			add_action( $hook, $stop, PHP_INT_MAX );
			ob_start();

			try {
				tutor_load_template( $template );
				self::fail( 'Tutor registration form hook was not called.' );
			} catch ( RuntimeException $e ) {
				self::assertSame( 'Tutor registration form rendered.', $e->getMessage() );
			} finally {
				$output = (string) ob_get_clean();
				remove_action( $hook, $stop, PHP_INT_MAX );
			}

			self::assertStringContainsString( 'id="tutor-registration-form"', $output );
			self::assertStringContainsString( 'name="hcaptcha_tutor_register_nonce"', $output );
			self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $output );
		}

		remove_filter( 'hcap_delay_api_event', '__return_true' );
	}

	/**
	 * Test the checkout template hooks used by Tutor LMS Lite.
	 *
	 * @return void
	 */
	public function test_lite_checkout_hooks(): void {
		hcaptcha()->settings()->set( 'tutor_status', 'checkout' );
		new Checkout();

		add_filter( 'hcap_delay_api_event', '__return_true' );
		ob_start();
		do_action( 'tutor_load_template_before', 'ecommerce.checkout', [] );
		echo '<form id="tutor-checkout-form"><button type="submit">Pay</button></form>';
		do_action( 'tutor_load_template_after', 'ecommerce.checkout', [] );
		$output = (string) ob_get_clean();
		remove_filter( 'hcap_delay_api_event', '__return_true' );

		self::assertStringContainsString( 'name="hcaptcha_tutor_checkout_nonce"', $output );
		self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $output );
		self::assertStringContainsString( '<button type="submit">Pay</button>', $output );
	}

	/**
	 * Test login entry includes the username but excludes the password.
	 *
	 * @return void
	 */
	public function test_login_entry_data(): void {
		$_POST['log'] = 'student';
		$_POST['pwd'] = 'secret';

		$subject = new Login();
		$entry   = $this->set_method_accessibility( $subject, 'get_login_entry' )->invoke( $subject );

		self::assertSame( [ 'log' => 'student' ], $entry['data'] );
		self::assertSame( 'login', $entry['expected_id']['form_id'] );
	}

	/**
	 * Test registration entry includes useful anti-spam fields only.
	 *
	 * @return void
	 */
	public function test_registration_entry_data(): void {
		$_POST['first_name']            = 'Jane';
		$_POST['last_name']             = 'Doe';
		$_POST['user_login']            = 'jane';
		$_POST['email']                 = 'jane@example.com';
		$_POST['password']              = 'secret';
		$_POST['password_confirmation'] = 'secret';

		$subject = new Register();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject );

		self::assertSame(
			[
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
				'user_login' => 'jane',
				'email'      => 'jane@example.com',
				'name'       => 'Jane Doe',
			],
			$entry['data']
		);
		self::assertSame( 'register', $entry['expected_id']['form_id'] );
	}

	/**
	 * Test checkout entry includes ordinary billing fields without payment details.
	 *
	 * @return void
	 */
	public function test_checkout_entry_data(): void {
		$_POST['billing_first_name'] = 'Jane';
		$_POST['billing_last_name']  = 'Doe';
		$_POST['billing_email']      = 'jane@example.com';
		$_POST['billing_phone']      = '123456789';
		$_POST['billing_address']    = 'Some street';
		$_POST['payment_method']     = 'card';

		$subject = new Checkout();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject );

		self::assertSame(
			[
				'billing_first_name' => 'Jane',
				'billing_last_name'  => 'Doe',
				'billing_email'      => 'jane@example.com',
				'billing_phone'      => '123456789',
				'billing_address'    => 'Some street',
				'name'               => 'Jane Doe',
				'email'              => 'jane@example.com',
			],
			$entry['data']
		);
		self::assertSame( [ 'jane@example.com' ], $entry['email_addresses'] );
		self::assertSame( 'checkout', $entry['expected_id']['form_id'] );
	}

	/**
	 * Test Tutor registration sends entry data to anti-spam verification.
	 *
	 * @return void
	 */
	public function test_registration_disposable_email_is_rejected(): void {
		$settings                     = (array) get_option( 'hcaptcha_settings', [] );
		$settings['disposable_email'] = [ 'on' ];
		update_option( 'hcaptcha_settings', $settings );
		$this->prepare_verify_post( 'hcaptcha_tutor_register_nonce', 'hcaptcha_tutor_register' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'tutor-pro/tutor-pro.php', 'tutor/tutor.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['tutor_action']                 = 'tutor_register_student';
		$_POST['email']                        = 'student@example.com';
		add_filter( 'hcap_is_disposable_email', '__return_true' );

		$result = ( new Register() )->verify( new WP_Error(), 'student', 'student@example.com' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Please use a permanent email address.', $result->get_error_message() );
	}

	/**
	 * Test Tutor login rejects a widget ID from another Tutor form.
	 *
	 * @return void
	 */
	public function test_login_rejects_wrong_widget_id(): void {
		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'tutor-pro/tutor-pro.php', 'tutor/tutor.php' ],
				'form_id' => 'register',
			]
		);
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$result = ( new Login() )->login_base_verify( new WP_User( 1 ), 'secret' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Bad hCaptcha signature!', $result->get_error_message( 'bad-signature' ) );
	}

	/**
	 * Route native Tutor credentials through the login verifier.
	 */
	public function test_native_login_credentials_are_verified(): void {
		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'tutor-pro/tutor-pro.php', 'tutor/tutor.php' ],
				'form_id' => 'register',
			]
		);
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );
		apply_filters( 'tutor_login_credentials', [] );

		$result = ( new Login() )->verify( new WP_User( 1 ), 'secret' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Bad hCaptcha signature!', $result->get_error_message( 'bad-signature' ) );
	}

	/**
	 * Test Tutor registration rejects a signed widget ID from another source.
	 *
	 * @return void
	 */
	public function test_registration_rejects_wrong_widget_id(): void {
		$this->prepare_verify_post( 'hcaptcha_tutor_register_nonce', 'hcaptcha_tutor_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'register',
			]
		);

		$_POST['tutor_action'] = 'tutor_register_student';

		$result = ( new Register() )->verify( new WP_Error(), 'student', 'student@example.com' );

		self::assertSame( 'Bad hCaptcha signature!', $result->get_error_message( 'bad-signature' ) );
	}

	/**
	 * Test Tutor checkout stops when another form's widget ID is submitted.
	 *
	 * @return void
	 */
	public function test_checkout_rejects_wrong_widget_id(): void {
		$this->prepare_verify_post( 'hcaptcha_tutor_checkout_nonce', 'hcaptcha_tutor_checkout' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'tutor-pro/tutor-pro.php', 'tutor/tutor.php' ],
				'form_id' => 'register',
			]
		);

		$subject = new Checkout();
		$subject->verify();

		self::assertSame(
			[ 'Bad hCaptcha signature!' ],
			get_transient( CheckoutController::PAY_NOW_ERROR_TRANSIENT_KEY . get_current_user_id() )
		);
		self::assertFalse( has_action( 'tutor_action_tutor_pay_now' ) );
	}

	/**
	 * Partial checkout checks stored billing until a native replacement is supplied.
	 *
	 * @param bool $replace_email Whether the native request supplies an allowed email.
	 *
	 * @return void
	 * @dataProvider dp_partial_checkout
	 */
	public function test_partial_checkout_email_policy( bool $replace_email ): void {
		global $wpdb;

		$user_id = $this->factory()->user->create();
		wp_set_current_user( $user_id );
		$billing_reads       = 0;
		$native_billing_read = static function ( string $query ) use ( $wpdb, $user_id, &$billing_reads ): string {
			if ( false === strpos( $query, $wpdb->prefix . 'tutor_customers' ) || 0 !== strpos( ltrim( $query ), 'SELECT' ) ) {
				return $query;
			}

			++$billing_reads;

			// Native Tutor activation omits its tables here; keep BillingModel and QueryHelper real and supply their read result.
			return $wpdb->prepare( 'SELECT %d AS id, %d AS user_id, %s AS billing_email', 1, $user_id, 'buyer@blocked.test' );
		};
		add_filter( 'query', $native_billing_read );
		$this->prepare_verify_post( 'hcaptcha_tutor_checkout_nonce', 'hcaptcha_tutor_checkout' );
		hcaptcha()->settings()->set( 'tutor_status', [ 'checkout' ] );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'tutor-pro/tutor-pro.php', 'tutor/tutor.php' ],
				'form_id' => 'checkout',
			]
		);
		$_POST['email']                        = 'forged@allowed.test';

		if ( $replace_email ) {
			$_POST['billing_email'] = 'buyer@allowed.test';
		} else {
			unset( $_POST['billing_email'] );
		}

		$checked = [];
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'buyer@blocked.test' === $address;
			},
			10,
			2
		);
		try {
			( new Checkout() )->verify();
			self::assertSame(
				$replace_email ? false : [ 'Please use a permanent email address.' ],
				get_transient( CheckoutController::PAY_NOW_ERROR_TRANSIENT_KEY . $user_id )
			);
			self::assertSame( [ $replace_email ? 'buyer@allowed.test' : 'buyer@blocked.test' ], $checked );
			self::assertSame( $replace_email ? 0 : 1, $billing_reads );
		} finally {
			remove_filter( 'query', $native_billing_read );
			delete_transient( CheckoutController::PAY_NOW_ERROR_TRANSIENT_KEY . $user_id );
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Native partial billing updates.
	 *
	 * @return array
	 */
	public function dp_partial_checkout(): array {
		return [
			'retained billing'     => [ false ],
			'supplied replacement' => [ true ],
		];
	}
}
