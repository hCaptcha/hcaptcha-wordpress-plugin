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
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;
use HCaptcha\FluentForm\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use stdClass;
use tad\FunctionMocker\FunctionMocker;
use WP_User;

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
			has_filter( 'pre_option', [ $subject, 'pre_option' ] )
		);
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
			0,
			has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] )
		);
		self::assertSame(
			9,
			has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
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
	 * Test pre_option().
	 *
	 * @return void
	 */
	public function test_pre_option(): void {
		$pre_option    = 'some_pre_option_value';
		$default_value = 'some_default_value';
		$site_key      = 'some_site_key';
		$secret_key    = 'some_secret_key';

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $site_key,
				'secret_key' => $secret_key,
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Form();

		// Some option.
		self::assertSame( $pre_option, $subject->pre_option( $pre_option, 'some_option', $default_value ) );

		$expected = [
			'siteKey'   => $site_key,
			'secretKey' => $secret_key,
		];

		// Details option.
		self::assertSame( $expected, $subject->pre_option( $pre_option, '_fluentform_hCaptcha_details', $default_value ) );

		// Status option.
		self::assertSame( '1', $subject->pre_option( $pre_option, '_fluentform_hCaptcha_keys_status', $default_value ) );
	}

	/**
	 * Test render_field_hcaptcha().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_render_field_hcaptcha(): void {
		$form_id   = 1;
		$form      = (object) [
			'id' => $form_id,
		];
		$hcap_form = $this->get_hcaptcha( $form_id );
		$html      = "<div class='ff-el-group'>
	<div class='ff-el-input--content'>
		<div data-fluent_id='3' name='h-captcha-response'>
			<div
					data-sitekey='10000000-ffff-ffff-ffff-000000000001'
					id='fluentform-hcaptcha-3-1'
					class='ff-el-hcaptcha h-captcha'>
			</div>
		</div>
	</div>
</div>";
		$expected  = "<div class='ff-el-group'>
	<div class='ff-el-input--content ff-el-input--hcaptcha'>
		<div data-fluent_id='3' >
			$hcap_form
		</div>
	</div>
</div>";

		$subject = new Form();

		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );
		self::assertSame( $expected, $subject->render_field_hcaptcha( $html, [], $form ) );
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
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$errors    = [
			'some_error' => 'Some error description',
		];
		$response  = 'some response';
		$widget_id = 'some-widget-id';
		$data      = [
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => $widget_id,
		];
		$fields    = [];
		$form      = Mockery::mock( FluentForm::class );

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$this->prepare_verify_request( $response );

		//phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$post_data = [
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => $widget_id,
			'hcap_hp_sig'        => $_POST['hcap_hp_sig'],
			'hcap_fst_token'     => $_POST['hcap_fst_token'],
			'hcap_hp_test'       => $_POST['hcap_hp_test'],
		];
		//phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$_POST['data'] = http_build_query( $post_data );

		self::assertSame( $errors, $mock->verify( $errors, $data, $form, $fields ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		$errors     = [
			'some_error' => 'Some error description',
		];
		$response   = 'some response';
		$widget_id  = 'some-widget-id';
		$data       = [
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => $widget_id,
		];
		$expected   = [
			'some_error'         => 'Some error description',
			'h-captcha-response' => [ 'The hCaptcha is invalid.' ],
		];
		$fields     = [];
		$attributes = [
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'form_fields' => json_encode(
				[
					'fields'       => [],
					'submitButton' => [],
				]
			),
		];

		$form = Mockery::mock( FluentForm::class );
		$form->shouldReceive( 'getAttributes' )->with()->andReturn( $attributes );

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$this->prepare_verify_request( $response, false );

		//phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$post_data = [
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => $widget_id,
			'hcap_hp_sig'        => $_POST['hcap_hp_sig'],
			'hcap_fst_token'     => $_POST['hcap_fst_token'],
			'hcap_hp_test'       => $_POST['hcap_hp_test'],
		];
		//phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$_POST['data'] = http_build_query( $post_data );

		self::assertSame( $expected, $mock->verify( $errors, $data, $form, $fields ) );
	}

	/**
	 * Test verify() when a multistep form is not verified.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_multi_step_not_verified(): void {
		$errors    = [
			'some_error' => 'Some error description',
		];
		$response  = 'some response';
		$widget_id = 'some-widget-id';
		$data      = [
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => $widget_id,
		];
		$expected  = [
			'some_error'         => 'Some error description',
			'h-captcha-response' => [ 'The hCaptcha is invalid.' ],
		];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$expected_json    = json_encode(
			[
				'success' => false,
				'data'    => $expected,
			]
		);
		$fields           = [];
		$die_arr          = [];
		$expected_die_arr = [
			'',
			'',
			[
				'response' => null,
			],
		];
		$attributes       = [
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'form_fields' => json_encode(
				[
					'fields'       => [],
					'submitButton' => [],
					'stepsWrapper' => [],
				]
			),
		];

		$form = Mockery::mock( FluentForm::class );
		$form->shouldReceive( 'getAttributes' )->with()->andReturn( $attributes );

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$this->prepare_verify_request( $response, false );

		//phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$post_data = [
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => $widget_id,
			'hcap_hp_sig'        => $_POST['hcap_hp_sig'],
			'hcap_fst_token'     => $_POST['hcap_fst_token'],
			'hcap_hp_test'       => $_POST['hcap_hp_test'],
		];
		//phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$_POST['data'] = http_build_query( $post_data );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		ob_start();
		self::assertSame( $expected, $mock->verify( $errors, $data, $form, $fields ) );
		self::assertSame( $expected_json, ob_get_clean() );
		self::assertSame( $expected_die_arr, $die_arr );
	}

	/**
	 * Test verify() a login form.
	 *
	 * @param bool $password_ok             Password is OK.
	 * @param bool $is_login_limit_exceeded Whether the login limit is exceeded.
	 *
	 * @return void
	 * @dataProvider dp_test_verify_login_form
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_login_form( bool $password_ok, bool $is_login_limit_exceeded ): void {
		$errors    = [
			'some_error' => 'Some error description',
		];
		$response  = 'some response';
		$widget_id = 'some-widget-id';
		$user      = get_user_by( 'id', 1 );
		$email     = $user->user_email;
		$password  = 'some password';
		$data      = [
			'email'    => $email,
			'password' => $password,
		];
		$form      = Mockery::mock( FluentForm::class );
		$fields    = [];
		$die_arr   = [];
		$expected  = [
			'',
			'',
			[
				'response' => null,
			],
		];

		$array_helper = Mockery::mock( 'alias:' . ArrayHelper::class );

		$array_helper->shouldReceive( 'get' )->andReturnUsing(
			static function ( $data, $key ) {
				return $data[ $key ];
			}
		);

		add_filter(
			'check_password',
			static function () use ( $password_ok ) {
				return $password_ok;
			}
		);
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$mock = Mockery::mock( Form::class )->makePartial();

		$mock->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive( 'is_login_form' )->with( $form )->andReturn( true );
		$mock->shouldReceive( 'is_login_limit_exceeded' )->with()->andReturn( $is_login_limit_exceeded );

		if ( $password_ok ) {
			$mock->shouldReceive( 'login' )->with( $email, Mockery::type( WP_User::class ) )->once();
		} else {
			$mock->shouldReceive( 'login_failed' )->with( $email )->once();
		}

		$this->prepare_verify_request( $response );

		//phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$post_data = [
			'h-captcha-response' => $response,
			'hcaptcha-widget-id' => $widget_id,
			'hcap_hp_sig'        => $_POST['hcap_hp_sig'],
			'hcap_fst_token'     => $_POST['hcap_fst_token'],
			'hcap_hp_test'       => $_POST['hcap_hp_test'],
		];
		//phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$_POST['data'] = http_build_query( $post_data );

		self::assertSame( $errors, $mock->verify( $errors, $data, $form, $fields ) );
	}

	/**
	 * Data provider for test_verify_login_form().
	 *
	 * @return array
	 */
	public function dp_test_verify_login_form(): array {
		return [
			[ false, true ],
			[ true, false ],
		];
	}

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 */
	public function test_print_hcaptcha_scripts(): void {
		wp_dequeue_script( 'hcaptcha-fst' );
		wp_deregister_script( 'hcaptcha-fst' );

		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

		wp_register_script( 'hcaptcha', 'https://test.test/wp-content/plugins/fluentform/some-assets/hcaptcha.js', [], '1.0.0', true );
		wp_enqueue_script( 'hcaptcha' );

		$subject = new Form();

		self::assertTrue( wp_script_is( 'hcaptcha' ) );
		self::assertTrue( wp_script_is( 'hcaptcha', 'registered' ) );

		// Without conversational form script.
		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
		self::assertFalse( wp_script_is( 'hcaptcha' ) );
		self::assertFalse( wp_script_is( 'hcaptcha', 'registered' ) );

		// With conversational form script.
		wp_register_script( 'fluent_forms_conversational_form', 'https://example.com/some-fluent-form.js', [], '1.0.0', true );
		wp_enqueue_script( 'fluent_forms_conversational_form' );

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
		self::assertFalse( wp_script_is( 'hcaptcha' ) );
		self::assertFalse( wp_script_is( 'hcaptcha', 'registered' ) );

		wp_dequeue_script( 'fluent_forms_conversational_form' );
		wp_deregister_script( 'fluent_forms_conversational_form' );
	}

	/**
	 * Test print_footer_scripts().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @noinspection ES6ConvertVarToLetConst
	 */
	public function test_print_footer_scripts(): void {
		global $wp_scripts;

		$handle = 'hcaptcha-fluentform';

		self::assertFalse( wp_script_is( $handle ) );

		$subject = new Form();

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
				'source'  => [ 'fluentformpro/fluentformpro.php', 'fluentform/fluentform.php' ],
				'form_id' => $form_id,
			],
		];
		$hcap_form      = HCaptcha::form( $args );
		$hcap_form      = str_replace( 'class="h-captcha"', 'class=""', $hcap_form );
		$hcap_form      = '<div class="h-captcha-hidden" style="display: none;">' . "\n$hcap_form\n</div>";

		$this->set_protected_property( $subject, 'form_id', $form_id );

		ob_start();
		$subject->print_footer_scripts();
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

		// Not on the fluent forms admin page.
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

		// Not an admin page.
		self::assertFalse( $subject->is_fluent_forms_admin_page() );

		// Not a Fluent Forms admin page.
		set_current_screen( 'some' );

		self::assertFalse( $subject->is_fluent_forms_admin_page() );

		// Success path. Fluent Forms admin pages.
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

		$expected = <<<'CSS'
	.frm-fluent-form .h-captcha {
		line-height: 0;
		margin-bottom: 0;
	}
	
	.fluentform-step.active .ff-el-input--hcaptcha {
		justify-self: end;
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
			FormFieldsParser::class . '::resetData',
			static function () {
				// Do nothing.
			}
		);

		FunctionMocker::replace(
			FormFieldsParser::class . '::hasElement',
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
	 * Test get_hcaptcha().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_hcaptcha(): void {
		$form_id  = 1;
		$args     = [
			'action' => 'hcaptcha_fluentform',
			'name'   => 'hcaptcha_fluentform_nonce',
			'id'     => [
				'source'  => [ 'fluentformpro/fluentformpro.php', 'fluentform/fluentform.php' ],
				'form_id' => $form_id,
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = Mockery::mock( Form::class )->makePartial();

		$this->set_protected_property( $subject, 'form_id', $form_id );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_form' )->andReturn( false );

		self::assertSame( $expected, $subject->get_hcaptcha() );
	}

	/**
	 * Test get_hcaptcha() for login form.
	 *
	 * @return void
	 */
	public function test_get_hcaptcha_for_login_form(): void {
		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_form' )->andReturn( true );
		$subject->shouldReceive( 'is_login_limit_exceeded' )->andReturn( false );

		self::assertSame( '', $subject->get_hcaptcha() );
	}

	/**
	 * Get hCaptcha form wrapped.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	private function get_get_hcaptcha_wrapped( int $form_id ): string {
		$hcap_form = $this->get_hcaptcha( $form_id );

		ob_start();

		?>
		<div class="ff-el-group">
			<div class="ff-el-input--content ff-el-input--hcaptcha">
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

	/**
	 * Get hCaptcha form.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	private function get_hcaptcha( int $form_id ): string {
		$args = [
			'action' => 'hcaptcha_fluentform',
			'name'   => 'hcaptcha_fluentform_nonce',
			'id'     => [
				'source'  => [ 'fluentformpro/fluentformpro.php', 'fluentform/fluentform.php' ],
				'form_id' => $form_id,
			],
		];

		return HCaptcha::form( $args );
	}
}
