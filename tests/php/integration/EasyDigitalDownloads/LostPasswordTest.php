<?php
/**
 * LostPasswordTest class file.
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
use HCaptcha\EasyDigitalDownloads\LostPassword;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use RuntimeException;

/**
 * Exercise EDD's installed recovery dispatcher and its pre-recovery CAPTCHA gate.
 *
 * @group easy-digital-downloads
 * @group easy-digital-downloads-lost-password
 */
class LostPasswordTest extends HCaptchaPluginWPTestCase {
	/**
	 * Marker for the native pre-key recovery boundary.
	 */
	private const BOUNDARY = 'SEC039 native recovery reached.';

	/**
	 * Native plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'easy-digital-downloads/easy-digital-downloads.php';

	/**
	 * Replay EDD's deferred bootstrap after the test environment loads it.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'plugins_loaded' ];

	/**
	 * Restore the plugin callbacks after WordPress resets test hooks.
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
	 * Server globals before each test.
	 *
	 * @var array
	 */
	private array $server = [];

	/**
	 * The hCaptcha adapter installed for this test.
	 *
	 * @var LostPassword
	 */
	private LostPassword $subject;

	/**
	 * Prepare native EDD recovery and deterministic CAPTCHA settings.
	 */
	public function setUp(): void {
		parent::setUp();

		if (
			! class_exists( Messages::class ) ||
			( ! class_exists( Router::class ) && ( ! function_exists( 'edd_get_actions' ) || ! function_exists( 'edd_post_actions' ) ) )
		) {
			self::markTestSkipped( 'The native EDD recovery dispatcher is unavailable in this EDD version.' );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Preserve the test request globals.
		$this->request = [ $_GET, $_POST, $_REQUEST ];
		// phpcs:enable WordPress.Security.NonceVerification
		$this->server              = $_SERVER;
		$_GET                      = [];
		$_POST                     = [];
		$_REQUEST                  = [];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		wp_set_current_user( 0 );
		Messages::clear();
		if ( class_exists( Router::class ) ) {
			Router::reset();
		}
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'lost_pass' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		$this->subject = hcaptcha()->get( LostPassword::class ) ?: new LostPassword();

		self::assertSame( 9, has_action( 'edd_user_lost_password', [ $this->subject, 'verify' ] ) );
		self::assertSame( 10, has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
	}

	/**
	 * Restore request state and the native EDD handler after each case.
	 */
	public function tearDown(): void {
		if ( $this->request ) {
			Messages::clear();
			add_action( 'edd_user_lost_password', 'edd_handle_lost_password_request', 10, 1 );
			[ $_GET, $_POST, $_REQUEST ] = $this->request;
			$_SERVER                     = $this->server;
		}

		parent::tearDown();
	}

	/**
	 * Native POST and GET transports, including router normalization, require CAPTCHA.
	 *
	 * @dataProvider dp_missing_proof_routes
	 *
	 * @param string $transport Native router transport.
	 * @param string $action    Raw router action.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_missing_proof_stops_native_recovery( string $transport, string $action ): void {
		$native = $this->native_payload( $action );
		$this->set_route( $transport, $native );

		self::assertSame( 0, $this->dispatch_before_reset_key( $native, $transport ) );
		self::assertFalse( has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
		self::assertSame(
			[ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ],
			Messages::get_by_type( 'error' )
		);
		self::assertSame( Messages::get_by_type( 'error' ), edd_get_errors() );
	}

	/**
	 * Every native action spelling and request carrier must be covered.
	 *
	 * @return array
	 */
	public function dp_missing_proof_routes(): array {
		return [
			'POST action'            => [ 'POST', 'user_lost_password' ],
			'GET action on POST'     => [ 'GET', 'user_lost_password' ],
			'normalized POST action' => [ 'POST', 'USER_LOST_PASSWORD' ],
		];
	}

	/**
	 * Both native router passes stay blocked when one POST carries both actions.
	 */
	public function test_dual_carrier_missing_proof_blocks_both_dispatches(): void {
		$native = $this->native_payload( 'user_lost_password' );
		// phpcs:disable WordPress.Security.NonceVerification -- Set both synthetic native request carriers.
		$_GET     = $native;
		$_POST    = $native;
		$_REQUEST = array_merge( $_GET, $_POST );
		// phpcs:enable WordPress.Security.NonceVerification

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Dispatch the synthetic native GET carrier.
		self::assertSame( 0, $this->dispatch_before_reset_key( $_GET, 'GET' ) );
		self::assertFalse( has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Dispatch the synthetic native POST carrier.
		self::assertSame( 0, $this->dispatch_before_reset_key( $_POST ) );
		self::assertFalse( has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
		self::assertSame(
			[ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ],
			Messages::get_by_type( 'error' )
		);
	}

	/**
	 * Signed proof allows the installed native recovery callback to begin.
	 *
	 * @dataProvider dp_valid_proof_routes
	 *
	 * @param string $transport Native router transport.
	 */
	public function test_valid_proof_reaches_native_recovery( string $transport ): void {
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_lostpassword_nonce', 'hcaptcha_easy_digital_downloads_lostpassword' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'lost_password',
			]
		);
		$native                                = $this->native_payload( 'user_lost_password' );
		$this->set_route( $transport, $native );

		self::assertSame( 1, $this->dispatch_before_reset_key( $native, $transport ) );
		self::assertSame( 10, has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
		self::assertSame( [], Messages::get_by_type( 'error' ) );
	}

	/**
	 * A valid proof can accompany either native action carrier.
	 *
	 * @return array
	 */
	public function dp_valid_proof_routes(): array {
		return [
			'POST action'        => [ 'POST' ],
			'GET action on POST' => [ 'GET' ],
		];
	}

	/**
	 * A signed proof from another EDD form must not unlock recovery.
	 */
	public function test_foreign_widget_identity_stops_recovery(): void {
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_lostpassword_nonce', 'hcaptcha_easy_digital_downloads_lostpassword' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'login',
			]
		);
		$native                                = $this->native_payload( 'user_lost_password' );
		$this->set_route( 'POST', $native );

		self::assertSame( 0, $this->dispatch_before_reset_key( $native ) );
		self::assertSame( [ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ], Messages::get_by_type( 'error' ) );
	}

	/**
	 * An invalid native EDD nonce retains EDD's original error path.
	 */
	public function test_invalid_native_nonce_preserves_edd_error(): void {
		$native                            = $this->native_payload( 'user_lost_password' );
		$native['edd_lost-password_nonce'] = 'invalid';
		$this->set_route( 'POST', $native );

		self::assertSame( 0, $this->dispatch_before_reset_key( $native ) );
		self::assertArrayHasKey( 'edd_lost_password', Messages::get_by_type( 'error' ) );
		self::assertArrayNotHasKey( 'bad-signature', Messages::get_by_type( 'error' ) );
		self::assertSame( 10, has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
	}

	/**
	 * A different EDD action does not trigger the recovery CAPTCHA.
	 */
	public function test_unrelated_native_action_is_unaffected(): void {
		$native = $this->native_payload( 'unrelated_action' );
		$this->set_route( 'POST', $native );

		self::assertSame( 0, $this->dispatch_before_reset_key( $native ) );
		self::assertSame( [], Messages::get_by_type( 'error' ) );
		self::assertSame( 10, has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
	}

	/**
	 * The hCaptcha callback preserves the native non-POST method gate.
	 */
	public function test_non_post_method_does_not_validate_captcha(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->subject->verify( $this->native_payload( 'user_lost_password' ) );

		self::assertSame( [], Messages::get_by_type( 'error' ) );
		self::assertSame( 10, has_action( 'edd_user_lost_password', 'edd_handle_lost_password_request' ) );
	}

	/**
	 * Construct a native EDD request without any real account name.
	 *
	 * @param string $action Raw native router action.
	 *
	 * @return array
	 */
	private function native_payload( string $action ): array {
		return [
			'edd_action'              => $action,
			'edd_lost-password_nonce' => wp_create_nonce( 'edd-lost-password-nonce' ),
			'user_login'              => '',
		];
	}

	/**
	 * Set the native carrier while retaining the prepared POST proof, if any.
	 *
	 * @param string $transport Native router transport.
	 * @param array  $native    Native request fields.
	 *
	 * @return void
	 */
	private function set_route( string $transport, array $native ): void {
		// phpcs:disable WordPress.Security.NonceVerification -- Set synthetic test request carriers.
		if ( 'GET' === $transport ) {
			$_GET                = $native;
			$_POST['user_login'] = '';
		} else {
			$_POST = array_merge( $_POST, $native );
		}
		$_REQUEST = array_merge( $_GET, $_POST );
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Dispatch through EDD and stop at WordPress's pre-key recovery hook.
	 *
	 * @param array  $native    Native dispatcher request.
	 * @param string $transport Native request transport.
	 *
	 * @return int Number of attempts to enter WordPress recovery.
	 * @throws RuntimeException When an unrelated native callback fails.
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function dispatch_before_reset_key( array $native, string $transport = 'POST' ): int {
		$attempts = 0;
		$stop     = static function () use ( &$attempts ): void {
			self::assertTrue( doing_action( 'edd_user_lost_password' ) );
			++$attempts;
			throw new RuntimeException( esc_html( self::BOUNDARY ) );
		};
		add_action( 'lostpassword_post', $stop, PHP_INT_MIN );

		try {
			if ( class_exists( Router::class ) ) {
				Router::frontend( $native );
			} elseif ( 'GET' === $transport ) {
				edd_get_actions();
			} else {
				edd_post_actions();
			}
		} catch ( RuntimeException $error ) {
			if ( self::BOUNDARY !== $error->getMessage() ) {
				throw $error;
			}
		} finally {
			remove_action( 'lostpassword_post', $stop, PHP_INT_MIN );
		}

		return $attempts;
	}
}
