<?php
/**
 * SendinblueTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Sendinblue;

use HCaptcha\Sendinblue\Sendinblue;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionMethod;

/**
 * Test Sendinblue class.
 *
 * @group sendinblue
 */
class SendinblueTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'mailin/sendinblue.php';

	/**
	 * Hooks to replay after loading Brevo.
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
	 * Configure Brevo to register its frontend form shortcode.
	 *
	 * @return void
	 */
	protected function before_load_test_plugins(): void {
		update_option( 'sib_api_key_v3', 'integration-test-key' );
		update_option( 'sib_main_option', [ 'access_key' => 'integration-test-key' ] );
		update_option(
			'sib_home_option',
			[
				'activate_email' => 'no',
				'activate_ma'    => 'no',
			]
		);
		update_option( 'sib_use_apiv2', '1' );
		set_transient(
			'sib_list_' . md5( 'integration-test-key' ),
			[
				[
					'id'   => 1,
					'name' => 'Integration List',
				],
			],
			900
		);
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Sendinblue();

		self::assertSame(
			10,
			has_filter( 'do_shortcode_tag', [ $subject, 'add_hcaptcha' ] )
		);
		self::assertSame(
			10,
			has_filter( 'hcap_verify_request', [ $subject, 'verify_request' ] )
		);
		self::assertSame(
			9,
			has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] )
		);
		self::assertSame(
			10,
			has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] )
		);
	}

	/**
	 * Test add_hcaptcha() with the wrong shortcode tag.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_wrong_tag(): void {
		$subject = new Sendinblue();
		$output  = '<form><button type="submit">Send</button></form>';

		self::assertSame( $output, $subject->add_hcaptcha( $output, 'some_shortcode', [], [] ) );
	}

	/**
	 * Test add_hcaptcha() with the correct shortcode tag.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$subject = new Sendinblue();
		$output  = '<form><button class="sib" type="submit">Send</button></form>';

		$result = $subject->add_hcaptcha( $output, 'sibwp_form', [ 'id' => 5 ], [] );

		self::assertStringContainsString( 'h-captcha', $result );
		self::assertStringContainsString( 'hcaptcha_sendinblue_nonce', $result );
		self::assertStringContainsString( '<button class="sib" type="submit">Send</button>', $result );
	}

	/**
	 * Test hCaptcha in a form rendered by the live Brevo shortcode.
	 *
	 * @return void
	 */
	public function test_live_form_shortcode(): void {
		\SIB_Forms::createTable();

		$forms = \SIB_Forms::getForms();

		if ( ! $forms ) {
			\SIB_Forms::createDefaultForm();
			$forms = \SIB_Forms::getForms();
		}

		$form_id = (int) $forms[0]['id'];
		$subject = new Sendinblue();
		$method  = new ReflectionMethod( \SIB_Manager::class, 'sibwp_form_shortcode' );
		$html    = do_shortcode( '[sibwp_form id="' . $form_id . '"]' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/mailin/' ),
			wp_normalize_path( (string) $method->getFileName() )
		);
		self::assertTrue( shortcode_exists( 'sibwp_form' ) );
		self::assertStringContainsString( 'id="sib_signup_form_' . $form_id . '"', $html );
		self::assertStringContainsString( 'name="sib_form_action"', $html );
		self::assertStringContainsString( 'name="hcaptcha_sendinblue_nonce"', $html );
		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_hcaptcha' ] ) );
	}

	/**
	 * Test verify_request() when not a Sendinblue request.
	 *
	 * @return void
	 */
	public function test_verify_request_not_sendinblue(): void {
		$subject = new Sendinblue();

		unset( $_POST['sib_form_action'] );

		self::assertSame( 'some_error', $subject->verify_request( 'some_error', [] ) );
	}

	/**
	 * Test verify_request() when verified (the result is null).
	 *
	 * @return void
	 */
	public function test_verify_request_verified(): void {
		$subject = new Sendinblue();

		$_POST['sib_form_action'] = 'submit';

		self::assertNull( $subject->verify_request( null, [] ) );
	}

	/**
	 * Test verify_request() when not verified (a result is error string).
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_request_not_verified(): void {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$_POST['sib_form_action'] = 'submit';

		$subject = new Sendinblue();

		ob_start();
		$subject->verify_request( 'Some hCaptcha error', [] );
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertSame( 'failure', $data['status'] );
		self::assertSame( 'Some hCaptcha error', $data['msg']['errorMsg'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test enqueue_scripts() when form not shown.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_no_form(): void {
		$subject = new Sendinblue();

		hcaptcha()->form_shown = false;

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( Sendinblue::HANDLE ) );
	}

	/**
	 * Test enqueue_scripts() when the form is shown.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Sendinblue();

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( Sendinblue::HANDLE ) );

		$script = wp_scripts()->registered[ Sendinblue::HANDLE ];

		self::assertStringContainsString( 'hcaptcha-sendinblue', $script->src );
		self::assertContains( 'jquery', $script->deps );
		self::assertContains( 'hcaptcha', $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
	}

	/**
	 * Test add_type_module() with a wrong handle.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module_wrong_handle(): void {
		$subject = new Sendinblue();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="some.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'wrong-handle', 'some.js' ) );
	}

	/**
	 * Test add_type_module() with a correct handle.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module(): void {
		$subject = new Sendinblue();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="hcaptcha-sendinblue.js"></script>';

		$result = $subject->add_type_module( $tag, Sendinblue::HANDLE, 'hcaptcha-sendinblue.js' );

		self::assertStringContainsString( 'type="module"', $result );
	}
}
