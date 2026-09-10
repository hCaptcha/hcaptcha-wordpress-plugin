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
