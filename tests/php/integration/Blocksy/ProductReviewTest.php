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
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use stdClass;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test ProductReview class.
 *
 * @group blocksy
 */
class ProductReviewTest extends HCaptchaWPTestCase {

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
		$screen = Mockery::mock( 'Blocksy_Screen_Manager' );
		$screen->shouldReceive( 'is_product' )->andReturn( false );

		$manager         = new stdClass();
		$manager->screen = $screen;

		FunctionMocker::replace(
			'blocksy_manager',
			static function () use ( $manager ) {
				return $manager;
			}
		);

		$submit_field = '<div class="form-submit"><button type="submit">Submit</button></div>';
		$comment_args = [];

		$subject = new ProductReview();

		$result = $subject->add_hcaptcha( $submit_field, $comment_args );

		// Should contain signature but not hcaptcha form.
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

		$screen = Mockery::mock( 'Blocksy_Screen_Manager' );
		$screen->shouldReceive( 'is_product' )->andReturn( true );

		$manager         = new stdClass();
		$manager->screen = $screen;

		FunctionMocker::replace(
			'blocksy_manager',
			static function () use ( $manager ) {
				return $manager;
			}
		);

		$submit_field = '<div class="form-submit"><button type="submit">Submit</button></div>';
		$comment_args = [];

		$subject = new ProductReview();

		$result = $subject->add_hcaptcha( $submit_field, $comment_args );

		self::assertStringContainsString( 'h-captcha', $result );
		self::assertStringContainsString( '<button type="submit">Submit</button>', $result );
	}

	/**
	 * Test add_hcaptcha() when the screen is null.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_no_screen(): void {
		$manager         = new stdClass();
		$manager->screen = null;

		FunctionMocker::replace(
			'blocksy_manager',
			static function () use ( $manager ) {
				return $manager;
			}
		);

		$submit_field = '<div class="form-submit"><button type="submit">Submit</button></div>';
		$comment_args = [];

		$subject = new ProductReview();

		$result = $subject->add_hcaptcha( $submit_field, $comment_args );

		// Should contain a signature but not hcaptcha form (not a product).
		self::assertStringContainsString( '<button type="submit">Submit</button>', $result );
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
	.ct-product-waitlist-form input[type="email"] {
		grid-row: 1;
	}

	.ct-product-waitlist-form h-captcha {
		grid-row: 2;
		margin-bottom: 0;
	}

	.ct-product-waitlist-form button {
		grid-row: 3;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new ProductReview();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
