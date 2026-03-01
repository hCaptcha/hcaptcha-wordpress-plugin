<?php
/**
 * NewsletterSubscribeTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Blocksy;

use HCaptcha\Blocksy\NewsletterSubscribe;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Block;

/**
 * Test NewsletterSubscribe class.
 *
 * @group blocksy
 */
class NewsletterSubscribeTest extends HCaptchaWPTestCase {

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

		$_POST['email'] = 'test@example.com';
		$_POST['group'] = 'newsletter';

		$subject = new NewsletterSubscribe();

		// Successful verification — should return without calling wp_send_json_error.
		$subject->verify();
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
		self::assertArrayNotHasKey( 'some_other_field', $actual['data'] );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'CSS'
	.ct-newsletter-subscribe-form input[type="email"] {
		grid-row: 1;
	}

	.ct-newsletter-subscribe-form h-captcha {
		grid-row: 2;
		margin-bottom: 0;
	}

	.ct-newsletter-subscribe-form button {
		grid-row: 3;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new NewsletterSubscribe();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
