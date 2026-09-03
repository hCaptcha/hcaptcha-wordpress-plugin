<?php
/**
 * NewTopic class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\NewTopic;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use WP_Error;

/**
 * Test NewTopic class.
 *
 * @group bbpress
 */
class NewTopicTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'bbpress/bbpress.php';

	/**
	 * Hooks to replay after loading bbPress.
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
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Tear down the test.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST );
		bbpress()->errors = new WP_Error();

		parent::tearDown();
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$args = [
			'action' => 'hcaptcha_bbp_new_topic',
			'name'   => 'hcaptcha_bbp_new_topic_nonce',
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'new_topic',
			],
		];

		$expected = $this->get_hcap_form( $args );

		$subject = new NewTopic();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hCaptcha in the live bbPress new-topic shortcode.
	 *
	 * @return void
	 */
	public function test_live_new_topic_form(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );

		bbp_add_forums_roles();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		get_user_by( 'id', $user_id )->add_cap( 'keep_gate' );
		wp_set_current_user( $user_id );

		new NewTopic();
		$this->load_bbp_templates();

		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: null;

		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			$html = do_shortcode( '[bbp-topic-form]' );
		} finally {
			if ( null === $request_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $request_method;
			}
		}

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringContainsString( 'class="bbp-topic-form"', $html );
		self::assertStringContainsString( 'name="bbp_topic_title"', $html );
		self::assertStringContainsString( 'name="hcaptcha_bbp_new_topic_nonce"', $html );
	}

	/**
	 * Load bbPress templates when WP_USE_THEMES is disabled by the test runner.
	 *
	 * @return void
	 */
	private function load_bbp_templates(): void {
		add_action(
			'bbp_locate_template',
			static function ( $located, $template_name, $template_names, $template_locations, $load, $load_once ) {
				if ( $load && $located ) {
					load_template( $located, $load_once );
				}
			},
			10,
			6
		);
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_bbp_new_topic_nonce', 'hcaptcha_bbp_new_topic' );
		$this->prepare_widget_id();

		$expected = new WP_Error();
		$subject  = new NewTopic();

		self::assertTrue( $subject->verify() );

		self::assertEquals( $expected, bbpress()->errors );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$expected = new WP_Error( 'hcap_error', 'Please complete the hCaptcha.' );
		$subject  = new NewTopic();

		$this->prepare_verify_post( 'hcaptcha_bbp_new_topic_nonce', 'hcaptcha_bbp_new_topic', null );
		$this->prepare_widget_id();

		self::assertFalse( $subject->verify() );

		self::assertEquals( $expected, bbpress()->errors );
	}

	/**
	 * Prepare widget id.
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'new_topic',
			]
		);
	}
}
