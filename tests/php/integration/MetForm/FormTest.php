<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\MetForm;

use Elementor\Widget_Base;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\MetForm\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use WP_REST_Request;

/**
 * Test Form class.
 *
 * @group metform
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST );

		wp_dequeue_script( 'hcaptcha-metform' );
		wp_deregister_script( 'hcaptcha-metform' );

		hcaptcha()->form_shown = false;

		parent::tearDown();
	}

	/**
	 * Test hooks.
	 *
	 * @return void
	 */
	public function test_hooks(): void {
		$subject = new Form();

		self::assertSame( 10, has_filter( 'elementor/widget/render_content', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'rest_request_before_callbacks', [ $subject, 'capture_form_id' ] ) );
		self::assertSame( 10, has_filter( 'mf_after_validation_check', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test adding hCaptcha.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$form_id   = 123;
		$content   = '<div class="mf-btn-wraper"><button type="submit">Submit</button></div>';
		$args      = [
			'action' => 'hcaptcha_metform',
			'name'   => 'hcaptcha_metform_nonce',
			'id'     => [
				'source'  => [ 'metform/metform.php' ],
				'form_id' => $form_id,
			],
		];
		$hcap_form = rawurlencode( $this->get_hcap_form( $args ) );
		$expected  = sprintf(
			'<div class="hcaptcha-metform-placeholder" data-hcaptcha-html="%1$s"></div>' . "\n" . '%2$s',
			esc_attr( $hcap_form ),
			$content
		);

		$other_widget = Mockery::mock( Widget_Base::class );
		$other_widget->shouldReceive( 'get_name' )->andReturn( 'mf-text' );

		$button_widget = Mockery::mock( Widget_Base::class );
		$button_widget->shouldReceive( 'get_name' )->andReturn( 'mf-button' );

		$subject = Mockery::mock( Form::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_form_id' )->once()->andReturn( $form_id );

		self::assertSame( $content, $subject->add_hcaptcha( $content, $other_widget ) );
		self::assertSame( $expected, $subject->add_hcaptcha( $content, $button_widget ) );
	}

	/**
	 * Test capturing the MetForm ID.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_capture_form_id(): void {
		$response = 'response';
		$subject  = new Form();
		$request  = new WP_REST_Request( 'POST', '/some/v1/route/123' );

		self::assertSame( $response, $subject->capture_form_id( $response, [], $request ) );
		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );

		$request = new WP_REST_Request( 'POST', '/metform/v1/entries/insert/456' );

		self::assertSame( $response, $subject->capture_form_id( $response, [], $request ) );
		self::assertSame( 456, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test successful verification.
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$form_id           = wp_insert_post(
			[
				'post_type'   => 'metform-form',
				'post_status' => 'publish',
				'post_title'  => 'Test MetForm',
			]
		);
		$hcaptcha_response = 'some response';
		$form_data         = [
			'h-captcha-response'     => $hcaptcha_response,
			'hcaptcha-widget-id'     => HCaptcha::widget_id_value(
				[
					'source'  => [ 'metform/metform.php' ],
					'form_id' => $form_id,
				]
			),
			'hcaptcha_metform_nonce' => wp_create_nonce( 'hcaptcha_metform' ),
			'email'                  => 'person@example.com',
		];
		$validation        = [
			'is_valid'  => true,
			'form_data' => $form_data,
			'file_data' => [],
		];
		$request           = new WP_REST_Request( 'POST', '/metform/v1/entries/insert/' . $form_id );

		$this->prepare_verify_request( $hcaptcha_response );

		$subject = Mockery::mock( Form::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_fields' )->once()->andReturn(
			[
				'email' => (object) [
					'mf_input_label' => 'Email',
					'mf_input_name'  => 'email',
					'widgetType'     => 'mf-email',
				],
			]
		);
		$subject->capture_form_id( null, [], $request );

		self::assertSame( $validation, $subject->verify( $validation ) );
	}

	/**
	 * Test failed verification.
	 *
	 * @return void
	 */
	public function test_verify_failed(): void {
		$form_id    = 123;
		$form_data  = [
			'h-captcha-response'     => 'some response',
			'hcaptcha-widget-id'     => HCaptcha::widget_id_value(
				[
					'source'  => [ 'WordPress' ],
					'form_id' => $form_id,
				]
			),
			'hcaptcha_metform_nonce' => wp_create_nonce( 'hcaptcha_metform' ),
		];
		$validation = [
			'is_valid'  => true,
			'form_data' => $form_data,
		];
		$request    = new WP_REST_Request( 'POST', '/metform/v1/entries/insert/' . $form_id );
		$subject    = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_fields' )->once()->andReturn( [] );
		$subject->capture_form_id( null, [], $request );

		$actual = $subject->verify( $validation );

		self::assertFalse( $actual['is_valid'] );
		self::assertSame( 'Bad hCaptcha signature!', $actual['message'] );
	}

	/**
	 * Test preserving an existing validation failure.
	 *
	 * @return void
	 */
	public function test_verify_existing_failure(): void {
		$validation = [
			'is_valid' => false,
			'message'  => 'MetForm validation failed.',
		];
		$subject    = new Form();

		self::assertSame( $validation, $subject->verify( $validation ) );
	}

	/**
	 * Test get_entry() and get_data().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_entry_and_get_data(): void {
		$form_id   = wp_insert_post(
			[
				'post_type'   => 'metform-form',
				'post_status' => 'publish',
				'post_title'  => 'Entry processing test',
			]
		);
		$fields    = [
			'first-name' => (object) [
				'mf_input_label' => 'First name',
				'mf_input_name'  => 'first-name',
				'widgetType'     => 'mf-listing-fname',
			],
			'last-name'  => (object) [
				'mf_input_label' => 'Last name',
				'mf_input_name'  => 'last-name',
				'widgetType'     => 'mf-listing-lname',
			],
			'email'      => (object) [
				'mf_input_label' => 'Email address',
				'mf_input_name'  => 'email',
				'widgetType'     => 'mf-email',
			],
			'message'    => (object) [
				'mf_input_label' => 'Message',
				'mf_input_name'  => 'message',
				'widgetType'     => 'mf-textarea',
			],
			'choice'     => (object) [
				'mf_input_label' => '',
				'mf_input_name'  => 'choice',
				'widgetType'     => 'mf-checkbox',
			],
			'empty'      => (object) [
				'mf_input_label' => 'Empty',
				'mf_input_name'  => 'empty',
				'widgetType'     => 'mf-text',
			],
		];
		$form_data = [
			'first-name'             => 'John',
			'last-name'              => 'Doe',
			'email'                  => 'john@example.com',
			'message'                => 'Hello world',
			'choice'                 => [ 'One', 'Two' ],
			'empty'                  => '',
			'form_nonce'             => 'metform-nonce',
			'hcaptcha_metform_nonce' => 'hcaptcha-nonce',
			'h-captcha-response'     => 'captcha-response',
		];

		$entries_action = Mockery::mock();
		$entries_action->shouldReceive( 'get_fields' )->once()->with( $form_id )->andReturn( $fields );

		$entries_action_class = Mockery::mock( 'alias:MetForm\\Core\\Entries\\Action' );
		$entries_action_class->shouldReceive( 'instance' )->once()->andReturn( $entries_action );

		$subject = new Form();
		$request = new WP_REST_Request( 'POST', '/metform/v1/entries/insert/' . $form_id );

		$subject->capture_form_id( null, [], $request );

		$method = $this->set_method_accessibility( $subject, 'get_entry' );
		$entry  = $method->invoke( $subject, $form_data );

		self::assertSame( 'hcaptcha_metform_nonce', $entry['nonce_name'] );
		self::assertSame( 'hcaptcha_metform', $entry['nonce_action'] );
		self::assertSame( 'captcha-response', $entry['h-captcha-response'] );
		self::assertSame( get_post( $form_id )->post_modified_gmt, $entry['form_date_gmt'] );
		self::assertSame( $form_data, $entry['post_data'] );
		self::assertSame(
			[
				'First name'    => 'John',
				'Last name'     => 'Doe',
				'email'         => 'john@example.com',
				'Email address' => 'john@example.com',
				'Message'       => 'Hello world',
				'choice'        => 'One Two',
				'name'          => 'John Doe',
			],
			$entry['data']
		);
		self::assertSame(
			[
				'source'  => [ 'metform/metform.php' ],
				'form_id' => $form_id,
			],
			$entry['expected_id']
		);
	}

	/**
	 * Test script enqueueing.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		$subject->enqueue_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-metform' ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();
		self::assertTrue( wp_script_is( 'hcaptcha-metform' ) );
		self::assertStringEndsWith(
			'/assets/js/hcaptcha-metform.min.js',
			wp_scripts()->registered['hcaptcha-metform']->src
		);
	}
}
