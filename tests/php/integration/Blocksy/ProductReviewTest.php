<?php
/**
 * ProductReviewTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Blocksy;

use HCaptcha\Blocksy\ProductReview;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionClass;

/**
 * Test ProductReview class.
 *
 * @group blocksy
 */
class ProductReviewTest extends HCaptchaPluginWPTestCase {

	/**
	 * WooCommerce plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'woocommerce/woocommerce.php';

	/**
	 * Blocksy theme stylesheet.
	 *
	 * @var string
	 */
	protected static string $theme = 'blocksy';

	/**
	 * Hooks to replay after loading WooCommerce.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Test that the live Blocksy screen manager and WooCommerce are loaded.
	 *
	 * @return void
	 */
	public function test_live_blocksy_and_woocommerce_are_loaded(): void {
		$theme_file = wp_normalize_path( ( new ReflectionClass( 'Blocksy_Screen_Manager' ) )->getFileName() );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertSame( 'blocksy', get_stylesheet() );
		self::assertStringStartsWith( wp_normalize_path( get_theme_root() . '/blocksy/' ), $theme_file );
		self::assertNotNull( blocksy_manager()->screen );
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
		$subject = new ProductReview();

		self::assertSame( 0, has_filter( 'comment_form_submit_field', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( - PHP_INT_MAX, has_filter( 'preprocess_comment', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_filter( 'pre_comment_approved', [ $subject, 'pre_comment_approved' ] ) );
	}

	/**
	 * Test add_hcaptcha() when not a product page.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_not_product(): void {
		$page_id = wp_insert_post(
			[
				'post_title'  => 'Test Page',
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);
		$this->go_to( get_permalink( $page_id ) );

		$submit_field = '<div class="form-submit"><button type="submit">Submit</button></div>';
		$comment_args = [];

		$subject = new ProductReview();

		$result = $subject->add_hcaptcha( $submit_field, $comment_args );

		self::assertFalse( blocksy_manager()->screen->is_product() );
		self::assertStringNotContainsString( '<h-captcha', $result );
		self::assertStringContainsString( '<button type="submit">Submit</button>', $result );
	}

	/**
	 * Test add_hcaptcha() when on a product page.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_product(): void {
		$product_id = wp_insert_post(
			[
				'post_title'  => 'Test Product',
				'post_status' => 'publish',
				'post_type'   => 'product',
			]
		);

		$this->go_to( get_permalink( $product_id ) );

		$GLOBALS['wp_query']->queried_object    = get_post( $product_id );
		$GLOBALS['wp_query']->queried_object_id = $product_id;

		$submit_field = '<div class="form-submit"><button type="submit">Submit</button></div>';
		$comment_args = [];

		$subject = new ProductReview();

		$result = $subject->add_hcaptcha( $submit_field, $comment_args );

		self::assertTrue( blocksy_manager()->screen->is_product() );
		self::assertStringContainsString( 'h-captcha', $result );
		self::assertStringContainsString( '<button type="submit">Submit</button>', $result );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		$subject = new ProductReview();

		ob_start();

		$subject->print_inline_styles();

		$output = ob_get_clean();

		self::assertStringContainsString( '.ct-product-waitlist-form input[type="email"]', $output );
		self::assertStringContainsString( '.ct-product-waitlist-form h-captcha', $output );
		self::assertStringContainsString( '.ct-product-waitlist-form button', $output );
	}
}
