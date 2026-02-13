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

namespace HCaptcha\Tests\Integration\Otter;

use HCaptcha\Otter\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request;
use WP_Block;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Test Otter Form.
 *
 * @group otter
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Form();

		// Check if the filters and actions are added.
		self::assertSame(
			10,
			has_filter( 'option_themeisle_google_captcha_api_site_key', [ $subject, 'replace_site_key' ] )
		);
		self::assertSame(
			99,
			has_filter( 'default_option_themeisle_google_captcha_api_site_key', [ $subject, 'replace_site_key' ] )
		);
		self::assertSame( 10, has_filter( 'render_block', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'otter_form_anti_spam_validation', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test replace_site_key().
	 *
	 * @return void
	 */
	public function test_replace_site_key(): void {
		$subject = new Form();

		// Check if the site key is replaced with an empty string.
		self::assertSame( '', $subject->replace_site_key() );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$form_id   = 'fc0b7800';
		$hcap_form = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_otter',
				'name'   => 'hcaptcha_otter_nonce',
				'id'     => [
					'source'  => [ 'otter-blocks/otter-blocks.php' ],
					'form_id' => $form_id,
				],
			]
		);

		$subject = new Form();

		$block_content = 'Some content';
		$block         = [ 'blockName' => 'some/some' ];
		$instance      = Mockery::mock( WP_Block::class );

		self::assertSame( $block_content, $subject->add_hcaptcha( $block_content, $block, $instance ) );

		$block_content = <<<HTML
<div id="wp-block-themeisle-blocks-form-$form_id" class="wp-block-themeisle-blocks-form has-captcha">
	<form>
		Some form inputs
		<div class="wp-block-button">
			<button class="wp-block-button__link" type="submit">Submit</button>
		</div>
	</form>
</div>
HTML;
		$block         = [ 'blockName' => 'themeisle-blocks/form' ];
		$expected      = <<<HTML
<div id="wp-block-themeisle-blocks-form-$form_id" class="wp-block-themeisle-blocks-form ">
	<form>
		Some form inputs
		$hcap_form
<div class="wp-block-button">
			<button class="wp-block-button__link" type="submit">Submit</button>
		</div>
	</form>
</div>
HTML;

		// Check if hCaptcha is added to the form block.
		self::assertSame( $expected, $subject->add_hcaptcha( $block_content, $block, $instance ) );
	}

	/**
	 * Test verify().
	 *
	 * @param bool $verified Verified or not.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 */
	public function test_verify( bool $verified ): void {
		$subject = new Form();

		// Do not verify if the form data is null.
		self::assertNull( $subject->verify( null ) );

		// Do not verify if the form has errors.
		$form_data_request = Mockery::mock( Form_Data_Request::class );

		$form_data_request->shouldReceive( 'has_error' )->andReturn( true );

		self::assertSame( $form_data_request, $subject->verify( $form_data_request ) );

		// Verify the hCaptcha.
		$form_data_request = Mockery::mock( Form_Data_Request::class );

		$form_data_request->shouldReceive( 'has_error' )->andReturn( false );

		$action = 'hcaptcha_otter';
		$nonce  = 'hcaptcha_otter_nonce';

		$this->prepare_verify_post( $nonce, $action, $verified );

		$form_data_arr = [
			'form_data' => [
				'h-captcha-response'   => 'some response',
				'hcaptcha-widget-id'   => 'some widget id',
				'hcaptcha_otter_nonce' => $_POST[ $nonce ], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				'hcap_fst_token'       => 'some token',
				'hcap_hp_test'         => '',
				'hcap_hp_sig'          => 'some signature',
			],
		];

		$form_data_request->shouldReceive( 'dump_data' )->with()->andReturn( $form_data_arr );

		if ( $verified ) {
			$form_data_request->shouldReceive( 'set_error' )->never();
		} else {
			$form_data_request->shouldReceive( 'set_error' )->once()->with( 'fail', 'The hCaptcha is invalid.' );
		}

		$result = $subject->verify( $form_data_request );

		self::assertInstanceOf( Form_Data_Request::class, $result );
	}

	/**
	 * Data provider for test_verify().
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			[ 'not verified' => false ],
			[ 'verified' => true ],
		];
	}

	/**
	 * Test get_entry() and get_data().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_entry_and_data(): void {
		$subject   = new Form();
		$post_id   = wp_insert_post(
			[
				'post_title'  => 'Otter Test Form',
				'post_status' => 'publish',
			]
		);
		$post      = get_post( $post_id );
		$inputs    = [
			[
				'label' => 'Name',
				'value' => 'Jane Doe',
				'type'  => 'text',
			],
			[
				'label' => 'Email',
				'value' => 'jane@example.com',
				'type'  => 'email',
			],
			[
				'label' => 'Message',
				'value' => [ 'Hello', 'world' ],
				'type'  => 'textarea',
			],
			[
				'label' => '',
				'id'    => 'field-id',
				'value' => 'Field value',
			],
			[
				'label' => 'Empty',
				'value' => '',
			],
		];
		$post_data = [
			'payload'            => [
				'formInputsData' => $inputs,
				'postId'         => $post_id,
			],
			'h-captcha-response' => 'token',
		];

		$method = $this->set_method_accessibility( $subject, 'get_entry' );
		$entry  = $method->invoke( $subject, $post_data );

		self::assertSame( 'hcaptcha_otter_nonce', $entry['nonce_name'] );
		self::assertSame( 'hcaptcha_otter', $entry['nonce_action'] );
		self::assertSame( 'token', $entry['h-captcha-response'] );
		self::assertSame( $post->post_modified_gmt, $entry['form_date_gmt'] );
		self::assertSame( $post_data, $entry['post_data'] );
		self::assertSame( 'jane@example.com', $entry['data']['email'] );
		self::assertSame( 'Jane Doe', $entry['data']['name'] );
		self::assertSame( 'Jane Doe', $entry['data']['Name'] );
		self::assertSame( 'Hello world', $entry['data']['Message'] );
		self::assertSame( 'Field value', $entry['data']['field-id'] );
		self::assertArrayNotHasKey( 'Empty', $entry['data'] );
	}

	/**
	 * Test filter_response().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_filter_response(): void {
		$subject  = new Form();
		$response = new WP_REST_Response( [ 'success' => false ] );
		$request  = new WP_REST_Request( 'POST', '/otter/v1/form/frontend' );

		$this->set_protected_property( $subject, 'error_code', 'fail' );
		$this->set_protected_property( $subject, 'error_message', 'The hCaptcha is invalid.' );

		$result = $subject->filter_response( $response, [], $request );
		$data   = $result->get_data();

		self::assertSame( 'fail', $data['code'] );
		self::assertSame( 'The hCaptcha is invalid.', $data['displayError'] );

		$request_other  = new WP_REST_Request( 'POST', '/otter/v1/other' );
		$response_other = new WP_REST_Response( [ 'success' => true ] );
		$result_other   = $subject->filter_response( $response_other, [], $request_other );

		self::assertSame( [ 'success' => true ], $result_other->get_data() );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 */
	public function test_add_type_module(): void {
		$subject = new Form();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="/assets/js/hcaptcha-otter.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'other-handle', '' ) );
		self::assertStringContainsString( 'type="module"', $subject->add_type_module( $tag, 'hcaptcha-otter', '' ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		// Form isn't shown.
		self::assertFalse( wp_script_is( 'hcaptcha-otter' ) );

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-otter' ) );

		// Form is shown.
		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		// Check if the script is enqueued.
		self::assertTrue( wp_script_is( 'hcaptcha-otter' ) );
	}
}
