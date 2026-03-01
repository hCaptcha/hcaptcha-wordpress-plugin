<?php
/**
 * WaitlistTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Blocksy;

use HCaptcha\Blocksy\Waitlist;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test Waitlist class.
 *
 * @group blocksy
 */
class WaitlistTest extends HCaptchaWPTestCase {

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
		$subject = new Waitlist();

		self::assertSame(
			0,
			has_action( 'blocksy:woocommerce:product:custom:layer', [ $subject, 'before_layer' ] )
		);
		self::assertSame(
			20,
			has_action( 'blocksy:woocommerce:product:custom:layer', [ $subject, 'after_layer' ] )
		);
		self::assertSame(
			10,
			has_filter( 'blocksy:ext:woocommerce-extra:waitlist:subscribe:validate', [ $subject, 'verify' ] )
		);
		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test before_layer() with the wrong layer.
	 *
	 * @return void
	 */
	public function test_before_layer_wrong_layer(): void {
		$subject = new Waitlist();

		$level = ob_get_level();

		$subject->before_layer( [ 'id' => 'some_other' ] );

		// ob_start() should not have been called, so ob_get_level should be the same.
		self::assertSame( $level, ob_get_level() );
	}

	/**
	 * Test before_layer() and after_layer().
	 *
	 * @return void
	 */
	public function test_before_and_after_layer(): void {
		$layer = [
			'id'   => 'product_waitlist',
			'__id' => 'waitlist_123',
		];

		$subject = new Waitlist();

		// Capture everything: before_layer starts its own ob, after_layer consumes it and echoes.
		ob_start();

		$subject->before_layer( $layer );

		echo '<form><button class="ct-button" type="submit">Subscribe</button></form>';

		$subject->after_layer( $layer );

		$output = ob_get_clean();

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( '<button class="ct-button" type="submit">', $output );
	}

	/**
	 * Test after_layer() with the wrong layer.
	 *
	 * @return void
	 */
	public function test_after_layer_wrong_layer(): void {
		$subject = new Waitlist();

		ob_start();
		$subject->after_layer( [ 'id' => 'some_other' ] );
		$output = ob_get_clean();

		self::assertSame( '', $output );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$product_id = 42;
		$email      = 'test@example.com';

		$this->prepare_verify_post( 'hcaptcha_blocksy_waitlist_nonce', 'hcaptcha_blocksy_waitlist' );

		$_POST['product_id'] = $product_id;
		$_POST['email']      = $email;

		$subject = new Waitlist();

		$result = $subject->verify( null, $product_id, $email );

		self::assertNull( $result );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		$product_id = 42;
		$email      = 'test@example.com';

		$this->prepare_verify_post( 'hcaptcha_blocksy_waitlist_nonce', 'hcaptcha_blocksy_waitlist', false );

		$_POST['product_id'] = $product_id;
		$_POST['email']      = $email;

		$subject = new Waitlist();

		$result = $subject->verify( null, $product_id, $email );

		self::assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test verify() with existing WP_Error.
	 *
	 * @return void
	 */
	public function test_verify_with_existing_wp_error(): void {
		$product_id = 42;
		$email      = 'test@example.com';
		$existing   = new WP_Error( 'some_error', 'Some error' );

		$this->prepare_verify_post( 'hcaptcha_blocksy_waitlist_nonce', 'hcaptcha_blocksy_waitlist', false );

		$_POST['product_id'] = $product_id;
		$_POST['email']      = $email;

		$subject = new Waitlist();

		$result = $subject->verify( $existing, $product_id, $email );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertNotEmpty( $result->get_error_messages( 'hcaptcha_error' ) );
		self::assertNotEmpty( $result->get_error_messages( 'some_error' ) );
	}

	/**
	 * Test verify() with a non-null, non-WP_Error value.
	 *
	 * @return void
	 */
	public function test_verify_with_non_standard_value(): void {
		$product_id = 42;
		$email      = 'test@example.com';

		$this->prepare_verify_post( 'hcaptcha_blocksy_waitlist_nonce', 'hcaptcha_blocksy_waitlist' );

		$_POST['product_id'] = $product_id;
		$_POST['email']      = $email;

		$subject = new Waitlist();

		// Pass a non-null, non-WP_Error value — should be reset to null.
		$result = $subject->verify( 'some_string', $product_id, $email );

		self::assertNull( $result );
	}

	/**
	 * Test get_entry().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_entry(): void {
		$product_id = wp_insert_post(
			[
				'post_title'  => 'Test Product',
				'post_status' => 'publish',
				'post_type'   => 'product',
			]
		);

		$_POST['product_id'] = (string) $product_id;

		$subject = new Waitlist();
		$method  = $this->set_method_accessibility( $subject, 'get_entry' );

		$form_data = [
			'h-captcha-response' => 'some-response',
			'email'              => 'test@example.com',
			'product_id'         => (string) $product_id,
			// Non-matching key — covers continue branch.
			'some_other_field'   => 'value',
		];

		$actual = $method->invoke( $subject, $form_data );

		self::assertSame( 'hcaptcha_blocksy_waitlist_nonce', $actual['nonce_name'] );
		self::assertSame( 'hcaptcha_blocksy_waitlist', $actual['nonce_action'] );
		self::assertSame( 'some-response', $actual['h-captcha-response'] );
		self::assertNotNull( $actual['form_date_gmt'] );
		self::assertSame( 'test@example.com', $actual['data']['email'] );
		self::assertSame( (string) $product_id, $actual['data']['product_id'] );
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

		$subject = new Waitlist();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
