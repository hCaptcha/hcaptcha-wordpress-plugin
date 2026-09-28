<?php
/**
 * EmailOptinTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Divi;

use HCaptcha\Divi\EmailOptin;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;

/**
 * Class EmailOptinTest
 *
 * @group divi
 * @group divi-email-optin
 */
class EmailOptinTest extends HCaptchaPluginWPTestCase {

	/**
	 * Theme stylesheet slug.
	 *
	 * @var string
	 */
	protected static string $theme = 'Divi';

	/**
	 * Live Divi shortcode modules used by the test.
	 *
	 * @var array<string, string>
	 */
	protected static array $theme_shortcode_classes = [
		'et_pb_signup' => 'ET_Builder_Module_Signup',
	];

	/**
	 * Expected incorrect usage notices caused by the late theme load.
	 *
	 * @var string[]
	 */
	protected static array $theme_expected_incorrect_usage = [ "add_theme_support( 'title-tag' )" ];

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		wp_dequeue_script( EmailOptin::HANDLE );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new EmailOptin();

		self::assertSame( 10, has_filter( 'et_pb_signup_form_field_html_submit_button', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_et_pb_submit_subscribe_form', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_et_pb_submit_subscribe_form', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test Divi's submitted signup fields reach the anti-spam entry.
	 *
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_entry_data(): void {
		$_POST = [
			'et_email'     => 'subscriber@example.com',
			'et_firstname' => 'Jane',
			'et_lastname'  => 'Doe',
			'et_token'     => 'do-not-copy',
		];

		$subject = new EmailOptin();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject );

		self::assertSame( 'subscriber@example.com', $entry['data']['email'] );
		self::assertSame( 'Jane Doe', $entry['data']['name'] );
		self::assertArrayNotHasKey( 'et_token', $entry['data'] );
	}

	/**
	 * Test the live Divi Email Opt-in module.
	 *
	 * @return void
	 */
	public function test_live_email_optin_module(): void {
		update_option( 'hcaptcha_settings', [ 'divi_status' => [ 'email_optin' ] ] );
		hcaptcha()->init_hooks();

		new EmailOptin();

		$output = do_shortcode(
			'[et_pb_signup provider="feedburner" feedburner_uri="hcaptcha-test"][/et_pb_signup]'
		);

		self::assertStringContainsString( 'et_pb_feedburner_form', $output );
		self::assertStringContainsString( 'feedburner.google.com/fb/a/mailverify', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_divi_email_optin_nonce"', $output );
	}

	/**
	 * Test honeypot output and widget source for a Divi component.
	 *
	 * @param string $component Active Divi component.
	 * @param string $source    Expected source.
	 *
	 * @dataProvider dp_test_honeypot_for_component
	 * @return void
	 */
	public function test_honeypot_for_component( string $component, string $source ): void {
		$subject = Mockery::mock( EmailOptin::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_divi_component' )->andReturn( $component );

		$output = $subject->add_captcha( '<p class="et_pb_newsletter_button_wrap"></p>', 'off' );
		$id     = HCaptcha::widget_id_value(
			[
				'source'  => [ $source ],
				'form_id' => 'email_optin',
			]
		);

		self::assertStringContainsString( 'value="' . esc_attr( $id ) . '"', $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test that a filled Divi component email opt-in honeypot is rejected.
	 *
	 * @param string $component Active Divi component.
	 * @param string $source    Expected source.
	 *
	 * @dataProvider dp_test_honeypot_for_component
	 * @return void
	 */
	public function test_filled_honeypot_for_component( string $component, string $source ): void {
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

		$this->prepare_verify_post( EmailOptin::NONCE, EmailOptin::ACTION );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ $source ],
				'form_id' => 'email_optin',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$subject = Mockery::mock( EmailOptin::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_divi_component' )->andReturn( $component );

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		self::assertSame( '{"error":"Anti-spam check failed."}', $json );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Data provider for Divi component honeypot tests.
	 *
	 * @return array
	 */
	public function dp_test_honeypot_for_component(): array {
		return [
			'Divi Builder' => [ 'divi_builder', 'divi-builder/divi-builder.php' ],
			'Extra theme'  => [ 'extra', 'Extra' ],
		];
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( EmailOptin::NONCE, EmailOptin::ACTION );
		$this->prepare_widget_id();

		$subject = new EmailOptin();

		$subject->verify();
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
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

		$this->prepare_verify_post( EmailOptin::NONCE, EmailOptin::ACTION, false );
		$this->prepare_widget_id();

		$subject = new EmailOptin();

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		self::assertSame( '{"error":"The hCaptcha is invalid."}', $json );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify() when widget id is bad.
	 *
	 * @return void
	 */
	public function test_verify_bad_widget_id(): void {
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

		$this->prepare_verify_post( EmailOptin::NONCE, EmailOptin::ACTION );
		$this->prepare_widget_id(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'email_optin',
			]
		);

		$subject = new EmailOptin();

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		self::assertSame( '{"error":"Bad hCaptcha signature!"}', $json );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		hcaptcha()->form_shown = true;

		self::assertFalse( wp_script_is( EmailOptin::HANDLE ) );

		$subject = new EmailOptin();

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( EmailOptin::HANDLE ) );
	}

	/**
	 * Test enqueue_scripts() when the form was not shown.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_when_form_was_not_shown(): void {
		self::assertFalse( wp_script_is( EmailOptin::HANDLE ) );

		$subject = new EmailOptin();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( EmailOptin::HANDLE ) );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module(): void {
		$subject = new EmailOptin();

		// Wrong handle.

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag    = '<script src="https://example.com/script.js"></script>';
		$handle = 'some';
		$src    = 'https://example.com/script.js';

		self::assertSame( $tag, $subject->add_type_module( $tag, $handle, $src ) );

		// Proper handle.
		$handle   = EmailOptin::HANDLE;
		$expected = '<script type="module" src="https://example.com/script.js"></script>';

		self::assertSame( $expected, $subject->add_type_module( $tag, $handle, $src ) );

		// Script has a type.
		$tag = '<script type="text/javascript" src="https://example.com/script.js"></script>';
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		self::assertSame( $expected, $subject->add_type_module( $tag, $handle, $src ) );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param array $id The hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'Divi' ],
			'form_id' => 'email_optin',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
}
