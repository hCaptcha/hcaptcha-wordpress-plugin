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

/**
 * Test Kadence Form.
 *
 * @group kadence
 * @group kadence-form
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Teardown test.
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
			self::assertSame( 10, has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );
		} else {
			self::assertFalse( has_filter( 'render_block', [ $subject, 'render_block' ] ) );
			self::assertFalse( has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );
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
		$block_content      = '<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">Some block content</div>';

		self::assertSame( $block_content, $subject->render_block( $block_content, $block, $instance ) );

		$block_content = 'Some content with recaptcha_response';

		self::assertSame( $block_content, $subject->render_block( $block_content, $block, $instance ) );

		$block_content = '<div class="kadence-blocks-form-field kb-submit-field">Some block content</div>';
		$hcap_form     = $this->get_hcap_form(
			[
				'id' => [
					'source'  => [ 'kadence-blocks/kadence-blocks.php' ],
					'form_id' => $form_id,
				],
			]
		);
		$expected      = $hcap_form . $block_content;

		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );
	}

	/**
	 * Test process_ajax().
	 *
	 * @return void
	 */
	public function test_process_ajax(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'has_recaptcha' )->andReturn( false );

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

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'has_recaptcha' )->andReturn( false );

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
	 * Test process_ajax() when has recaptcha.
	 *
	 * @return void
	 */
	public function test_process_ajax_when_has_recaptcha(): void {
		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'has_recaptcha' )->andReturn( true );

		$subject->process_ajax();
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

	/**
	 * Test has_recaptcha().
	 *
	 * @return void
	 */
	public function test_has_recaptcha(): void {
		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->has_recaptcha() );

		$postarr = [
			'post_content' => 'Some post content',
		];
		$post_id = wp_insert_post( $postarr );
		$form_id = "{$post_id}_7e3e65-e6";

		$_POST['_kb_form_id']      = $form_id;
		$_POST['_kb_form_post_id'] = $post_id;

		self::assertFalse( $subject->has_recaptcha() );

		$post_content = "<!-- wp:kadence/form {\"uniqueID\":\"{$post_id}_7e3e65-e6\",\"postID\":\"$post_id\",\"fields\":[{\"label\":\"Name\",\"showLabel\":true,\"placeholder\":\"\",\"default\":\"\",\"description\":\"\",\"rows\":4,\"options\":[{\"value\":\"\",\"label\":\"\"}],\"multiSelect\":false,\"inline\":false,\"showLink\":false,\"min\":\"\",\"max\":\"\",\"type\":\"text\",\"required\":false,\"width\":[\"100\",\"\",\"\"],\"auto\":\"\",\"errorMessage\":\"\",\"requiredMessage\":\"\",\"slug\":\"\",\"ariaLabel\":\"\"},{\"label\":\"Email\",\"showLabel\":true,\"placeholder\":\"\",\"default\":\"\",\"description\":\"\",\"rows\":4,\"options\":[{\"value\":\"\",\"label\":\"\"}],\"multiSelect\":false,\"inline\":false,\"showLink\":false,\"min\":\"\",\"max\":\"\",\"type\":\"email\",\"required\":true,\"width\":[\"100\",\"\",\"\"],\"auto\":\"\",\"errorMessage\":\"\",\"requiredMessage\":\"\",\"slug\":\"\",\"ariaLabel\":\"\"},{\"label\":\"Message\",\"showLabel\":true,\"placeholder\":\"\",\"default\":\"\",\"description\":\"\",\"rows\":4,\"options\":[{\"value\":\"\",\"label\":\"\"}],\"multiSelect\":false,\"inline\":false,\"showLink\":false,\"min\":\"\",\"max\":\"\",\"type\":\"textarea\",\"required\":false,\"width\":[\"100\",\"\",\"\"],\"auto\":\"\",\"errorMessage\":\"\",\"requiredMessage\":\"\",\"slug\":\"\",\"ariaLabel\":\"\"}],\"recaptcha\":true,\"recaptchaVersion\":\"v2\"} -->
<div class=\"wp-block-kadence-form kadence-form-{$post_id}_7e3e65-e6 kb-form-wrap\"><form class=\"kb-form\" action=\"\" method=\"post\"><div class=\"kadence-blocks-form-field kb-field-desk-width-100 kb-input-size-standard\"><label for=\"kb_field_{$post_id}_7e3e65-e6_0\">Name</label><input name=\"kb_field_0\" id=\"kb_field_{$post_id}_7e3e65-e6_0\" data-label=\"Name\" type=\"text\" placeholder=\"\" value=\"\" data-type=\"text\" class=\"kb-field kb-text-style-field kb-text-field kb-field-0\"/></div><div class=\"kadence-blocks-form-field kb-form-field-1 kb-field-desk-width-100 kb-input-size-standard\"><label for=\"kb_field_{$post_id}_7e3e65-e6_1\">Email<span class=\"required\">*</span></label><input name=\"kb_field_1\" id=\"kb_field_{$post_id}_7e3e65-e6_1\" data-label=\"Email\" type=\"email\" placeholder=\"\" value=\"\" data-type=\"email\" class=\"kb-field kb-text-style-field kb-email-field kb-field-1\" data-required=\"yes\"/></div><div class=\"kadence-blocks-form-field kb-form-field-2 kb-field-desk-width-100 kb-input-size-standard\"><label for=\"kb_field_{$post_id}_7e3e65-e6_2\">Message</label><textarea name=\"kb_field_2\" id=\"kb_field_{$post_id}_7e3e65-e6_2\" data-label=\"Message\" type=\"textarea\" placeholder=\"\" data-type=\"textarea\" class=\"kb-field kb-text-style-field kb-textarea-field kb-field-2\" rows=\"4\"></textarea></div><input type=\"hidden\" name=\"_kb_form_id\" value=\"{$post_id}_7e3e65-e6\"/><input type=\"hidden\" name=\"_kb_form_post_id\" value=\"$post_id\"/><input type=\"hidden\" name=\"action\" value=\"kb_process_ajax_submit\"/><div class=\"kadence-blocks-form-field google-recaptcha-checkout-wrap\"><p id=\"kb-container-g-recaptcha\" class=\"google-recaptcha-container\"><span id=\"kb_recaptcha_{$post_id}_7e3e65-e6\" class=\"kadence-blocks-g-recaptcha-v2 g-recaptcha kb_recaptcha_{$post_id}_7e3e65-e6\" style=\"display:inline-block\"></span></p></div><input class=\"kadence-blocks-field verify\" type=\"text\" name=\"_kb_verify_email\" autocomplete=\"off\" aria-hidden=\"true\" placeholder=\"Email\" tabindex=\"-1\"/><div class=\"kadence-blocks-form-field kb-submit-field kb-field-desk-width-100\"><button class=\"kb-forms-submit button kb-button-size-standard kb-button-width-auto\">Submit</button></div></form></div>
<!-- /wp:kadence/form -->

<!-- wp:kadence/advanced-form {\"id\":4917,\"uniqueID\":\"{$post_id}_6917f3-45\"} /-->";

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $post_content,
			]
		);

		self::assertTrue( $subject->has_recaptcha() );
	}
}
