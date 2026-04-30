<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Kadence\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;

/**
 * Test Kadence Form.
 *
 * @group kadence
 * @group kadence-form
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'], $_POST );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @param bool $is_frontend Whether it is a frontend request.
	 *
	 * @return void
	 * @dataProvider dp_test_constructor_and_init_hooks
	 */
	public function test_constructor_and_init_hooks( bool $is_frontend ): void {
		if ( ! $is_frontend ) {
			set_current_screen( 'some' );
		}

		$subject = new Form();

		self::assertSame( 8, has_action( 'wp_print_footer_scripts', [ $subject, 'dequeue_kadence_captcha_api' ] ) );

		self::assertSame( 9, has_action( 'wp_ajax_kb_process_ajax_submit', [ $subject, 'process_ajax' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_kb_process_ajax_submit', [ $subject, 'process_ajax' ] ) );

		if ( $is_frontend ) {
			self::assertSame( 10, has_filter( 'render_block', [ $subject, 'render_block' ] ) );
			self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		} else {
			self::assertFalse( has_filter( 'render_block', [ $subject, 'render_block' ] ) );
			self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		}
	}

	/**
	 * Data provider for dp_test_constructor_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_constructor_and_init_hooks(): array {
		return [
			'frontend' => [ true ],
			'backend'  => [ false ],
		];
	}

	/**
	 * Test render_block().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_render_block(): void {
		$form_id       = 5;
		$block_content = 'some block content';
		$block         = [
			'blockName' => 'some',
			'attrs'     => [
				'postID' => $form_id,
			],
		];
		$instance      = Mockery::mock( 'WP_Block' );

		$subject = new Form();

		self::assertSame( $block_content, $subject->render_block( $block_content, $block, $instance ) );

		$block['blockName'] = 'kadence/form';
		$hcap_form          = $this->get_hcap_form(
			[
				'id' => [
					'source'  => [ 'kadence-blocks/kadence-blocks.php' ],
					'form_id' => $form_id,
				],
			]
		);

		// Replace reCaptcha V2 + V3.
		$block_content = '<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">Some block content</div><input type="hidden" name="recaptcha_response" class="some-class">';
		$expected      = str_replace(
			'<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">Some block content</div><input type="hidden" name="recaptcha_response" class="some-class">',
			$hcap_form,
			$block_content
		);

		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );

		// No reCaptcha, add hCaptcha before submit.
		$block_content = '<div class="kadence-blocks-form-field kb-submit-field">Some block content</div>';
		$expected      = $hcap_form . $block_content;

		self::assertFalse( $this->get_protected_property( $subject, 'has_captcha' ) );
		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );
		self::assertTrue( $this->get_protected_property( $subject, 'has_captcha' ) );
	}

	/**
	 * Test process_ajax().
	 *
	 * @return void
	 */
	public function test_process_ajax(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$subject = new Form();

		$subject->process_ajax();
	}

	/**
	 * Test process_ajax() when not success.
	 *
	 * @param bool|null $result Result of \HCaptcha\Helpers\API::verify_request().
	 *
	 * @return void
	 * @dataProvider dp_test_process_ajax_when_not_success
	 */
	public function test_process_ajax_when_not_success( ?bool $result ): void {
		$hcaptcha_response = 'some response';
		$error_message     = 'The hCaptcha is invalid.';

		if ( null === $result ) {
			$error_message = 'Please complete the hCaptcha.';
		}

		$die_arr       = [];
		$expected_json = [
			'success' => false,
			'data'    => [
				'html'         => "<div class=\"kadence-blocks-form-message kadence-blocks-form-warning\">$error_message</div>",
				'console'      => 'hCaptcha Failed',
				'required'     => null,
				'headers_sent' => true,
			],
		];
		$expected      = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_verify_request( $hcaptcha_response, $result );

		if ( null === $result ) {
			unset( $_POST['h-captcha-response'] );
		}

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
		$subject->process_ajax();
		$json = ob_get_clean();

		$expected_json['data']['headers_sent'] = json_decode( $json, true )['data']['headers_sent'];

		self::assertSame( wp_json_encode( $expected_json ), $json );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Data provider for test_process_ajax_when_not_success().
	 *
	 * @return array
	 */
	public function dp_test_process_ajax_when_not_success(): array {
		return [
			'null'  => [ null ],
			'false' => [ false ],
		];
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$handle = 'hcaptcha-kadence';

		$subject = new Form();

		self::assertFalse( wp_script_is( $handle ) );

		$subject::enqueue_scripts();

		self::assertTrue( wp_script_is( $handle ) );
	}
}
