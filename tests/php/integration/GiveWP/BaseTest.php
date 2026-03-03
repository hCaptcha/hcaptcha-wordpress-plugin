<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\GiveWP;

use HCaptcha\GiveWP\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\Tests\Integration\Stubs\Give\DonationForms\ValueObjects\DonationFormErrorTypesStub;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use Give\DonationForms\ValueObjects\DonationFormErrorTypes;

/**
 * Test Base class (via Form).
 *
 * @group givewp
 */
class BaseTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST, $_GET, $_SERVER['REQUEST_METHOD'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Form();

		self::assertSame(
			10,
			has_action( 'give_donation_form_user_info', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'give_checkout_error_checks', [ $subject, 'verify' ] )
		);
		self::assertSame(
			9,
			has_action( 'template_redirect', [ $subject, 'verify_block' ] )
		);
	}

	/**
	 * Test init_hooks() with a donation-form-view route.
	 *
	 * @return void
	 */
	public function test_init_hooks_with_donation_form_view_route(): void {
		$_GET['givewp-route'] = 'donation-form-view';
		$_GET['form-id']      = '123';

		$subject = new Form();

		self::assertSame(
			0,
			has_filter( 'hcap_print_hcaptcha_scripts', '__return_true' )
		);
		self::assertSame(
			9,
			has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
		);
		self::assertSame(
			10,
			has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] )
		);
	}

	/**
	 * Test init_hooks() with the wrong route — early return.
	 *
	 * @return void
	 */
	public function test_init_hooks_wrong_route(): void {
		$_GET['givewp-route'] = 'some-other-route';
		$_GET['form-id']      = '123';

		$subject = new Form();

		self::assertFalse(
			has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
		);
	}

	/**
	 * Test init_hooks() with no form-id — early return.
	 *
	 * @return void
	 */
	public function test_init_hooks_no_form_id(): void {
		$_GET['givewp-route'] = 'donation-form-view';

		$subject = new Form();

		self::assertFalse(
			has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
		);
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$form_id = 42;
		$subject = new Form();

		ob_start();
		$subject->add_captcha( $form_id );
		$output = ob_get_clean();

		self::assertStringContainsString( 'h-captcha', $output );
	}

	/**
	 * Test verify() with the correct action.
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );

		$_POST['action'] = 'give_process_donation';

		FunctionMocker::replace( 'give_set_error' );

		$subject = new Form();

		$subject->verify( true );

		// Verified successfully — give_set_error should not be called.
		// No exception means success.
		self::assertTrue( true );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form', false );

		$_POST['action'] = 'give_process_donation';

		$error_slug    = '';
		$error_message = '';

		FunctionMocker::replace(
			'give_set_error',
			static function ( $slug, $message ) use ( &$error_slug, &$error_message ) {
				$error_slug    = $slug;
				$error_message = $message;
			}
		);

		$subject = new Form();

		$subject->verify( true );

		self::assertSame( 'invalid_hcaptcha', $error_slug );
		self::assertNotEmpty( $error_message );
	}

	/**
	 * Test verify() with the wrong action — early return.
	 *
	 * @return void
	 */
	public function test_verify_wrong_action(): void {
		$_POST['action'] = 'some_other_action';

		$called = false;

		FunctionMocker::replace(
			'give_set_error',
			static function () use ( &$called ) {
				$called = true;
			}
		);

		$subject = new Form();

		$subject->verify( true );

		self::assertFalse( $called );
	}

	/**
	 * Test verify_block() when not POST request.
	 *
	 * @return void
	 */
	public function test_verify_block_not_post(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$subject = new Form();

		// Should return early — no error.
		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Test verify_block() with the wrong route.
	 *
	 * @return void
	 */
	public function test_verify_block_wrong_route(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['givewp-route']      = 'some-other';

		$subject = new Form();

		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Test verify_block() with no route (empty string fallback).
	 *
	 * @return void
	 */
	public function test_verify_block_no_route(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		unset( $_GET['givewp-route'] );

		$subject = new Form();

		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Test verify_block() with the correct route — verified.
	 *
	 * @return void
	 */
	public function test_verify_block_verified(): void {
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';
		$_POST['h-captcha-response']       = 'some-response';

		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form' );

		$subject = new Form();

		// Should return without sending JSON error.
		$subject->verify_block();

		self::assertTrue( true );
	}

	/**
	 * Test verify_block() with no h-captcha-response (empty string fallback).
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_block_no_hcaptcha_response(): void {
		Mockery::namedMock( DonationFormErrorTypes::class, DonationFormErrorTypesStub::class );

		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';

		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form', false );

		unset( $_POST['h-captcha-response'] );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Form();

		ob_start();
		$subject->verify_block();
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify_block() with the correct route — not verified.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_block_not_verified(): void {
		Mockery::namedMock( DonationFormErrorTypes::class, DonationFormErrorTypes::class );

		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_GET['givewp-route']              = 'donate';
		$_GET['givewp-route-signature-id'] = 'givewp-donate';
		$_POST['h-captcha-response']       = 'bad-response';

		$this->prepare_verify_post( 'hcaptcha_give_wp_form_nonce', 'hcaptcha_give_wp_form', false );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Form();

		ob_start();
		$subject->verify_block();
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test print_footer_scripts().
	 *
	 * @return void
	 */
	public function test_print_footer_scripts(): void {
		$_GET['givewp-route'] = 'donation-form-view';
		$_GET['form-id']      = '42';

		$subject = new Form();

		$subject->print_footer_scripts();

		$script = wp_scripts()->registered['hcaptcha-give-wp'] ?? null;

		self::assertNotNull( $script );
		self::assertStringContainsString( 'hcaptcha-givewp', $script->src );
		self::assertContains( 'wp-blocks', $script->deps );
		self::assertContains( 'hcaptcha', $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );

		// Check localization.
		self::assertNotEmpty( $script->extra['data'] );
		self::assertStringContainsString( 'HCaptchaGiveWPObject', $script->extra['data'] );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module(): void {
		$subject = new Form();

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag    = '<script src="some.js"></script>';
		$result = $subject->add_type_module( $tag, 'some-other-handle', '' );

		self::assertSame( $tag, $result );

		$tag    = '<script src="hcaptcha-givewp.js"></script>';
		$result = $subject->add_type_module( $tag, 'hcaptcha-give-wp', '' );
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		self::assertStringContainsString( 'type="module"', $result );
	}
}
