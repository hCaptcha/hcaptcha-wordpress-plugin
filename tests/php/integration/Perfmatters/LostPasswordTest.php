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

namespace HCaptcha\Tests\Integration\Perfmatters;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WP\LostPassword;
use Perfmatters\Config;
use Perfmatters\General;

/**
 * Test the WordPress lost password form with the real Perfmatters plugin.
 *
 * @group perfmatters
 * @group wp-lost-password
 */
class LostPasswordTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'perfmatters/perfmatters.php';

	/**
	 * Original Perfmatters options.
	 *
	 * @var array|null
	 */
	private $perfmatters_options;

	/**
	 * Original permalink structure.
	 *
	 * @var string
	 */
	private string $permalink_structure;

	/**
	 * Whether the Perfmatters test configuration was applied.
	 *
	 * @var bool
	 */
	private bool $perfmatters_configured = false;

	/**
	 * Setup test.
	 *
	 * @noinspection PhpUndefinedConstantInspection
	 */
	public function setUp(): void {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . static::$plugin ) ) {
			self::markTestSkipped( 'The Perfmatters plugin is not installed.' );
		}

		if ( PHP_VERSION_ID < 80100 ) {
			self::markTestSkipped( 'This test requires PHP 8.1 at least.' );
		}

		parent::setUp();

		if (
			! defined( 'PERFMATTERS_VERSION' ) ||
			version_compare( PERFMATTERS_VERSION, '2.6.6', '<' )
		) {
			self::markTestSkipped( 'This test requires Perfmatters 2.6.6 at least.' );
		}

		$this->perfmatters_options = Config::$options;
		$this->permalink_structure = (string) get_option( 'permalink_structure' );

		Config::$options = array_merge(
			(array) Config::$options,
			[ 'login_url' => 'auth' ]
		);

		update_option( 'permalink_structure', '/%postname%/' );

		$this->perfmatters_configured = true;
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		if ( $this->perfmatters_configured ) {
			Config::$options = $this->perfmatters_options;

			update_option( 'permalink_structure', $this->permalink_structure );
		}

		unset( $_SERVER['REQUEST_URI'], $_GET['action'] );

		parent::tearDown();
	}

	/**
	 * Test the lost password form on a login URL changed by Perfmatters.
	 */
	public function test_lost_password_form_on_custom_login_url(): void {
		self::assertIsCallable( [ General::class, 'login_url' ] );

		$login_url  = General::login_url();
		$login_path = (string) wp_parse_url( $login_url, PHP_URL_PATH );

		self::assertStringEndsWith( '/auth/', $login_path );

		$_SERVER['REQUEST_URI'] = add_query_arg( 'action', 'lostpassword', $login_path );
		$_GET['action']         = 'lostpassword';

		$args     = [
			'action' => 'hcaptcha_wp_lost_password',
			'name'   => 'hcaptcha_wp_lost_password_nonce',
			'id'     => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'lost_password',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}
}
