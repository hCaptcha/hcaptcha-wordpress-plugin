<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\GiveWP;

use Exception;
use HCaptcha\GiveWP\Form;
use HCaptcha\GiveWP\PayPalGrant;
use HCaptcha\AntiSpam\DisposableEmail;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Give\Onboarding\DefaultFormFactory;
use ReflectionFunction;
use WP_Error;

/**
 * Test Base class (via Form).
 *
 * @group givewp
 */
class BaseTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'give/give.php';

	/**
	 * Hooks to replay after loading GiveWP.
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
	 * Optional late-bootstrap notice emitted by GiveWP's lifecycle container.
	 *
	 * @var string[]
	 */
	protected static array $plugin_optional_incorrect_usage = [
		'_lw_harbor_instance_registry',
	];

	/**
	 * Tear down the test.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		unset( $_POST, $_GET, $_SERVER['REQUEST_METHOD'] );

		if ( function_exists( 'give_clear_errors' ) ) {
			give_clear_errors();
		}

		wp_dequeue_script( 'hcaptcha-give-wp' );
		wp_deregister_script( 'hcaptcha-give-wp' );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Form();

		self::assertSame(
			10,
			has_action( 'give_donation_form_user_info', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'give_pre_process_donation', [ $subject, 'verify' ] )
		);
		self::assertFalse( has_action( 'give_checkout_error_checks', [ $subject, 'verify' ] ) );
		self::assertSame(
			9,
			has_action( 'template_redirect', [ $subject, 'verify_block' ] )
		);
		self::assertSame( 0, has_action( 'wp_ajax_give_paypal_commerce_create_order', [ $subject, 'verify_paypal_order' ] ) );
		self::assertSame( 0, has_action( 'wp_ajax_nopriv_give_paypal_commerce_create_order', [ $subject, 'verify_paypal_order' ] ) );
	}

	/**
	 * A response can authorize one matching final donation only.
	 *
	 * @return void
	 */
	public function test_paypal_grant_is_bound_and_single_use(): void {
		$response = 'new002-response-' . wp_generate_password( 16, false );
		$binding  = [
			'form_id' => 42,
			'email'   => 'donor@example.com',
			'amount'  => '25.00',
		];
		$proof    = [
			'success'      => true,
			'challenge_ts' => '2026-10-11T00:00:00Z',
			'hostname'     => 'test.test',
			'credit'       => false,
			'error-codes'  => [],
		];

		try {
			self::assertTrue( PayPalGrant::issue( $response, $binding, $proof ) );
			self::assertFalse( PayPalGrant::issue( $response, $binding, $proof ) );
			self::assertNull( PayPalGrant::redeem( $response, array_merge( $binding, [ 'amount' => '50.00' ] ) ) );
			self::assertNull( PayPalGrant::redeem( $response, array_merge( $binding, [ 'fst_on' => true ] ) ) );
			self::assertSame( $proof, PayPalGrant::redeem( $response, $binding ) );
			self::assertNull( PayPalGrant::redeem( $response, $binding ) );
		} finally {
			wp_clear_scheduled_hook( 'hcap_givewp_paypal_cleanup' );
		}
	}

	/**
	 * Legacy PayPal final donations use GiveWP's give-gateway field.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_legacy_paypal_order_uses_native_gateway_field(): void {
		$_POST = [
			'give-gateway'  => 'paypal-commerce',
			'payment-mode'  => 'paypal-commerce',
			'payPalOrderId' => 'LOCAL-ORDER',
		];

		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'is_paypal_order_donation' );

		self::assertTrue( $method->invoke( $subject, false ) );

		$_POST['give-gateway'] = 'offline';

		self::assertFalse( $method->invoke( $subject, false ) );
	}

	/**
	 * Block PayPal detection uses the native gateway data structure.
	 */
	public function test_block_paypal_order_uses_native_gateway_data(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$original_request = $_REQUEST;
		$subject          = new Form();
		$method           = $this->set_method_accessibility( $subject, 'is_paypal_order_donation' );

		try {
			$_REQUEST['gatewayId']   = 'paypal-commerce';
			$_REQUEST['gatewayData'] = [ 'payPalOrderId' => 'LOCAL-ORDER' ];
			self::assertTrue( $method->invoke( $subject, true ) );

			$_REQUEST['gatewayData'] = [ 'payPalOrderId' => '' ];
			self::assertFalse( $method->invoke( $subject, true ) );
		} finally {
			$_REQUEST = $original_request;
		}
	}

	/**
	 * Block PayPal binding requires the native form and donor identity.
	 */
	public function test_block_paypal_binding_uses_native_identity(): void {
		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'get_paypal_binding' );
		$this->prepare_widget_id( 42 );
		$_POST['give_email']         = 'donor@example.com';
		$_POST['email']              = 'donor@example.com';
		$_POST['formId']             = 42;
		$_POST['gatewayId']          = 'paypal-commerce';
		$_POST['amount']             = '25.00';
		$_POST['h-captcha-response'] = 'verified-response';
		$_POST['hcap_fst_token']     = '';
		$_POST['give_payment_mode']  = 'paypal-commerce';

		$binding = $method->invoke( $subject, 42, true, true );
		self::assertIsArray( $binding );
		self::assertSame( 'donor@example.com', $binding['email'] );
		self::assertSame( '25.00', $binding['amount'] );

		$_POST['formId'] = 43;
		self::assertNull( $method->invoke( $subject, 42, true, true ) );
	}

	/**
	 * Missing form identity and nonpositive amounts cannot bind a PayPal proof.
	 */
	public function test_paypal_binding_rejects_missing_identity_and_amount(): void {
		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'get_paypal_binding' );

		self::assertNull( $method->invoke( $subject, 0, false, false ) );

		$this->prepare_widget_id( 42 );
		$_POST['give_email']         = 'donor@example.com';
		$_POST['give-amount']        = '0';
		$_POST['h-captcha-response'] = 'verified-response';
		$_POST['hcap_fst_token']     = '';
		self::assertNull( $method->invoke( $subject, 42, false, false ) );
	}

	/**
	 * A final PayPal donation consumes the previously verified proof once.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_final_paypal_donation_consumes_verified_proof(): void {
		$response = 'final-proof-' . wp_generate_password( 16, false );
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );
		$this->prepare_widget_id( 42 );
		$_POST['h-captcha-response'] = $response;
		$_POST['give-form-id']       = 42;
		$_POST['give_email']         = 'donor@example.com';
		$_POST['give-amount']        = '25.00';
		$_POST['give-gateway']       = 'paypal-commerce';
		$_POST['payPalOrderId']      = 'LOCAL-ORDER';
		$_POST['hcap_fst_token']     = '';
		$subject                     = new Form();
		$method                      = $this->set_method_accessibility( $subject, 'get_paypal_binding' );
		$binding                     = $method->invoke( $subject, 42, false, false );
		self::assertIsArray( $binding );

		try {
			self::assertTrue( PayPalGrant::issue( $response, $binding, [ 'success' => true ] ) );
			give_clear_errors();
			$subject->verify( true );
			self::assertEmpty( give_get_errors() );

			$subject->verify( true );
			self::assertArrayHasKey( 'invalid_hcaptcha', give_get_errors() );
		} finally {
			wp_clear_scheduled_hook( 'hcap_givewp_paypal_cleanup' );
		}
	}

	/**
	 * Native PayPal order creation issues a proof for the matching final donation.
	 */
	public function test_paypal_order_preflight_issues_verified_proof(): void {
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );
		$response = Request::filter_input( INPUT_POST, 'h-captcha-response' );
		$this->prepare_widget_id( 42 );
		$_POST['give-form-id']   = 42;
		$_POST['give-form-hash'] = wp_create_nonce( 'give_donation_form_nonce_42' );
		$_POST['give_email']     = 'donor@example.com';
		$_POST['give-amount']    = '25.00';
		$_POST['give-gateway']   = 'paypal-commerce';
		$_POST['payment-mode']   = 'paypal-commerce';
		$_POST['hcap_fst_token'] = '';

		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'get_paypal_binding' );

		try {
			$subject->verify_paypal_order();
			$binding = $method->invoke( $subject, 42, false, true );
			self::assertIsArray( $binding );
			self::assertIsArray( PayPalGrant::redeem( $response, $binding ) );
		} finally {
			wp_clear_scheduled_hook( 'hcap_givewp_paypal_cleanup' );
		}
	}

	/**
	 * PayPal preflight stops before verification when the request lacks a form ID.
	 */
	public function test_paypal_order_preflight_rejects_missing_form_id(): void {
		$subject = $this->prepare_paypal_preflight_request();
		unset( $_POST['give-form-id'] );

		self::assertSame( [], $this->capture_paypal_order_error( $subject ) );
	}

	/**
	 * Disabled form protection does not create a PayPal proof.
	 */
	public function test_paypal_order_preflight_skips_unprotected_form(): void {
		$subject = $this->prepare_paypal_preflight_request();
		$exempt  = static function () {
			return false;
		};
		add_filter( 'hcap_protect_form', $exempt );

		try {
			$subject->verify_paypal_order();
			self::assertNull( PayPalGrant::redeem( Request::filter_input( INPUT_POST, 'h-captcha-response' ), [] ) );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}

	/**
	 * PayPal preflight rejects incomplete donor identity and invalid hCaptcha.
	 */
	public function test_paypal_order_preflight_rejects_invalid_requests(): void {
		$subject = $this->prepare_paypal_preflight_request();
		unset( $_POST['give_email'] );
		self::assertFalse( $this->capture_paypal_order_error( $subject )['success'] );

		$subject = $this->prepare_paypal_preflight_request( false );
		self::assertFalse( $this->capture_paypal_order_error( $subject )['success'] );
	}

	/**
	 * A token already bound to another order cannot be issued again.
	 */
	public function test_paypal_order_preflight_rejects_duplicate_grant(): void {
		$subject                     = $this->prepare_paypal_preflight_request();
		$response                    = 'duplicate-grant-' . wp_generate_uuid4();
		$_POST['h-captcha-response'] = $response;

		$this->prepare_verify_request( $response );

		$_POST['hcap_fst_token'] = '';
		$method                  = $this->set_method_accessibility( $subject, 'get_paypal_binding' );
		$binding                 = $method->invoke( $subject, 42, false, true );

		self::assertIsArray( $binding );

		try {
			self::assertTrue( PayPalGrant::issue( $response, $binding, [ 'success' => true ] ) );
			self::assertSame( [], $this->capture_paypal_order_error( $subject ) );
			self::assertSame( [ 'success' => true ], PayPalGrant::redeem( $response, $binding ) );
		} finally {
			PayPalGrant::redeem( $response, $binding );
			wp_clear_scheduled_hook( 'hcap_givewp_paypal_cleanup' );
		}
	}

	/**
	 * Merged block request arrays are sanitized, and legacy form IDs remain valid.
	 */
	public function test_merged_request_array_and_legacy_form_id(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$original_request = $_REQUEST;
		$subject          = new Form();
		$request_value    = $this->set_method_accessibility( $subject, 'request_value' );
		$get_form_id      = $this->set_method_accessibility( $subject, 'get_form_id' );

		try {
			$_REQUEST['gatewayData'] = [ 'payPalOrderId' => '<b>ORDER</b>' ];
			self::assertSame( [ 'payPalOrderId' => 'ORDER' ], $request_value->invoke( $subject, 'gatewayData' ) );
			$_POST['form-id'] = '42';
			self::assertSame( 42, $get_form_id->invoke( $subject, false ) );
		} finally {
			$_REQUEST = $original_request;
		}
	}

	/**
	 * A change to the minimum submit time setting invalidates an existing grant.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_paypal_grant_rejects_fst_setting_change(): void {
		$settings = hcaptcha()->settings();
		$previous = $settings->get( 'set_min_submit_time' );
		$response = 'new002-fst-' . wp_generate_password( 16, false );
		$_POST    = [
			'give_email'         => 'donor@example.com',
			'give-amount'        => '25.00',
			'give-gateway'       => 'paypal-commerce',
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => 'signed-widget',
		];
		$subject  = new Form();
		$method   = $this->set_method_accessibility( $subject, 'get_paypal_binding' );
		$proof    = [ 'success' => true ];

		try {
			$settings->set( 'set_min_submit_time', [ '' ] );
			$before = $method->invoke( $subject, 42, false, true );
			$settings->set( 'set_min_submit_time', [ 'on' ] );
			$after = $method->invoke( $subject, 42, false, true );

			self::assertIsArray( $before );
			self::assertIsArray( $after );
			self::assertFalse( $before['fst_on'] );
			self::assertTrue( $after['fst_on'] );
			self::assertTrue( PayPalGrant::issue( $response, $before, $proof ) );
			self::assertNull( PayPalGrant::redeem( $response, $after ) );
			self::assertSame( $proof, PayPalGrant::redeem( $response, $before ) );
		} finally {
			$settings->set( 'set_min_submit_time', $previous );
			wp_clear_scheduled_hook( 'hcap_givewp_paypal_cleanup' );
		}
	}

	/**
	 * A claimed remote proof reruns local guards without consuming the same FST twice.
	 *
	 * @return void
	 */
	public function test_preverified_paypal_proof_rechecks_local_policy_with_fst_enabled(): void {
		$settings = hcaptcha()->settings();
		$previous = $settings->get( 'set_min_submit_time' );
		$block_ip = '__return_true';

		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );
		$this->prepare_widget_id( 42 );
		$_POST['h-captcha-response'] = 'new002-verified-response';
		$_POST['hcap_fst_token']     = 'already-consumed-token';
		$settings->set( 'set_min_submit_time', [ 'on' ] );
		remove_filter( 'hcap_verify_fst_token', '__return_true' );

		$entry = [
			'nonce_name'         => 'hcaptcha_give_wp_form_nonce',
			'nonce_action'       => 'hcaptcha_give_wp_form',
			'h-captcha-response' => 'new002-verified-response',
			'expected_id'        => [
				'source'  => [ 'give/give.php' ],
				'form_id' => 42,
			],
		];
		$proof = [
			'success'  => true,
			'hostname' => 'test.test',
		];

		try {
			self::assertNull( API::verify_preverified( $entry, $proof ) );
			self::assertTrue( API::get_siteverify_response()['success'] );

			add_filter( 'hcap_blacklist_ip', $block_ip );
			self::assertNotNull( API::verify_preverified( $entry, $proof ) );
		} finally {
			remove_filter( 'hcap_blacklist_ip', $block_ip );
			add_filter( 'hcap_verify_fst_token', '__return_true' );
			$settings->set( 'set_min_submit_time', $previous );
		}
	}

	/**
	 * Test donor fields reach the entry without payment fields.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_donor_entry_data(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$original_request = $_REQUEST;
		$_POST            = [
			'give-form-id' => 42,
			'give_email'   => 'donor@example.com',
			'give_first'   => 'Jane',
			'give_last'    => 'Doe',
			'card_number'  => 'do-not-copy',
		];

		$subject = new Form();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject );

		self::assertSame( 'donor@example.com', $entry['data']['email'] );
		self::assertSame( 'Jane Doe', $entry['data']['name'] );
		self::assertArrayNotHasKey( 'card_number', $entry['data'] );

		$_POST = [
			'formId'    => 42,
			'email'     => 'block@example.com',
			'firstName' => 'John',
			'lastName'  => 'Smith',
		];
		// GiveWP's Router merges the form-encoded request before this callback.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$_REQUEST = $_POST;
		try {
			$entry = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, false );

			self::assertSame( 'block@example.com', $entry['data']['email'] );
			self::assertSame( 'John Smith', $entry['data']['name'] );
			$_REQUEST['email'] = [ 'unexpected' ];
			$entry             = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, false );
			self::assertSame( 42, $entry['expected_id']['form_id'] );
		} finally {
			$_REQUEST = $original_request;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The block route checks the email that GiveWP persists, not a legacy alias.
	 *
	 * @return void
	 */
	public function test_block_email_uses_native_field_over_legacy_alias(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$original_request = $_REQUEST;
		$_POST            = [
			'formId'     => 42,
			'email'      => 'blocked@disposable.example',
			'give_email' => 'allowed@example.com',
		];

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$_REQUEST = $_POST;
		try {
			$subject   = new Form();
			$get_entry = $this->set_method_accessibility( $subject, 'get_entry' );
			$block     = $get_entry->invoke( $subject, false );
			$legacy    = $get_entry->invoke( $subject, true );

			self::assertSame( 'blocked@disposable.example', $block['data']['email'] );
			self::assertSame( 'allowed@example.com', $legacy['data']['email'] );

			$_POST['email']      = 'allowed@example.com';
			$_POST['give_email'] = 'blocked@disposable.example';
			$_REQUEST            = $_POST;
			$block               = $get_entry->invoke( $subject, false );

			self::assertSame( 'allowed@example.com', $block['data']['email'] );
		} finally {
			$_REQUEST = $original_request;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * GiveWP also accepts the canonical block email from the merged query and POST data.
	 *
	 * @return void
	 */
	public function test_block_email_follows_native_request_merge(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$original_request = $_REQUEST;

		// The block route deliberately reads the same merged request as GiveWP.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		try {
			$_GET     = [
				'givewp-route' => 'donate',
				'email'        => 'blocked@disposable.example',
			];
			$_POST    = [
				'formId'     => 42,
				'give_email' => 'allowed@example.com',
			];
			$_REQUEST = array_merge( $_GET, $_POST );

			$subject   = new Form();
			$get_entry = $this->set_method_accessibility( $subject, 'get_entry' );
			$block     = $get_entry->invoke( $subject, false );

			self::assertSame( 'blocked@disposable.example', $block['data']['email'] );

			$_POST['email'] = 'allowed@example.com';
			$_REQUEST       = array_merge( $_GET, $_POST );
			$block          = $get_entry->invoke( $subject, false );

			self::assertSame( 'allowed@example.com', $block['data']['email'] );
		} finally {
			$_REQUEST = $original_request;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Native-valid quoted local parts must still reach the disposable-domain policy.
	 *
	 * @return void
	 */
	public function test_block_email_policy_checks_native_quoted_local_part(): void {
		// The block route checks the request after native GiveWP validation.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$original_request = $_REQUEST;
		$email            = '"abc"@sec062-disposable.test';
		$_POST            = [
			'formId'     => 42,
			'email'      => $email,
			'give_email' => 'allowed@example.com',
		];
		$_REQUEST         = $_POST;

		try {
			$subject   = new Form();
			$get_entry = $this->set_method_accessibility( $subject, 'get_entry' );
			$entry     = $get_entry->invoke( $subject, false );

			self::assertNotFalse( filter_var( $email, FILTER_VALIDATE_EMAIL ) );
			self::assertFalse( is_email( $email ) );
			self::assertSame( $email, $entry['data']['email'] );
			self::assertSame( [ $email ], EntryData::get_emails( $entry ) );
			set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec062-disposable.test' => true ] );
			self::assertFalse( (bool) ( new DisposableEmail() )->verify( $entry ) );
		} finally {
			delete_transient( 'hcaptcha_disposable_email_blocklist' );
			$_REQUEST = $original_request;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The native block route selects formId even when legacy aliases identify another form.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_block_form_id_uses_native_field(): void {
		$_POST = [
			'formId'       => 42,
			'give-form-id' => 43,
			'form-id'      => 44,
		];

		$subject   = new Form();
		$get_entry = $this->set_method_accessibility( $subject, 'get_entry' );
		$block     = $get_entry->invoke( $subject, false );
		$legacy    = $get_entry->invoke( $subject, true );

		self::assertSame( 42, $block['expected_id']['form_id'] );
		self::assertSame( 43, $legacy['expected_id']['form_id'] );

		unset( $_POST['formId'] );

		$block = $get_entry->invoke( $subject, false );

		self::assertSame( 0, $block['expected_id']['form_id'] );
	}

	/**
	 * GiveWP merges GET then POST for form-encoded block donations.
	 *
	 * @return void
	 */
	public function test_block_form_id_follows_native_request_merge(): void {
		$_GET['formId']        = 42;
		$_POST['give-form-id'] = 43;

		$subject   = new Form();
		$get_entry = $this->set_method_accessibility( $subject, 'get_entry' );
		$entry     = $get_entry->invoke( $subject, false );

		self::assertSame( 42, $entry['expected_id']['form_id'] );

		$_POST['formId'] = 44;
		$entry           = $get_entry->invoke( $subject, false );

		self::assertSame( 44, $entry['expected_id']['form_id'] );

		$_POST['formId'] = '';
		$entry           = $get_entry->invoke( $subject, false );

		self::assertSame( 0, $entry['expected_id']['form_id'] );
	}

	/**
	 * Test init_hooks() with a donation-form-view route.
	 *
	 * @return void
	 */
	public function test_init_hooks_with_donation_form_view_route(): void {
		$_GET['givewp-route'] = 'donation-form-view';
		$_GET['form-id']      = '123';

		$subject = new Form();

		self::assertSame(
			0,
			has_filter( 'hcap_print_hcaptcha_scripts', '__return_true' )
		);
		self::assertSame(
			10,
			has_action( 'givewp_donation_form_enqueue_scripts', [ $subject, 'enqueue_scripts' ] )
		);
		self::assertSame(
			10,
			has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] )
		);
	}

	/**
	 * Test init_hooks() with the wrong route — early return.
	 *
	 * @return void
	 */
	public function test_init_hooks_wrong_route(): void {
		$_GET['givewp-route'] = 'some-other-route';
		$_GET['form-id']      = '123';

		$subject = new Form();

		self::assertFalse(
			has_action( 'givewp_donation_form_enqueue_scripts', [ $subject, 'enqueue_scripts' ] )
		);
	}

	/**
	 * Test init_hooks() with no form-id — early return.
	 *
	 * @return void
	 */
	public function test_init_hooks_no_form_id(): void {
		$_GET['givewp-route'] = 'donation-form-view';

		$subject = new Form();

		self::assertFalse(
			has_action( 'givewp_donation_form_enqueue_scripts', [ $subject, 'enqueue_scripts' ] )
		);
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$form_id = 42;
		$subject = new Form();

		ob_start();
		$subject->add_captcha( $form_id );
		$output = ob_get_clean();

		self::assertStringContainsString( 'h-captcha', $output );
	}

	/**
	 * Test hCaptcha in a donation form rendered by the live GiveWP shortcode.
	 *
	 * @return void
	 */
	public function test_live_donation_form(): void {
		$form_id = ( new DefaultFormFactory() )->make();
		$user_id = $this->factory()->user->create();

		update_post_meta( $form_id, '_give_form_template', 'legacy' );
		wp_set_current_user( $user_id );

		$subject  = new Form();
		$function = new ReflectionFunction( 'give_form_shortcode' );
		$html     = do_shortcode( '[give_form id="' . $form_id . '"]' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/give/' ),
			wp_normalize_path( (string) $function->getFileName() )
		);
		self::assertTrue( shortcode_exists( 'give_form' ) );
		self::assertStringContainsString( 'give-form', $html );
		self::assertStringContainsString( 'name="give-form-id"', $html );
		self::assertStringContainsString( 'name="hcaptcha_give_wp_form_nonce"', $html );
		self::assertSame( 10, has_action( 'give_donation_form_user_info', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test verify() with the correct action.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$form_id = 42;

		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );
		$this->prepare_widget_id( $form_id );

		$_POST['action']       = 'give_process_donation';
		$_POST['give-form-id'] = $form_id;

		give_clear_errors();

		$subject = new Form();

		$subject->verify( true );

		self::assertEmpty( give_get_errors() );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$form_id = 42;

		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form', false );
		$this->prepare_widget_id( $form_id );

		$_POST['action']       = 'give_process_donation';
		$_POST['give-form-id'] = $form_id;

		give_clear_errors();

		$subject = new Form();

		$subject->verify( true );

		$errors = give_get_errors();

		self::assertArrayHasKey( 'invalid_hcaptcha', $errors );
		self::assertNotEmpty( $errors['invalid_hcaptcha'] );
	}

	/**
	 * Test verify() with an empty hCaptcha response does not consume FST.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_empty_hcaptcha_response_skips_fst(): void {
		$form_id = 42;

		remove_filter( 'hcap_verify_fst_token', '__return_true' );
		add_filter(
			'hcap_verify_fst_token',
			static function () {
				return new WP_Error( 'fst-replayed-or-expired', 'Token replayed or expired.' );
			},
			9
		);

		$_POST['action']                      = 'give_process_donation';
		$_POST['give-form-id']                = $form_id;
		$_POST['h-captcha-response']          = '';
		$_POST['hcaptcha_give_wp_form_nonce'] = wp_create_nonce( 'hcaptcha_give_wp_form' );
		$_POST['hcap_hp_test']                = '';
		$_POST['hcap_hp_sig']                 = wp_create_nonce( 'hcap_hp_test' );
		$_POST['hcap_fst_token']              = 'bad-token';
		$_SERVER['HTTP_CLIENT_IP']            = '7.7.7.7';

		$this->prepare_widget_id( $form_id );

		give_clear_errors();

		$subject = new Form();

		$subject->verify( true );

		$errors        = give_get_errors();
		$error_message = $errors['invalid_hcaptcha'] ?? '';

		self::assertStringContainsString( 'Please complete the hCaptcha.', $error_message );
		self::assertStringNotContainsString( 'Token replayed or expired.', $error_message );
	}
	/**
	 * Native final donation verification does not depend on an AJAX action field.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_without_ajax_action(): void {
		$_POST['action']       = 'some_other_action';
		$_POST['give-form-id'] = 42;

		give_clear_errors();

		$subject = new Form();

		$subject->verify( true );

		self::assertArrayHasKey( 'invalid_hcaptcha', give_get_errors() );
	}

	/**
	 * Test verify_block() with a GET outside the donation route.
	 *
	 * @return void
	 */
	public function test_verify_block_not_post(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$subject = new Form();

		// An unrelated GET is not a donation request.
		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Native GiveWP donation routing accepts GET and other valid signature names.
	 *
	 * @dataProvider dp_test_donate_route_markers
	 *
	 * @param string $method       Request method.
	 * @param string $signature_id Public native signature name.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_block_ignores_non_authoritative_markers( string $method, string $signature_id ): void {
		$_SERVER['REQUEST_METHOD']         = $method;
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = $signature_id;
		$_GET['formId']                    = 42;

		add_filter( 'wp_doing_ajax', '__return_true' );
		$die_handler = static function (): callable {
			return static function (): void {};
		};
		add_filter( 'wp_die_ajax_handler', $die_handler );

		try {
			ob_start();
			( new Form() )->verify_block();
			$data = json_decode( (string) ob_get_clean(), true );
			self::assertFalse( $data['success'] ?? null );
			self::assertStringContainsString( 'hCaptcha', wp_json_encode( $data ) );
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
		}
	}

	/**
	 * Non-authoritative marker combinations on the native donate route.
	 *
	 * @return array
	 */
	public function dp_test_donate_route_markers(): array {
		return [
			'GET donate'             => [ 'GET', 'givewp-donate' ],
			'other public signature' => [ 'POST', 'givewp-donation-form-validation' ],
		];
	}

	/**
	 * Test verify_block() with the wrong route.
	 *
	 * @return void
	 */
	public function test_verify_block_wrong_route(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['givewp-route']      = 'some-other';

		$subject = new Form();

		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Test verify_block() with no route (empty string fallback).
	 *
	 * @return void
	 */
	public function test_verify_block_no_route(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		unset( $_GET['givewp-route'] );

		$subject = new Form();

		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Test verify_block() with the correct route — verified.
	 *
	 * @return void
	 */
	public function test_verify_block_verified(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';
		$_POST['h-captcha-response']       = 'some-response';
		$_POST['give-form-id']             = 42;
		$_POST['formId']                   = 42;

		$this->prepare_widget_id( 42 );
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );

		$subject = new Form();

		// Should return without sending JSON error.
		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Test verify_block() with the correct route and upgraded form id field — verified.
	 *
	 * @return void
	 */
	public function test_verify_block_verified_with_form_id(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';
		$_POST['h-captcha-response']       = 'some-response';
		$_POST['formId']                   = 42;

		$this->prepare_widget_id( 42 );
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );

		$subject = new Form();

		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * An exemption for a legacy alias cannot authorize a different native block form.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_block_rejects_exempt_legacy_alias(): void {
		$source = HCaptcha::get_class_source( Form::class );
		$exempt = static function ( $protect, $form_source, $form_id ) use ( $source ) {
			return $source === $form_source && 43 === $form_id ? false : $protect;
		};

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';
		$_POST['formId']                   = 42;
		$_POST['give-form-id']             = 43;
		$_POST['form-id']                  = 43;

		$this->prepare_widget_id( 43 );

		add_filter( 'hcap_protect_form', $exempt, 10, 3 );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {};
			}
		);

		try {
			$subject = new Form();

			ob_start();
			$subject->verify_block();
			$json = ob_get_clean();

			$data = json_decode( $json, true );

			self::assertFalse( $data['success'] );
			self::assertStringContainsString( 'hCaptcha', $json );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}

	/**
	 * An exemption for the canonical block form remains valid with an unrelated alias.
	 *
	 * @return void
	 */
	public function test_verify_block_accepts_canonical_exemption(): void {
		$source = HCaptcha::get_class_source( Form::class );
		$exempt = static function ( $protect, $form_source, $form_id ) use ( $source ) {
			return $source === $form_source && 42 === $form_id ? false : $protect;
		};

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';
		$_POST['formId']                   = 42;
		$_POST['give-form-id']             = 43;

		$this->prepare_widget_id( 42 );

		add_filter( 'hcap_protect_form', $exempt, 10, 3 );

		try {
			$subject = new Form();

			$subject->verify_block();

			self::assertTrue( true );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}
	/**
	 * Test verify_block() with no h-captcha-response (empty string fallback).
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_block_no_hcaptcha_response(): void {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';

		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form', false );
		$this->prepare_widget_id( 42 );

		$_POST['give-form-id'] = 42;
		$_POST['formId']       = 42;

		unset( $_POST['h-captcha-response'] );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Form();

		ob_start();
		$subject->verify_block();
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify_block() with the correct route — not verified.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_block_not_verified(): void {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';
		$_POST['h-captcha-response']       = 'bad-response';
		$_POST['give-form-id']             = 42;
		$_POST['formId']                   = 42;

		$this->prepare_widget_id( 42 );
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form', false );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Form();

		ob_start();
		$subject->verify_block();
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$_GET['givewp-route'] = 'donation-form-view';
		$_GET['form-id']      = '42';

		$subject = new Form();

		$subject->enqueue_scripts();

		$script = wp_scripts()->registered['hcaptcha-give-wp'] ?? null;

		self::assertNotNull( $script );
		self::assertStringContainsString( 'hcaptcha-givewp', $script->src );
		self::assertContains( 'wp-blocks', $script->deps );
		self::assertContains( 'hcaptcha', $script->deps );
		self::assertContains( 'hcaptcha-fst', $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );

		// Check localization.
		self::assertNotEmpty( $script->extra['data'] );
		self::assertStringContainsString( 'HCaptchaGiveWPObject', $script->extra['data'] );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module(): void {
		$subject = new Form();

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag    = '<script src="some.js"></script>';
		$result = $subject->add_type_module( $tag, 'some-other-handle', '' );

		self::assertSame( $tag, $result );

		$tag    = '<script src="hcaptcha-givewp.js"></script>';
		$result = $subject->add_type_module( $tag, 'hcaptcha-give-wp', '' );
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		self::assertStringContainsString( 'type="module"', $result );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( int $form_id ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'give/give.php' ],
				'form_id' => $form_id,
			]
		);
	}

	/**
	 * Prepare the native GiveWP create-order fields with a stubbed hCaptcha response.
	 *
	 * @param bool $verified Whether siteverify should accept the response.
	 *
	 * @return Form
	 */
	private function prepare_paypal_preflight_request( bool $verified = true ): Form {
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form', $verified );
		$this->prepare_widget_id( 42 );
		$_POST['give-form-id']   = 42;
		$_POST['give-form-hash'] = wp_create_nonce( 'give_donation_form_nonce_42' );
		$_POST['give_email']     = 'donor@example.com';
		$_POST['give-amount']    = '25.00';
		$_POST['give-gateway']   = 'paypal-commerce';
		$_POST['payment-mode']   = 'paypal-commerce';
		$_POST['hcap_fst_token'] = '';

		return new Form();
	}

	/**
	 * Capture a native PayPal AJAX rejection without terminating the test runner.
	 *
	 * @param Form $subject GiveWP integration.
	 *
	 * @return array Decoded JSON error, or an empty array for a bare wp_die().
	 * @noinspection ThrowRawExceptionInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function capture_paypal_order_error( Form $subject ): array {
		$initial_level = ob_get_level();
		$die_handler   = static function () {
			return static function () {
				throw new Exception( 'wp_die' );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		ob_start();
		ob_start();

		try {
			$subject->verify_paypal_order();
			self::fail( 'Expected PayPal order rejection.' );
		} catch ( Exception $exception ) {
			self::assertSame( 'wp_die', $exception->getMessage() );
		} finally {
			$output = '';
			while ( ob_get_level() > $initial_level ) {
				$output = ob_get_clean() . $output;
			}
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
		}

		return json_decode( $output, true ) ?? [];
	}
}
