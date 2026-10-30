<?php
/**
 * CheckoutNoticeTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use EDD\Utils\Messages;
use HCaptcha\EasyDigitalDownloads\Checkout;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Verify that native EDD checkout displays a CAPTCHA rejection.
 *
 * @group easy-digital-downloads
 * @group easy-digital-downloads-checkout
 */
class CheckoutNoticeTest extends HCaptchaPluginWPTestCase {

	/**
	 * Native plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'easy-digital-downloads/easy-digital-downloads.php';

	/**
	 * Replay EDD's deferred bootstrap after test setup.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'plugins_loaded' ];

	/**
	 * Restore plugin callbacks after WordPress resets test hooks.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Request state before each test.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Prepare isolated EDD messages and request fields.
	 */
	public function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.NonceVerification -- Preserve test request globals.
		$this->request = [ $_GET, $_POST, $_REQUEST ];
		$_GET          = [];
		$_POST         = [];
		$_REQUEST      = [];
		Messages::clear();
	}

	/**
	 * Restore native messages and request fields.
	 */
	public function tearDown(): void {
		Messages::clear();
		[ $_GET, $_POST, $_REQUEST ] = $this->request;

		parent::tearDown();
	}

	/**
	 * A CAPTCHA-only rejection must be visible in EDD's checkout form.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_captcha_rejection_is_visible(): void {
		$_POST['action']    = 'edd_process_checkout';
		$_POST['edd_email'] = 'buyer@example.com';

		$errors = ( new Checkout() )->verify( [] );

		self::assertSame( [ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ], $errors );
		self::assertSame( $errors, Messages::get_by_type( 'error' ) );

		ob_start();
		edd_print_errors();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'edd-alert-error', $html );
		self::assertStringContainsString( 'edd_error_bad-signature', $html );
		self::assertSame( [], Messages::get_by_type( 'error' ) );
	}

	/**
	 * Other EDD requests must not create a checkout CAPTCHA notice.
	 */
	public function test_unrelated_request_has_no_checkout_notice(): void {
		$_POST['action'] = 'edd_apply_discount';

		self::assertSame( [], ( new Checkout() )->verify( [] ) );
		self::assertSame( [], Messages::get_by_type( 'error' ) );
	}
}
