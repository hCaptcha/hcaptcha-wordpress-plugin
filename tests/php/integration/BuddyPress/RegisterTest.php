<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\BuddyPress;

use HCaptcha\BuddyPress\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test Register.
 *
 * @group bp
 */
class RegisterTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'buddypress/bp-loader.php';

	/**
	 * Hooks to replay after loading BuddyPress.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'setup_theme',
		'after_setup_theme',
		'init',
	];

	/**
	 * Initialize BuddyPress after the WordPress test bootstrap.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Enable the BuddyPress components used by these tests.
	 *
	 * @return void
	 */
	protected function before_load_test_plugins(): void {
		add_filter(
			'bp_active_components',
			static function ( $components ) {
				$components['groups'] = '1';

				return $components;
			}
		);
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		global $bp;

		unset( $bp->signup );

		parent::tearDown();
	}

	/**
	 * Test init hooks.
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Register();

		self::assertSame( 10, has_action( 'bp_before_registration_submit_buttons', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_action( 'bp_signup_validate', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$args     = [
			'action' => 'hcaptcha_bp_register',
			'name'   => 'hcaptcha_bp_register_nonce',
			'id'     => [
				'source'  => [ 'buddypress/bp-loader.php' ],
				'form_id' => 'register',
			],
		];
		$expected =
			'<div class="hcap_buddypress_register_form">' .
			$this->get_hcap_form( $args ) .
			'</div>';

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hCaptcha in the live BuddyPress registration template.
	 *
	 * @return void
	 */
	public function test_live_registration_template(): void {
		$bp         = buddypress();
		$bp->signup = (object) [
			'step'   => 'request-details',
			'errors' => [],
		];
		$subject    = new Register();
		$template   = bp_locate_template( 'members/register.php' );
		$html       = bp_buffer_template_part( 'members/register', null, false );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/buddypress/' ),
			wp_normalize_path( (string) $template )
		);
		self::assertStringContainsString( 'name="signup_form"', $html );
		self::assertStringContainsString( 'name="signup_email"', $html );
		self::assertStringContainsString( 'class="hcap_buddypress_register_form"', $html );
		self::assertStringContainsString( 'name="hcaptcha_bp_register_nonce"', $html );
		self::assertSame( 10, has_action( 'bp_before_registration_submit_buttons', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test add_captcha() with an error.
	 */
	public function test_register_error(): void {
		global $bp;

		$args                     = [
			'action' => 'hcaptcha_bp_register',
			'name'   => 'hcaptcha_bp_register_nonce',
			'id'     => [
				'source'  => [ 'buddypress/bp-loader.php' ],
				'form_id' => 'register',
			],
		];
		$hcaptcha_response_verify = 'some response';

		$bp->signup = (object) [
			'errors' => [
				'hcaptcha_response_verify' => $hcaptcha_response_verify,
			],
		];

		$expected =
			'<div class="hcap_buddypress_register_form">' .
			'<div class="error">' .
			$hcaptcha_response_verify .
			'</div>' .
			$this->get_hcap_form( $args ) .
			'</div>';
		$subject  = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_bp_register_nonce', 'hcaptcha_bp_register' );
		$this->prepare_widget_id();

		$subject = new Register();

		self::assertTrue( $subject->verify() );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		global $bp;

		$bp->signup = (object) [
			'errors' => [],
		];
		$expected   = (object) [
			'errors' => [
				'hcaptcha_response_verify' => 'Please complete the hCaptcha.',
			],
		];
		$subject    = new Register();

		$this->prepare_verify_post( 'hcaptcha_bp_register_nonce', 'hcaptcha_bp_register', null );
		$this->prepare_widget_id();

		self::assertFalse( $subject->verify() );

		self::assertEquals( $expected, $bp->signup );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		$subject = new Register();

		ob_start();

		$subject->print_inline_styles();
		$css = (string) ob_get_clean();

		self::assertStringContainsString( '<style>', $css );
		self::assertStringContainsString( '.hcap_buddypress_register_form', $css );
		self::assertStringContainsString( 'margin-inline-start', $css );
		self::assertStringContainsString( '@media', $css );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'buddypress/bp-loader.php' ],
				'form_id' => 'register',
			]
		);
	}
}
