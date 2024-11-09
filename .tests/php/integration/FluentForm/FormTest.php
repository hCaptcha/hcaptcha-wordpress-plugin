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

namespace HCaptcha\Tests\Integration\FluentForm;

use FluentForm\App\Models\Form as FluentForm;
use HCaptcha\FluentForm\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use stdClass;
use tad\FunctionMocker\FunctionMocker;
use FluentForm\App\Modules\Form\FormFieldsParser;

/**
 * Test FluentForm.
 *
 * @group fluentform
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Form();

		self::assertSame(
			10,
			has_action( 'fluentform/rendering_field_html_hcaptcha', [ $subject, 'render_field_hcaptcha' ] )
		);
		self::assertSame(
			9,
			has_action( 'fluentform/render_item_submit_button', [ $subject, 'add_hcaptcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'fluentform/validation_errors', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_filter( 'fluentform/rendering_form', [ $subject, 'fluentform_rendering_form_filter' ] )
		);
		self::assertSame(
			10,
			has_filter( 'fluentform/has_hcaptcha', [ $subject, 'fluentform_has_hcaptcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] )
		);
		self::assertSame(
			9,
			has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] )
		);
		self::assertSame(
			10,
			has_action( 'admin_enqueue_scripts', [ $subject, 'admin_enqueue_scripts' ] )
		);
		self::assertSame(
			20,
			has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
		);
	}

	/**
	 * Test render_field_hcaptcha().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_render_field_hcaptcha(): void {
		$form_id = 1;
		$form    = (object) [
			'id' => $form_id,
		];

		$subject = new Form();

		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );
		self::assertSame( $this->get_get_hcaptcha_wrapped( $form_id ), $subject->render_field_hcaptcha( '', [], $form ) );
		self::assertSame( $form_id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test add_hcaptcha().
	 */
	public function test_add_hcaptcha(): void {
		hcaptcha()->init_hooks();

		$form_id = 1;
		$form    = (object) [
			'id' => $form_id,
		];

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive( 'has_own_hcaptcha' )->with( $form )->andReturn( false );

		$expected = $this->get_get_hcaptcha_wrapped( $form_id );

		ob_start();
		$mock->add_hcaptcha( [], $form );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() with own captcha.
	 */
	public function test_add_captcha_with_own_captcha(): void {
		hcaptcha()->init_hooks();

		$form = (object) [];

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive( 'has_own_hcaptcha' )->with( $form )->andReturn( true );

		ob_start();
		$mock->add_hcaptcha( [], $form );

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test verify() with bad response.
	 *
	 * @return void
	 */
	public function test_verify_no_success(): void {
		$errors = [
			'some_error'         => 'Some error description',
			'h-captcha-response' => [ 'Please complete the hCaptcha.' ],
		];
		$data   = [];
		$form   = Mockery::mock( FluentForm::class );
		$fields = [];

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive( 'has_own_hcaptcha' )->with( $form )->andReturn( true );

		self::assertSame( $errors, $mock->verify( $errors, $data, $form, $fields ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$errors                         = [
			'some_error' => 'Some error description',
		];
		$data                           = [];
		$form                           = Mockery::mock( FluentForm::class );
		$fields                         = [];
		$response                       = 'some response';
		$expected                       = $errors;
		$expected['h-captcha-response'] = [ 'Please complete the hCaptcha.' ];

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive( 'has_own_hcaptcha' )->with( $form )->andReturn( false );

		$this->prepare_hcaptcha_request_verify( $response, false );

		self::assertSame( $expected, $mock->verify( $errors, $data, $form, $fields ) );
	}

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 */
	public function test_print_hcaptcha_scripts(): void {
		wp_register_script( 'hcaptcha', 'https://example.com/hcaptcha.js', [], '1.0.0', true );
		wp_enqueue_script( 'hcaptcha' );

		$subject = new Form();

		self::assertTrue( wp_script_is( 'hcaptcha' ) );
		self::assertTrue( wp_script_is( 'hcaptcha', 'registered' ) );

		$result = $subject->print_hcaptcha_scripts( false );
		self::assertTrue( $result );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );
		self::assertFalse( wp_script_is( 'hcaptcha', 'registered' ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		global $wp_scripts;

		$handle = 'hcaptcha-fluentform';

		self::assertFalse( wp_script_is( $handle ) );

		$subject = new Form();

		// Without conversational form script.
		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( $handle ) );

		// With conversational form script.
		$fluent_forms_conversational_source = 'https://example.com/script.js';
		$fluent_forms_conversational_script = 'fluent_forms_conversational_form';
		$fluent_forms_conversational_object = 'FluentFormsConversationalForm';
		$fluent_forms_conversational_params = [
			'some key' => 'some value',
		];
		$fluent_forms_conversational_json   = wp_json_encode( $fluent_forms_conversational_params );
		$expected_fluent_forms_extra        = <<<HTML
<script type="text/javascript" id="$fluent_forms_conversational_script-js-extra">
/* <![CDATA[ */
var $fluent_forms_conversational_object = $fluent_forms_conversational_json;
/* ]]> */
</script>
HTML;

		wp_enqueue_script( $fluent_forms_conversational_script, $fluent_forms_conversational_source, [], '1.0.0', true );
		wp_localize_script(
			$fluent_forms_conversational_script,
			$fluent_forms_conversational_object,
			$fluent_forms_conversational_params
		);

		$form_id        = 1;
		$params         = [
			'id'  => $fluent_forms_conversational_script,
			'url' => $wp_scripts->registered[ $fluent_forms_conversational_script ]->src,
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaFluentFormObject = ' . json_encode( $params ) . ';',
		];
		$args           = [
			'action' => 'hcaptcha_fluentform',
			'name'   => 'hcaptcha_fluentform_nonce',
			'id'     => [
				'source'  => [ 'fluentform/fluentform.php' ],
				'form_id' => $form_id,
			],
		];
		$hcap_form      = HCaptcha::form( $args );
		$hcap_form      = str_replace(
			[
				'class="h-captcha"',
				'class="hcaptcha-widget-id"',
			],
			[
				'class="h-captcha-hidden" style="display: none;"',
				'class="h-captcha-hidden hcaptcha-widget-id"',
			],
			$hcap_form
		);

		$this->set_protected_property( $subject, 'form_id', $form_id );

		ob_start();
		$subject->enqueue_scripts();
		self::assertSame( $expected_fluent_forms_extra . "\n" . $hcap_form, ob_get_clean() );

		self::assertTrue( wp_script_is( $handle ) );
		$script = wp_scripts()->registered[ $handle ];
		self::assertSame( $expected_extra, $script->extra );

		self::assertFalse( wp_script_is( $fluent_forms_conversational_script ) );
		self::assertFalse( wp_script_is( $fluent_forms_conversational_script, 'registered' ) );
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts(): void {
		$admin_handle   = 'admin-fluentform';
		$notice         = HCaptcha::get_hcaptcha_plugin_notice();
		$params         = [
			'noticeLabel'       => $notice['label'],
			'noticeDescription' => html_entity_decode( $notice['description'] ),
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaFluentFormObject = ' . json_encode( $params ) . ';',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		// Not on fluent forms admin page.
		self::assertFalse( wp_script_is( $admin_handle ) );

		$subject->admin_enqueue_scripts();

		self::assertFalse( wp_script_is( $admin_handle ) );

		// On fluent forms admin page.
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_fluent_forms_admin_page' )->andReturn( true );

		$subject->admin_enqueue_scripts();

		self::assertTrue( wp_script_is( $admin_handle ) );

		$script = wp_scripts()->registered[ $admin_handle ];

		self::assertSame( HCAPTCHA_URL . '/assets/js/admin-fluentform.min.js', $script->src );
		self::assertSame( [ 'jquery' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );

		$style = wp_styles()->registered[ $admin_handle ];

		self::assertSame( HCAPTCHA_URL . '/assets/css/admin-fluentform.min.css', $style->src );
		self::assertSame( [], $style->deps );
		self::assertSame( HCAPTCHA_VERSION, $style->ver );
	}

	/**
	 * Test is_fluent_forms_admin_page().
	 *
	 * @return void
	 * @noinspection DisconnectedForeachInstructionInspection
	 */
	public function test_is_fluent_forms_admin_page(): void {
		$fluent_forms_admin_pages = [
			'fluent-forms_page_fluent_forms_settings',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Not admin page.
		self::assertFalse( $subject->is_fluent_forms_admin_page() );

		// Not Formidable admin page.
		set_current_screen( 'some' );

		self::assertFalse( $subject->is_fluent_forms_admin_page() );

		// Success path. Formidable admin pages.
		foreach ( $fluent_forms_admin_pages as $page ) {
			set_current_screen( $page );

			self::assertTrue( $subject->is_fluent_forms_admin_page() );
		}
	}

	/**
	 * Test fluentform_rendering_form_filter().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_fluentform_rendering_form_filter(): void {
		$subject = new Form();

		// Not a form.
		$form = 'some value';

		self::assertSame( $form, $subject->fluentform_rendering_form_filter( $form ) );

		// Form with ID.
		$form     = new stdClass();
		$form->id = '123';

		self::assertSame( $form, $subject->fluentform_rendering_form_filter( $form ) );
		self::assertSame( (int) $form->id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test fluentform_has_hcaptcha().
	 *
	 * @return void
	 */
	public function test_fluentform_has_hcaptcha(): void {
		$subject = new Form();

		self::assertFalse( has_filter( 'pre_http_request', [ $subject, 'pre_http_request' ] ) );

		self::assertFalse( $subject->fluentform_has_hcaptcha() );

		self::assertSame( 10, has_filter( 'pre_http_request', [ $subject, 'pre_http_request' ] ) );
	}

	/**
	 * Test pre_http_request().
	 *
	 * @return void
	 */
	public function test_pre_http_request(): void {
		$response    = [ 'some response' ];
		$parsed_args = [ 'some parsed args' ];
		$url         = 'https://example.com/some-url';

		$subject = new Form();

		// Wrong url.
		self::assertSame( $response, $subject->pre_http_request( $response, $parsed_args, $url ) );

		// Correct url.
		$url      = hcaptcha()->get_verify_url();
		$expected = [
			'body'     => '{"success":true}',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		];

		self::assertSame( $expected, $subject->pre_http_request( false, [], $url ) );
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

		$expected = <<<CSS
	.frm-fluent-form .h-captcha {
		line-height: 0;
		margin-bottom: 0;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Form();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test has_own_hcaptcha().
	 *
	 * @param bool $has Has own hCaptcha.
	 *
	 * @return void
	 * @dataProvider dp_has_own_hcaptcha
	 */
	public function test_has_own_hcaptcha( bool $has ): void {
		$form = new stdClass();

		FunctionMocker::replace(
			'FluentForm\App\Modules\Form\FormFieldsParser::resetData',
			static function () {
				// Do nothing.
			}
		);

		FunctionMocker::replace(
			'FluentForm\App\Modules\Form\FormFieldsParser::hasElement',
			static function ( $a_form, $element ) use ( $form, $has ) {
				if ( $form === $a_form && 'hcaptcha' === $element ) {
					return $has;
				}

				return ! $has;
			}
		);

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( $has, $subject->has_own_hcaptcha( $form ) );
	}

	/**
	 * Data provider for test_has_own_hcaptcha().
	 *
	 * @return array
	 */
	public function dp_has_own_hcaptcha(): array {
		return [
			'has'           => [ true ],
			'does not have' => [ false ],
		];
	}

	/**
	 * Get hCaptcha form wrapped.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	private function get_get_hcaptcha_wrapped( int $form_id ): string {
		$args = [
			'action' => 'hcaptcha_fluentform',
			'name'   => 'hcaptcha_fluentform_nonce',
			'id'     => [
				'source'  => [ 'fluentform/fluentform.php' ],
				'form_id' => $form_id,
			],
		];

		$hcap_form = HCaptcha::form( $args );

		ob_start();

		?>
		<div class="ff-el-group">
			<div class="ff-el-input--content">
				<div data-fluent_id="1" name="h-captcha-response">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $hcap_form;
					?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}
