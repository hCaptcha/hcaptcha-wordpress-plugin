<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\GravityForms;

use GF_Field;
use GFAPI;
use HCaptcha\GravityForms\Base;
use HCaptcha\GravityForms\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Test GravityForms Form class.
 *
 * @group gravityforms
 */
class FormTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'gravityforms/gravityforms.php';

	/**
	 * Hooks to replay after loading the plugin.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
	];

	/**
	 * Whether the live Gravity Forms schema was prepared.
	 *
	 * @var bool
	 */
	private static bool $schema_ready = false;

	/**
	 * Set up the live Gravity Forms database schema.
	 */
	public function setUp(): void {
		parent::setUp();

		// The WP test transaction rolls these options back after every test.
		update_option( 'gf_db_version', \GFForms::$version, false );
		update_option( 'rg_form_version', \GFForms::$version, false );

		if ( ! self::$schema_ready ) {
			gf_upgrade()->upgrade_schema();
			self::$schema_ready = true;
		}
	}

	/**
	 * Test rendering through the live Gravity Forms API and frontend renderer.
	 */
	public function test_live_form_render(): void {
		$api_file = wp_normalize_path( ( new ReflectionClass( GFAPI::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/gravityforms/' ), $api_file );

		$form_id = GFAPI::add_form(
			[
				'title'  => 'hCaptcha integration form',
				'fields' => [
					[
						'id'         => 1,
						'type'       => 'text',
						'label'      => 'Name',
						'isRequired' => true,
					],
				],
				'button' => [
					'type' => 'text',
					'text' => 'Submit',
				],
			]
		);

		self::assertIsInt( $form_id );

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'form' ] ] );
		hcaptcha()->init_hooks();
		new Form();

		$html = gravity_form( $form_id, false, false, false, null, false, 0, false );

		self::assertStringContainsString( 'gform_wrapper', $html );
		self::assertStringContainsString( "name='input_1'", $html );
		self::assertStringContainsString( 'class="h-captcha"', $html );
		self::assertStringContainsString( 'gravity_forms_nonce', $html );
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset(
			$GLOBALS['current_screen'],
			$_POST['input_3_3'],
			$_POST['input_3_6'],
			$_POST['input_4'],
			$_POST['input_5'],
			$_POST['gform_submit'],
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]
		);

		parent::tearDown();
	}

	/**
	 * Test constructor and init hooks.
	 *
	 * @param bool $mode_auto Auto mode.
	 *
	 * @dataProvider dp_test_constructor_and_init_hooks
	 */
	public function test_constructor_and_init_hooks( bool $mode_auto ): void {
		if ( $mode_auto ) {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'form' ] ] );
		} else {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [] ] );
		}

		hcaptcha()->init_hooks();

		$subject = new Form();

		if ( $mode_auto ) {
			self::assertSame( 20, has_filter( 'gform_submit_button', [ $subject, 'add_hcaptcha' ] ) );
		} else {
			self::assertFalse( has_filter( 'gform_submit_button', [ $subject, 'add_hcaptcha' ] ) );
		}

		self::assertSame( 10, has_filter( 'gform_validation', [ $subject, 'verify' ] ) );
		self::assertSame( 10, has_filter( 'gform_form_validation_errors', [ $subject, 'form_validation_errors' ] ) );
		self::assertSame(
			10,
			has_filter( 'gform_form_validation_errors_markup', [ $subject, 'form_validation_errors_markup' ] )
		);
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Data provider for test_constructor_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_constructor_and_init_hooks(): array {
		return [
			'auto mode'   => [ true ],
			'manual mode' => [ false ],
		];
	}

	/**
	 * Test add_captcha().
	 *
	 * @param bool $is_admin Admin mode.
	 *
	 * @dataProvider dp_test_add_hcaptcha
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_hcaptcha( bool $is_admin ): void {
		$form_id = 23;
		$form    = [
			'id' => $form_id,
		];

		if ( $is_admin ) {
			$expected = '';
			set_current_screen( 'edit-post' );
		} else {
			$expected = $this->get_hcap_form(
				[
					'action' => Base::ACTION,
					'name'   => Base::NONCE,
					'id'     => [
						'source'  => [ 'gravityforms/gravityforms.php' ],
						'form_id' => $form_id,
					],
				]
			);
		}

		$subject = new Form();

		$this->set_protected_property( $subject, 'form_id', $form_id );

		add_filter( 'hcap_form_args', [ $subject, 'hcap_form_args' ] );

		self::assertSame( $expected, $subject->add_hcaptcha( '', $form ) );
	}

	/**
	 * Data provider for test_add_captcha().
	 *
	 * @return array
	 */
	public function dp_test_add_hcaptcha(): array {
		return [
			'admin'     => [ true ],
			'not admin' => [ false ],
		];
	}

	/**
	 * Test add_captcha() in embed mode.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_hcaptcha_in_embed_mode(): void {
		$button_input = '';

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'embed' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();
		$form_id = 999999;
		$form    = [
			'id'     => $form_id,
			'fields' => [],
		];

		$this->set_protected_property( $subject, 'form_id', $form_id );
		add_filter( 'hcap_form_args', [ $subject, 'hcap_form_args' ] );

		// Form does not exist (strange case), add hCaptcha.
		self::assertSame( $this->get_gravity_hcaptcha( $form_id ), $subject->add_hcaptcha( $button_input, $form ) );

		// Does not have hCaptcha in the form, add hCaptcha.
		$form    = $this->create_gravity_form( [] );
		$form_id = (int) $form['id'];

		$this->set_protected_property( $subject, 'form_id', $form_id );

		self::assertSame( $this->get_gravity_hcaptcha( $form_id ), $subject->add_hcaptcha( $button_input, $form ) );

		// Has hCaptcha in the form, do not add hCaptcha.
		$form = $this->create_gravity_form(
			[
				$this->get_gf_field(
					[
						'id'    => 1,
						'type'  => 'hcaptcha',
						'label' => 'hCaptcha',
					]
				),
			]
		);

		self::assertSame( $button_input, $subject->add_hcaptcha( $button_input, $form ) );
	}

	/**
	 * Test gform_open().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_gform_open(): void {
		$markup  = '<div>Some markup</div>';
		$form_id = 23;
		$form    = [
			'id' => $form_id,
		];

		$subject = new Form();

		self::assertSame( $markup, $subject->gform_open( $markup, $form ) );
		self::assertSame( 10, has_filter( 'hcap_form_args', [ $subject, 'hcap_form_args' ] ) );
		self::assertSame( $form_id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test gform_close().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_gform_close(): void {
		$form_string = '<div>Some form string</div>';

		$subject = new Form();

		add_filter( 'hcap_form_args', [ $subject, 'hcap_form_args' ] );

		self::assertSame( $form_string, $subject->gform_close( $form_string, [] ) );
		self::assertFalse( has_filter( 'hcap_form_args', [ $subject, 'hcap_form_args' ] ) );
		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test verify().
	 *
	 * @param string $mode Mode.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 */
	public function test_verify( string $mode ): void {
		$form              = $this->create_verification_form();
		$form_id           = (int) $form['id'];
		$validation_result = [
			'is_valid'               => true,
			'form'                   => $form,
			'failed_validation_page' => 0,
		];
		$context           = 'form-submit';

		$_POST['input_3_3']    = 'John';
		$_POST['input_3_6']    = 'Doe';
		$_POST['input_4']      = 'foo@bar.com';
		$_POST['gform_submit'] = $form_id;

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ $mode ] ] );

		$this->prepare_verify_post( Base::NONCE, Base::ACTION );
		$this->prepare_widget_id( $form_id );

		hcaptcha()->init_hooks();

		$subject = new Form();

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @param string $mode Mode.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 */
	public function test_verify_not_verified( string $mode ): void {
		$form              = $this->create_verification_form();
		$form_id           = (int) $form['id'];
		$validation_result = [
			'is_valid'               => true,
			'form'                   => $form,
			'failed_validation_page' => 0,
		];
		$expected          = [
			'is_valid'               => false,
			'form'                   => array_merge( $form, [ 'validationSummary' => '1' ] ),
			'failed_validation_page' => 0,
		];
		$context           = 'form-submit';

		$_POST['input_3_3']    = 'John';
		$_POST['input_3_6']    = 'Doe';
		$_POST['input_4']      = 'foo@bar.com';
		$_POST['gform_submit'] = $form_id;

		$this->prepare_verify_post( Base::NONCE, Base::ACTION, false );
		$this->prepare_widget_id( $form_id );

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ $mode ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();

		self::assertSame( $expected, $subject->verify( $validation_result, $context ) );
	}

	/**
	 * Test verify() when widget id is missing.
	 *
	 * @return void
	 */
	public function test_verify_missing_widget_id(): void {
		$form              = $this->create_verification_form( false );
		$form_id           = (int) $form['id'];
		$validation_result = [
			'is_valid'               => true,
			'form'                   => $form,
			'failed_validation_page' => 0,
		];
		$expected          = [
			'is_valid'               => false,
			'form'                   => array_merge( $form, [ 'validationSummary' => '1' ] ),
			'failed_validation_page' => 0,
		];
		$context           = 'form-submit';

		$_POST['input_3_3']    = 'John';
		$_POST['input_3_6']    = 'Doe';
		$_POST['input_4']      = 'foo@bar.com';
		$_POST['gform_submit'] = $form_id;

		$this->prepare_verify_post( Base::NONCE, Base::ACTION );

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'form' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();

		self::assertSame( $expected, $subject->verify( $validation_result, $context ) );
		self::assertSame(
			[
				[
					'field_selector' => '',
					'field_label'    => 'hCaptcha',
					'message'        => 'Bad hCaptcha signature!',
				],
			],
			$subject->form_validation_errors( [], $form )
		);
	}

	/**
	 * Test get_value() with an array value.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_value_with_array(): void {
		$field = $this->get_gf_field(
			[
				'id'     => 5,
				'type'   => 'multiselect',
				'label'  => 'Choices',
				'inputs' => null,
			]
		);

		$_POST['input_5'] = [ 'First', '', 'Second' ];

		$subject   = new Form();
		$get_value = $this->set_method_accessibility( $subject, 'get_value' );

		self::assertSame( 'First Second', $get_value->invoke( $subject, $field ) );
	}

	/**
	 * Data provider for test_verify().
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			[ 'form' ],
			[ 'embed' ],
		];
	}

	/**
	 * Test verify() when should not be verified.
	 *
	 * @return void
	 */
	public function test_verify_when_should_not_be_verified(): void {
		$form              = $this->create_gravity_form(
			[],
			[
				'pagination' => [
					'pages' => [
						[ 'name' => 'First page' ],
						[ 'name' => 'Second page' ],
					],
				],
			]
		);
		$form_id           = (int) $form['id'];
		$source_page_name  = "gform_source_page_number_$form_id";
		$target_page_name  = "gform_target_page_number_$form_id";
		$validation_result = [
			'is_valid'               => true,
			'form'                   => $form,
			'failed_validation_page' => 0,
		];
		$context           = 'form-submit';

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'form' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();

		// The POST 'gform_submit' not set.
		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// Multipage form.
		$_POST['gform_submit'] = $form_id;

		// Switching pages does not verify hCaptcha.
		$_POST[ $source_page_name ] = 1;
		$_POST[ $target_page_name ] = 2;

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// Submitting the last page verifies hCaptcha through the live form metadata.
		$_POST[ $source_page_name ] = 2;
		$_POST[ $target_page_name ] = 0;
		$this->prepare_verify_post( Base::NONCE, Base::ACTION );
		$this->prepare_widget_id( $form_id );

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// An unset target page is also a final submission.
		unset( $_POST[ $target_page_name ] );

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );
	}

	/**
	 * Test form_validation_errors().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_form_validation_errors(): void {
		$errors        = [];
		$form          = [];
		$error_message = 'Some hCaptcha error.';
		$expected      = [
			[
				'field_selector' => '',
				'field_label'    => 'hCaptcha',
				'message'        => $error_message,
			],
		];

		$subject = new Form();

		self::assertSame( $errors, $subject->form_validation_errors( $errors, $form ) );

		$this->set_protected_property( $subject, 'error_message', $error_message );

		self::assertSame( $expected, $subject->form_validation_errors( $errors, $form ) );
	}

	/**
	 * Test form_validation_errors_markup().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_form_validation_errors_markup(): void {
		$error_message            = 'Some hCaptcha error.';
		$validation_errors_markup = '<a href="https:://test.test/some-url">Some text with hCaptcha: </a>';
		$expected                 = "<div>$error_message</div>";
		$form                     = [];

		$subject = new Form();

		$subject->form_validation_errors_markup( $validation_errors_markup, $form );

		$this->set_protected_property( $subject, 'error_message', $error_message );

		self::assertSame( $expected, $subject->form_validation_errors_markup( $validation_errors_markup, $form ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		$subject = new Form();

		ob_start();

		$subject->print_inline_styles();

		$output = ob_get_clean();

		self::assertStringStartsWith( '<style>', $output );
		self::assertStringContainsString( '.gform_previous_button', $output );
		self::assertStringContainsString( '.gform_wrapper.gravity-theme', $output );
		self::assertStringEndsWith( "</style>\n", $output );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		self::assertFalse( wp_script_is( Form::HANDLE ) );

		$subject = new Form();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( Form::HANDLE ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( Form::HANDLE ) );
		self::assertContains( 'wp-hooks', wp_scripts()->registered[ Form::HANDLE ]->deps );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int   $form_id Form id.
	 * @param array $id      Widget id.
	 *
	 * @return void
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function prepare_widget_id( int $form_id, array $id = [] ): void {
		$id = array_merge(
			[
				'source'  => [ 'gravityforms/gravityforms.php' ],
				'form_id' => $form_id,
			],
			$id
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Create and reload a form through the live Gravity Forms API.
	 *
	 * @param array $fields     Form fields.
	 * @param array $properties Additional form properties.
	 *
	 * @return array
	 */
	private function create_gravity_form( array $fields, array $properties = [] ): array {
		$form_id = GFAPI::add_form(
			array_merge(
				[
					'title'  => 'hCaptcha integration form',
					'fields' => $fields,
				],
				$properties
			)
		);

		self::assertIsInt( $form_id );

		$form = GFAPI::get_form( $form_id );

		self::assertIsArray( $form );

		return $form;
	}

	/**
	 * Get the expected Gravity Forms hCaptcha markup.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	private function get_gravity_hcaptcha( int $form_id ): string {
		return $this->get_hcap_form(
			[
				'action' => Base::ACTION,
				'name'   => Base::NONCE,
				'id'     => [
					'source'  => [ 'gravityforms/gravityforms.php' ],
					'form_id' => $form_id,
				],
			]
		);
	}

	/**
	 * Create a Gravity Forms form used by verification tests.
	 *
	 * @param bool $with_hcaptcha Whether to add an embedded hCaptcha field.
	 *
	 * @return array
	 */
	private function create_verification_form( bool $with_hcaptcha = true ): array {
		$fields = [
			$this->get_gf_field(
				[
					'id'     => 3,
					'type'   => 'name',
					'label'  => 'Name',
					'inputs' => [
						[ 'id' => '3.2' ],
						[ 'id' => '3.3' ],
						[ 'id' => '3.4' ],
						[ 'id' => '3.6' ],
						[ 'id' => '3.8' ],
					],
				]
			),
			$this->get_gf_field(
				[
					'id'     => 4,
					'type'   => 'email',
					'label'  => 'Email',
					'inputs' => null,
				]
			),
		];

		if ( $with_hcaptcha ) {
			$fields[] = $this->get_gf_field(
				[
					'id'     => 2,
					'type'   => 'hcaptcha',
					'label'  => 'hCaptcha',
					'inputs' => null,
				]
			);
		}

		return $this->create_gravity_form( $fields );
	}

	/**
	 * Get GF_Field object.
	 *
	 * @param array $data Field data.
	 *
	 * @return GF_Field
	 */
	private function get_gf_field( array $data ): GF_Field {
		$field = new GF_Field();

		foreach ( $data as $key => $value ) {
			$field->$key = $value;
		}

		return $field;
	}
}
