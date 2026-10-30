<?php
/**
 * RegisterLegacyDispatchTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use EDD\Actions\Router;
use EDD\Utils\Messages;
use HCaptcha\EasyDigitalDownloads\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Exercise EDD 3.6's native action functions and registration handler.
 *
 * @group easy-digital-downloads
 * @group easy-digital-downloads-register
 */
class RegisterLegacyDispatchTest extends HCaptchaPluginWPTestCase {
	/**
	 * Native plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'easy-digital-downloads/easy-digital-downloads.php';

	/**
	 * Replay EDD's deferred bootstrap.
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
	 * Request globals before each test.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Prepare the native legacy dispatcher and deterministic CAPTCHA settings.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( class_exists( Router::class ) ) {
			self::markTestSkipped( 'EDD uses the modern registration router.' );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Snapshot synthetic request globals.
		$this->request = [ $_GET, $_POST, $_REQUEST ];
		$_GET          = [];
		$_POST         = [];
		$_REQUEST      = [];
		// phpcs:enable WordPress.Security.NonceVerification
		wp_set_current_user( 0 );
		Messages::clear();
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Restore request values and native messages.
	 */
	public function tearDown(): void {
		if ( $this->request ) {
			Messages::clear();
			// phpcs:ignore WordPress.Security.NonceVerification -- Restore synthetic request globals.
			[ $_GET, $_POST, $_REQUEST ] = $this->request;
		}

		parent::tearDown();
	}

	/**
	 * The legacy GET and POST entry points enforce CAPTCHA before account insertion.
	 *
	 * @dataProvider dp_native_legacy_routes
	 *
	 * @param string $transport Native request carrier.
	 * @param string $action    Raw action normalized by EDD.
	 * @param bool   $proof     Whether to include valid CAPTCHA proof.
	 */
	public function test_native_legacy_registration_routes( string $transport, string $action, bool $proof ): void {
		$payload = $this->prepare_payload( $action, $proof );
		new Register();

		// phpcs:disable WordPress.Security.NonceVerification -- Populate one native request carrier.
		if ( 'GET' === $transport ) {
			$_GET  = $payload;
			$_POST = [];
		} else {
			$_POST = $payload;
		}
		$_REQUEST = array_merge( $_GET, $_POST );
		$before   = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification

		$sink_attempts = $this->dispatch( $transport );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Assert the adapter restores the outer request.
		self::assertSame( $before, $_POST );
		self::assertSame( $proof ? 1 : 0, $sink_attempts );
		self::assertSame(
			$proof ? [] : [ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ],
			Messages::get_by_type( 'error' )
		);
		self::assertFalse( username_exists( 'sec002-legacy-registration' ) );
	}

	/**
	 * Cover both legacy request carriers and sanitize_key action normalization.
	 *
	 * @return array
	 */
	public function dp_native_legacy_routes(): array {
		return [
			'POST without proof'            => [ 'POST', 'user_register', false ],
			'GET without proof'             => [ 'GET', 'user_register', false ],
			'normalized POST without proof' => [ 'POST', 'USER_REGISTER', false ],
			'POST with proof'               => [ 'POST', 'user_register', true ],
			'GET with proof'                => [ 'GET', 'user_register', true ],
		];
	}

	/**
	 * A valid POST proof cannot authorize the native GET registration request.
	 */
	public function test_get_registration_cannot_borrow_post_proof(): void {
		$payload = $this->prepare_payload( 'user_register', false );
		$decoy   = $this->prepare_payload( 'user_register', true );
		// phpcs:disable WordPress.Security.NonceVerification -- Populate conflicting native request carriers.
		$_GET     = $payload;
		$_POST    = $decoy;
		$_REQUEST = array_merge( $_GET, $_POST );
		$before   = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification
		new Register();

		self::assertSame( 0, $this->dispatch( 'GET' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verify the decoy transport remains untouched.
		self::assertSame( $before, $_POST );
		self::assertSame( [ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ], Messages::get_by_type( 'error' ) );
	}

	/**
	 * A native validation error still blocks insertion when CAPTCHA is valid.
	 */
	public function test_native_email_guard_remains_authoritative(): void {
		$payload                   = $this->prepare_payload( 'user_register', true );
		$payload['edd_user_email'] = 'invalid-email';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Populate the native POST carrier.
		$_POST = $payload;
		new Register();

		self::assertSame( 0, $this->dispatch( 'POST' ) );
		self::assertArrayHasKey( 'email_invalid', Messages::get_by_type( 'error' ) );
		self::assertArrayNotHasKey( 'bad-signature', Messages::get_by_type( 'error' ) );
	}

	/**
	 * Dispatch the installed legacy EDD function and stop before account creation.
	 *
	 * @param string $transport Native request carrier.
	 *
	 * @return int Number of attempted user insertions.
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function dispatch( string $transport ): int {
		$sink_attempts = 0;
		$stop          = static function ( array $args ) use ( &$sink_attempts ): array {
			++$sink_attempts;
			// Force wp_insert_user to stop before creating an account.
			$args['user_login'] = '';

			return $args;
		};
		add_filter( 'edd_insert_user_args', $stop );

		try {
			if ( 'GET' === $transport ) {
				edd_get_actions();
			} else {
				edd_post_actions();
			}
		} finally {
			remove_filter( 'edd_insert_user_args', $stop );
		}

		return $sink_attempts;
	}

	/**
	 * Build a full legacy registration payload with optional signed proof.
	 *
	 * @param string $action Raw EDD action.
	 * @param bool   $proof  Whether to include valid CAPTCHA proof.
	 *
	 * @return array
	 */
	private function prepare_payload( string $action, bool $proof ): array {
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_register_nonce', 'hcaptcha_easy_digital_downloads_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'register',
			]
		);
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Copy prepared synthetic proof into the native payload.
		$captcha = $proof ? $_POST : [];

		return array_merge(
			$captcha,
			[
				'edd_action'          => $action,
				'edd_register_submit' => 'Register',
				'edd_user_login'      => 'sec002-legacy-registration',
				'edd_user_email'      => 'sec002-legacy@example.com',
				'edd_user_pass'       => 'sec002-test-password',
				'edd_user_pass2'      => 'sec002-test-password',
			]
		);
	}
}
