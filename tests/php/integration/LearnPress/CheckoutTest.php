<?php
/**
 * CheckoutTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\LearnPress;

use Exception;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\LearnPress\Checkout;
use HCaptcha\LearnPress\Register;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use LearnPress;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Checkout class.
 *
 * @group learn-press
 * @group learn-press-checkout
 */
class CheckoutTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'learnpress/learnpress.php';

	/**
	 * Hooks to replay after loading LearnPress.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'learn_press_status', 'checkout' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Checkout();

		self::assertSame( 10, has_action( 'learn-press/payment-form', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 0, has_action( 'learn-press/before-checkout', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );
		self::assertFalse( has_action( 'learn-press/validate-checkout-fields', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$subject = new Checkout();

		ob_start();
		$subject->add_hcaptcha();
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'learnpress/learnpress.php' ],
			'form_id' => 'checkout',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Checkout();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-learnpress' ) );

		ob_start();
		$subject->add_hcaptcha();
		ob_end_clean();
		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-learnpress' ) );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag      = '<script src="https://test.test/a.js">some</script>';
		$expected = '<script type="module" src="https://test.test/a.js">some</script>';
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$subject = new Checkout();

		self::assertSame( $tag, $subject->add_type_module( $tag, 'some-handle', '' ) );
		self::assertSame( $expected, $subject->add_type_module( $tag, 'hcaptcha-learnpress', '' ) );
	}

	/**
	 * Test the Register integration does not add a second hCaptcha to check out.
	 *
	 * @return void
	 */
	public function test_register_hcaptcha_is_not_added_to_checkout(): void {
		$users_can_register = get_option( 'users_can_register' );

		wp_set_current_user( 0 );
		update_option( 'users_can_register', 1 );
		add_filter( 'learn-press/checkout/enable-register', '__return_true' );

		new Register();
		new Checkout();

		$ob_level = ob_get_level();

		ob_start();

		try {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Third-party hook name.
			do_action( 'learn-press/after-checkout-form' );
			$output = (string) ob_get_clean();
		} finally {
			if ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}

			remove_filter( 'learn-press/checkout/enable-register', '__return_true' );
			update_option( 'users_can_register', $users_can_register );
		}

		self::assertSame( 1, substr_count( $output, 'class="h-captcha"' ) );
		self::assertStringNotContainsString( 'hcaptcha_learn_press_register_nonce', $output );
		self::assertStringContainsString( 'hcaptcha_learn_press_checkout_nonce', $output );
	}

	/**
	 * Test the Register integration remains active in its own form.
	 *
	 * @return void
	 */
	public function test_register_hcaptcha_is_added_to_register_form(): void {
		new Register();

		ob_start();

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Third-party hook name.
		do_action( 'learn-press/after-form-register-fields' );
		do_action( 'register_form' );

		$output = (string) ob_get_clean();

		self::assertSame( 1, substr_count( $output, 'class="h-captcha"' ) );
		self::assertStringContainsString( 'hcaptcha_learn_press_register_nonce', $output );
	}

	/**
	 * Test the one-argument validation action used by current LearnPress.
	 *
	 * @return void
	 */
	public function test_current_validation_action_does_not_call_verify(): void {
		$subject = new Checkout();

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Third-party hook name.
		do_action( 'learn-press/validate-checkout-fields', LearnPress::instance()->checkout() );

		self::assertFalse( has_action( 'learn-press/validate-checkout-fields', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test successful verification allows checkout processing to continue.
	 *
	 * @return void
	 */
	public function test_verify_allows_checkout_after_successful_hcaptcha(): void {
		$continued = false;

		$this->prepare_verify_post( 'hcaptcha_learn_press_checkout_nonce', 'hcaptcha_learn_press_checkout' );
		$this->prepare_widget_id();

		new Checkout();

		add_action(
			'learn-press/before-checkout',
			static function () use ( &$continued ) {
				$continued = true;
			},
			1
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Third-party hook name.
		do_action( 'learn-press/before-checkout' );

		self::assertTrue( $continued );
	}

	/**
	 * Test failed verification blocks LearnPress before it creates an account.
	 *
	 * @return void
	 */
	public function test_failed_verification_blocks_checkout_registration(): void {
		$email              = 'blocked-checkout-registration@example.com';
		$password           = 'A-strong-test-password-123!';
		$response           = null;
		$users_can_register = get_option( 'users_can_register' );

		$_POST['checkout-account-switch-form'] = 'register';
		$_POST['reg_email']                    = $email;
		$_POST['reg_username']                 = 'blocked-checkout-registration';
		$_POST['reg_password']                 = $password;
		$_POST['reg_password2']                = $password;

		update_option( 'users_can_register', 1 );
		$this->prepare_verify_post( 'hcaptcha_learn_press_checkout_nonce', 'hcaptcha_learn_press_checkout', false );
		$this->prepare_widget_id();

		FunctionMocker::replace(
			'learn_press_send_json',
			static function ( $data ) use ( &$response ) {
				$response = $data;
			}
		);

		new Checkout();

		try {
			LearnPress::instance()->checkout()->process_checkout();
		} finally {
			update_option( 'users_can_register', $users_can_register );
		}

		self::assertSame(
			[
				'result'  => 'fail',
				'message' => 'The hCaptcha is invalid.',
			],
			$response
		);
		self::assertFalse( email_exists( $email ) );
		self::assertFalse( is_user_logged_in() );
	}

	/**
	 * Test an empty hCaptcha response blocks checkout processing.
	 *
	 * @return void
	 */
	public function test_empty_hcaptcha_response_blocks_checkout(): void {
		$this->prepare_verify_post( 'hcaptcha_learn_press_checkout_nonce', 'hcaptcha_learn_press_checkout', false );
		$this->prepare_widget_id();

		unset( $_POST['h-captcha-response'] );

		$subject = new Checkout();

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Please complete the hCaptcha.' );

		$subject->verify();
	}

	/**
	 * Test filled honeypot blocks checkout processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_blocks_checkout(): void {
		$this->prepare_verify_post( 'hcaptcha_learn_press_checkout_nonce', 'hcaptcha_learn_press_checkout' );
		$this->prepare_widget_id();

		$_POST['hcap_hp_test'] = 'bot';

		$subject = new Checkout();

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Anti-spam check failed.' );

		$subject->verify();
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'learnpress/learnpress.php' ],
				'form_id' => 'checkout',
			]
		);
	}
}
