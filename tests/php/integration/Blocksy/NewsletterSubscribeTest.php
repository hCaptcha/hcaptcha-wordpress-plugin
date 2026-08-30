<?php
/**
 * NewsletterSubscribeTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Blocksy;

use HCaptcha\Blocksy\NewsletterSubscribe;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use WP_Block;

/**
 * Test NewsletterSubscribe class.
 *
 * @group blocksy
 */
class NewsletterSubscribeTest extends HCaptchaPluginWPTestCase {

	/**
	 * Blocksy Companion plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'blocksy-companion/blocksy-companion.php';

	/**
	 * Blocksy theme stylesheet.
	 *
	 * @var string
	 */
	protected static string $theme = 'blocksy';

	/**
	 * Hooks to replay after loading Blocksy Companion.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'init' ];

	/**
	 * Expected incorrect usage notices raised by the live plugin.
	 *
	 * @var string[]
	 */
	protected static array $plugin_expected_incorrect_usage = [ "add_theme_support( 'title-tag' )" ];

	/**
	 * Enable the newsletter extension before Blocksy Companion is loaded.
	 *
	 * @return void
	 */
	protected function before_load_test_plugins(): void {
		update_option( 'blocksy_active_extensions', [ 'newsletter-subscribe' ] );
		update_option(
			'blocksy_ext_mailchimp_credentials',
			[
				'provider' => 'demo',
				'api_key'  => 'test-key',
				'list_id'  => 'demolist',
			]
		);
	}

	/**
	 * Test a newsletter form rendered by the live Blocksy block.
	 *
	 * @return void
	 */
	public function test_live_blocksy_newsletter_form(): void {
		new NewsletterSubscribe();

		$output      = do_blocks( '<!-- wp:blocksy/newsletter {"newsletter_subscribe_button_text":"Subscribe live"} /-->' );
		$plugin_file = wp_normalize_path( ( new ReflectionClass( 'BlocksyExtensionNewsletterSubscribe' ) )->getFileName() );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertSame( 'blocksy', get_stylesheet() );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/blocksy-companion/' ), $plugin_file );
		self::assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'blocksy/newsletter' ) );
		self::assertStringContainsString( 'class="ct-newsletter-subscribe-form"', $output );
		self::assertStringContainsString( '<button class="wp-element-button"', $output );
		self::assertStringContainsString( 'Subscribe live', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_blocksy_newsletter_subscribe_nonce"', $output );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new NewsletterSubscribe();

		self::assertSame( 10, has_filter( 'render_block', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame(
			9,
			has_action( 'wp_ajax_blc_newsletter_subscribe_process_ajax_subscribe', [ $subject, 'verify' ] )
		);
		self::assertSame(
			9,
			has_action( 'wp_ajax_nopriv_blc_newsletter_subscribe_process_ajax_subscribe', [ $subject, 'verify' ] )
		);
		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$block    = [
			'blockName' => 'some/block',
		];
		$instance = Mockery::mock( WP_Block::class );
		$content  = '<div class="newsletter"><button type="submit">Subscribe</button></div>';

		$subject = new NewsletterSubscribe();

		// Wrong block — returns content unchanged.
		self::assertSame( $content, $subject->add_hcaptcha( $content, $block, $instance ) );

		// Correct block — inserts hcaptcha before <button.
		$block['blockName'] = 'blocksy/newsletter';

		$result = $subject->add_hcaptcha( $content, $block, $instance );

		self::assertStringContainsString( 'h-captcha', $result );
		self::assertStringContainsString( '<button type="submit">Subscribe</button>', $result );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$this->prepare_verify_post(
			'hcaptcha_blocksy_newsletter_subscribe_nonce',
			'hcaptcha_blocksy_newsletter_subscribe'
		);
		$this->prepare_widget_id();

		$_POST['email'] = 'test@example.com';
		$_POST['group'] = 'newsletter';

		$subject = new NewsletterSubscribe();

		// Successful verification — should return without calling wp_send_json_error.
		$subject->verify();
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 */
	public function test_add_type_module(): void {
		$subject = new NewsletterSubscribe();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="/assets/js/hcaptcha-blocksy.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'other-handle', '' ) );
		self::assertStringContainsString(
			'type="module"',
			$subject->add_type_module( $tag, 'hcaptcha-blocksy', '' )
		);
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new NewsletterSubscribe();

		$subject->enqueue_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-blocksy' ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();
		self::assertTrue( wp_script_is( 'hcaptcha-blocksy' ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_not_verified(): void {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_verify_post(
			'hcaptcha_blocksy_newsletter_subscribe_nonce',
			'hcaptcha_blocksy_newsletter_subscribe',
			false
		);
		$this->prepare_widget_id();

		$_POST['email'] = 'test@example.com';
		$_POST['group'] = 'newsletter';

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new NewsletterSubscribe();

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertArrayHasKey( 'result', $data['data'] );
		self::assertSame( 'no', $data['data']['result'] );
		self::assertNotEmpty( $data['data']['message'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify() when widget id is missing.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_missing_widget_id(): void {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_verify_post(
			'hcaptcha_blocksy_newsletter_subscribe_nonce',
			'hcaptcha_blocksy_newsletter_subscribe'
		);

		$_POST['email'] = 'test@example.com';
		$_POST['group'] = 'newsletter';

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new NewsletterSubscribe();

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( 'no', $data['data']['result'] );
		self::assertSame( 'Bad hCaptcha signature!', $data['data']['message'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test get_entry().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_entry(): void {
		global $post;

		$post_id = wp_insert_post(
			[
				'post_title'  => 'Test Page',
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );

		$subject = new NewsletterSubscribe();
		$method  = $this->set_method_accessibility( $subject, 'get_entry' );

		$form_data = [
			'h-captcha-response' => 'some-response',
			'email'              => 'test@example.com',
			'group'              => 'newsletter',
			// Non-matching key — covers continue branch.
			'some_other_field'   => 'value',
			// Uppercase key — covers strtolower + matching.
			'Email'              => 'upper@example.com',
		];

		$actual = $method->invoke( $subject, $form_data );

		self::assertSame( 'hcaptcha_blocksy_newsletter_subscribe_nonce', $actual['nonce_name'] );
		self::assertSame( 'hcaptcha_blocksy_newsletter_subscribe', $actual['nonce_action'] );
		self::assertSame( 'some-response', $actual['h-captcha-response'] );
		self::assertNotNull( $actual['form_date_gmt'] );
		// 'Email' key overwrites 'email' after strtolower.
		self::assertSame( 'upper@example.com', $actual['data']['email'] );
		self::assertSame( 'newsletter', $actual['data']['group'] );
		self::assertSame(
			[
				'source'  => HCaptcha::get_class_source( NewsletterSubscribe::class ),
				'form_id' => 'newsletter-subscribe',
			],
			$actual['expected_id']
		);
		self::assertArrayNotHasKey( 'some_other_field', $actual['data'] );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param array $id Widget id.
	 *
	 * @return void
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = array_merge(
			[
				'source'  => HCaptcha::get_class_source( NewsletterSubscribe::class ),
				'form_id' => 'newsletter-subscribe',
			],
			$id
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		$subject = new NewsletterSubscribe();

		ob_start();

		$subject->print_inline_styles();

		$output = ob_get_clean();

		self::assertStringContainsString( '.ct-newsletter-subscribe-form input[type="email"]', $output );
		self::assertStringContainsString( '.ct-newsletter-subscribe-form h-captcha', $output );
		self::assertStringContainsString( '.ct-newsletter-subscribe-form button', $output );
	}
}
