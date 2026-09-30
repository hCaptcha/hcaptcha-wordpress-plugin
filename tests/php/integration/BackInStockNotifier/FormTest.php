<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BackInStockNotifier;

use HCaptcha\BackInStockNotifier\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the Back In Stock Notifier integration.
 *
 * @group back-in-stock-notifier
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'back_in_stock_notifier_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that Back In Stock Notifier hooks are registered.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 10, has_action( 'cwg_instock_after_email_field', [ $subject, 'after_email_field' ] ) );
		self::assertSame( 10, has_action( 'cwginstock_after_submit_button', [ $subject, 'after_submit_button' ] ) );
		self::assertSame( 0, has_action( 'cwginstock_ajax_data', [ $subject, 'verify' ] ) );
		self::assertSame( -1, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );
	}

	/**
	 * Test captcha placement and form arguments.
	 */
	public function test_add_hcaptcha(): void {
		$product_id = 123;
		$args       = [];
		$subject    = new Form();

		FunctionMocker::replace( '\\HCaptcha\\Helpers\\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::form',
			static function ( array $form_args ) use ( &$args ): string {
				$args = $form_args;

				return 'captcha';
			}
		);

		ob_start();
		$subject->after_email_field( $product_id, 0 );
		echo '<div class="form-group">Form</div>';
		$subject->after_submit_button( $product_id, 0 );
		$output = (string) ob_get_clean();

		self::assertSame(
			'<div class="form-group center-block" style="text-align:center;">captcha</div>' .
			'<div class="form-group">Form</div>',
			$output
		);
		self::assertSame(
			[
				'action' => 'hcaptcha_back_in_stock_notifier',
				'name'   => 'hcaptcha_back_in_stock_notifier_nonce',
				'id'     => [
					'source'  => [ 'source' ],
					'form_id' => $product_id,
				],
			],
			$args
		);
	}

	/**
	 * Test that submitted fields are passed to verification.
	 */
	public function test_verify(): void {
		$post_data = [
			'h-captcha-response'                    => 'response-token',
			'hcaptcha-widget-id'                    => 'widget-id',
			'hcaptcha_back_in_stock_notifier_nonce' => 'nonce',
		];
		$actual    = [];

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\API::verify_post_data',
			static function ( string $name, string $action, array $data ) use ( &$actual ): ?string {
				$actual = [ $name, $action, $data ];

				return null;
			}
		);

		( new Form() )->verify( $post_data, false );

		self::assertSame(
			[ 'hcaptcha_back_in_stock_notifier_nonce', 'hcaptcha_back_in_stock_notifier', $post_data ],
			$actual
		);
	}

	/**
	 * Test that an AJAX verification error is returned as JSON.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_with_ajax_error(): void {
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\API::verify_post_data', 'Verification failed.' );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
				};
			}
		);

		ob_start();
		( new Form() )->verify( [], false );
		$result = json_decode( (string) ob_get_clean(), true );

		self::assertSame(
			[ 'msg' => "<div class='cwginstockerror' style='color:red;'>Verification failed.</div>" ],
			$result
		);
	}

	/**
	 * Test that a REST verification error is returned as JSON.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_with_rest_error(): void {
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\API::verify_post_data', 'Verification failed.' );

		$subject = Mockery::mock( Form::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'exit' )->once();

		ob_start();
		$subject->verify( [], true );
		$result = json_decode( (string) ob_get_clean(), true );

		self::assertSame(
			[ 'msg' => "<div class='cwginstockerror' style='color:red;'>Verification failed.</div>" ],
			$result
		);
	}

	/**
	 * Test script loading on the shop and after a form is shown.
	 *
	 * @param bool $is_shop Whether the current page is a shop.
	 * @param bool $form_shown Whether a form is shown.
	 * @param bool $expected Whether the script is expected.
	 * @dataProvider dp_test_enqueue_scripts
	 */
	public function test_enqueue_scripts( bool $is_shop, bool $form_shown, bool $expected ): void {
		FunctionMocker::replace( 'is_shop', $is_shop );

		$subject               = new Form();
		hcaptcha()->form_shown = $form_shown;
		$subject->enqueue_scripts();

		self::assertSame( $form_shown || $is_shop, hcaptcha()->form_shown );
		self::assertSame( $expected, wp_script_is( 'hcaptcha-back-in-stock-notifier' ) );

		if ( $expected ) {
			self::assertSame(
				HCAPTCHA_URL . '/assets/js/hcaptcha-back-in-stock-notifier' . hcap_min_suffix() . '.js',
				wp_scripts()->registered['hcaptcha-back-in-stock-notifier']->src
			);
		}
	}

	/**
	 * Data provider for script loading.
	 *
	 * @return array
	 */
	public function dp_test_enqueue_scripts(): array {
		return [
			'not shown' => [ false, false, false ],
			'shown'     => [ false, true, true ],
			'shop'      => [ true, false, true ],
		];
	}

	/**
	 * Test adding a module type only to the Back In Stock Notifier script.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module(): void {
		$subject = new Form();
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="script.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'other', 'script.js' ) );
		self::assertSame(
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			'<script type="module" src="script.js"></script>',
			$subject->add_type_module( $tag, 'hcaptcha-back-in-stock-notifier', 'script.js' )
		);
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		$subject = new Form();

		ob_start();
		$subject->after_email_field( 123, 0 );

		echo '<div class="form-group">Form</div>';

		$subject->after_submit_button( 123, 0 );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks a subscription request.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_back_in_stock_notifier_nonce',
			'hcaptcha_back_in_stock_notifier'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php' ],
				'form_id' => 123,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$subject = Mockery::mock( Form::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'exit' )->once();

		ob_start();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$subject->verify( $_POST, true );
		$result = json_decode( (string) ob_get_clean(), true );

		self::assertStringContainsString( 'Anti-spam check failed.', $result['msg'] );
	}
}
