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

namespace HCaptcha\Tests\Integration\ACFE;

use HCaptcha\ACFE\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Test ACFE class.
 *
 * @group acfe
 */
class FormTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative paths.
	 *
	 * @var string[]
	 */
	protected static $plugin = [
		'advanced-custom-fields-pro/acf.php',
		'acf-extended/acf-extended.php',
	];

	/**
	 * Hooks to replay after loading ACF and ACF Extended.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Expected deprecation notices from ACF Extended.
	 *
	 * @var string[]
	 */
	protected static array $plugin_expected_deprecated = [
		'acfe/form/render/before_fields',
	];

	/**
	 * Test a live ACF Extended form with its reCAPTCHA field replaced by hCaptcha.
	 */
	public function test_live_form_render(): void {
		$field_group_key = 'group_hcaptcha_acfe_integration';

		acf_add_local_field_group(
			[
				'key'      => $field_group_key,
				'title'    => 'hCaptcha integration fields',
				'fields'   => [
					[
						'key'   => 'field_hcaptcha_acfe_name',
						'label' => 'Name',
						'name'  => 'name',
						'type'  => 'text',
					],
					[
						'key'        => 'field_hcaptcha_acfe_captcha',
						'label'      => 'Captcha',
						'name'       => 'captcha',
						'type'       => 'acfe_recaptcha',
						'required'   => 1,
						'version'    => 'v2',
						'v2_theme'   => 'light',
						'v2_size'    => 'normal',
						'site_key'   => 'live-site-key',
						'secret_key' => 'live-secret-key',
					],
				],
				'location' => [],
			]
		);

		acfe_register_form(
			[
				'name'         => 'hcaptcha-acfe-integration',
				'title'        => 'hCaptcha integration form',
				'field_groups' => [ $field_group_key ],
			]
		);

		$integration = new Form();
		$field_type  = acf_get_field_type( 'acfe_recaptcha' );
		$plugin_file = wp_normalize_path( ( new ReflectionClass( $field_type ) )->getFileName() );

		ob_start();
		acfe_form( 'hcaptcha-acfe-integration' );
		$html = ob_get_clean();

		self::assertTrue( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) );
		self::assertTrue( is_plugin_active( 'acf-extended/acf-extended.php' ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/acf-extended/' ), $plugin_file );
		self::assertInstanceOf( \acfe_field_recaptcha::class, $field_type );
		self::assertSame( 11, has_action( Form::RENDER_HOOK, [ $integration, 'add_hcaptcha' ] ) );
		self::assertStringContainsString( 'class="acfe-form"', $html );
		self::assertStringContainsString( 'name="acf[field_hcaptcha_acfe_name]"', $html );
		self::assertStringContainsString( 'class="h-captcha"', $html );
		self::assertStringContainsString( 'id="acf-field_hcaptcha_acfe_captcha"', $html );
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST['_acf_post_id'], $_POST['_acf_form'], $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

		wp_dequeue_script( 'hcaptcha-acfe' );
		wp_deregister_script( 'hcaptcha-acfe' );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 10, has_action( 'acfe/form/render/before_fields', [ $subject, 'before_fields' ] ) );
		self::assertSame( 8, has_action( Form::RENDER_HOOK, [ $subject, 'remove_recaptcha_render' ] ) );
		self::assertSame( 11, has_action( Form::RENDER_HOOK, [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 9, has_filter( Form::VALIDATION_HOOK, [ $subject, 'remove_recaptcha_verify' ] ) );
		self::assertSame( 11, has_filter( Form::VALIDATION_HOOK, [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test before_fields().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_before_fields(): void {
		$id = 5;

		$subject = new Form();

		$subject->before_fields( [ 'ID' => $id ] );

		self::assertSame( $id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test remove_recaptcha_render().
	 *
	 * @param array     $field    Field.
	 * @param int|false $expected Expected.
	 *
	 * @return void
	 *
	 * @dataProvider dp_test_remove_recaptcha_render
	 */
	public function test_remove_recaptcha_render( array $field, $expected ): void {
		$recaptcha = acf_get_field_type( 'acfe_recaptcha' );

		add_action( Form::RENDER_HOOK, [ $recaptcha, 'render_field' ], 9 );

		$subject = new Form();

		self::assertSame( 9, has_action( Form::RENDER_HOOK, [ $recaptcha, 'render_field' ] ) );

		$subject->remove_recaptcha_render( $field );

		self::assertSame( $expected, has_action( Form::RENDER_HOOK, [ $recaptcha, 'render_field' ] ) );
	}

	/**
	 * Data provider for test_remove_recaptcha_render().
	 *
	 * @return array
	 */
	public function dp_test_remove_recaptcha_render(): array {
		return [
			'recaptcha field' => [
				[ 'type' => 'acfe_recaptcha' ],
				false,
			],
			'some field'      => [
				[ 'type' => 'some' ],
				9,
			],
		];
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @param array  $field    Field.
	 * @param string $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_add_hcaptcha
	 */
	public function test_add_hcaptcha( array $field, string $expected ): void {
		$subject = new Form();

		hcaptcha()->init_hooks();

		ob_start();
		$subject->add_hcaptcha( $field );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider for test_add_hcaptcha().
	 *
	 * @return array
	 */
	public function dp_test_add_hcaptcha(): array {
		return [
			'recaptcha field' => [
				[
					'type' => 'acfe_recaptcha',
					'key'  => 'some-key',
					'name' => 'some-name',
				],
				'<div class="acf-input-wrap acfe-field-recaptcha"> <div>' .
				$this->get_hcap_form(
					[
						'id' => [
							'source'  => [
								'acf-extended-pro/acf-extended.php',
								'acf-extended/acf-extended.php',
							],
							'form_id' => 0,
						],
					]
				) . '</div><input type="hidden" id="acf-some-key" name="some-name"></div>',
			],
			'some field'      => [
				[ 'type' => 'some' ],
				'',
			],
		];
	}

	/**
	 * Test remove_recaptcha_verify().
	 *
	 * @return void
	 */
	public function test_remove_recaptcha_verify(): void {
		$value = 'some value';
		$field = [ 'type' => 'some' ];
		$input = 'some_input_name';

		$recaptcha = acf_get_field_type( 'acfe_recaptcha' );

		add_filter( Form::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] );

		$subject = new Form();

		self::assertSame( 10, has_action( Form::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] ) );

		$subject->remove_recaptcha_verify( true, $value, $field, $input );

		self::assertFalse( has_action( Form::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] ) );
	}

	/**
	 * Test verify.
	 *
	 * @param bool        $result   Request result.
	 * @param bool|string $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify( bool $result, $expected ): void {
		$valid   = ! $expected;
		$value   = 'some hcaptcha response';
		$input   = 'some_input_name';
		$form_id = 5;
		$field   = [ 'required' => true ];

		$_POST['_acf_form'] = 'acf-form';
		$this->prepare_widget_id( $form_id );
		$_POST['acf'] = [];

		$this->register_acf_form( $form_id );

		$this->prepare_verify_request( $value, $result );

		$subject = new Form();

		add_filter( 'wp_doing_ajax', '__return_true' );

		self::assertSame( $expected, $subject->verify( $valid, $value, $field, $input ) );
		self::assertSame( $form_id, $this->get_protected_property( $subject, 'form_id' ) );

		remove_filter( 'wp_doing_ajax', '__return_true' );

		self::assertSame( $expected, $subject->verify( $valid, $value, $field, $input ) );
		self::assertSame( $form_id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Data provider fot test_verify().
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			'request verified'     => [ true, true ],
			'request not verified' => [ false, 'The hCaptcha is invalid.' ],
		];
	}

	/**
	 * Test verify when field NOT required.
	 *
	 * @return void
	 */
	public function test_verify_when_NOT_required(): void {
		$value = 'some hcaptcha response';
		$input = 'some_input_name';
		$field = [ 'required' => false ];

		$subject = new Form();

		self::assertTrue( $subject->verify( true, $value, $field, $input ) );
		self::assertFalse( $subject->verify( false, $value, $field, $input ) );
	}

	/**
	 * Test verify direct POST without previous AJAX validation.
	 *
	 * @param bool        $result   Request result.
	 * @param bool|string $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_direct_post_without_ajax( bool $result, $expected ): void {
		$valid   = ! $expected;
		$value   = 'some hcaptcha response';
		$input   = 'some_input_name';
		$form_id = 5;
		$field   = [ 'required' => true ];

		$_POST['_acf_form'] = 'acf-form';
		$this->prepare_widget_id( $form_id );
		$_POST['acf'] = [];

		$this->register_acf_form( $form_id );
		$this->prepare_verify_request( $value, $result );

		$subject = new Form();

		self::assertSame( $expected, $subject->verify( $valid, $value, $field, $input ) );
		self::assertSame( $form_id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test verify on ajax.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_ajax(): void {
		$value = 'some hcaptcha response';
		$input = 'some_input_name';
		$field = [ 'required' => true ];

		$form_id = 5;

		$_POST['_acf_form'] = 'acf-form';
		$this->prepare_widget_id( $form_id );

		$this->register_acf_form( $form_id );
		$this->prepare_verify_request( $value, false );

		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = new Form();

		self::assertSame( 'The hCaptcha is invalid.', $subject->verify( true, $value, $field, $input ) );

		$this->prepare_widget_id( $form_id );

		self::assertSame( 'The hCaptcha is invalid.', $subject->verify( false, $value, $field, $input ) );

		self::assertSame( $form_id, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test verify on ajax when widget id is missing.
	 *
	 * @return void
	 */
	public function test_verify_ajax_missing_widget_id(): void {
		$value   = 'some hcaptcha response';
		$input   = 'some_input_name';
		$field   = [ 'required' => true ];
		$form_id = 5;

		$_POST['_acf_form'] = 'acf-form';

		$this->register_acf_form( $form_id );
		$this->prepare_verify_request( $value );

		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = new Form();

		self::assertSame( 'Bad hCaptcha signature!', $subject->verify( true, $value, $field, $input ) );

		remove_filter( 'wp_doing_ajax', '__return_true' );
	}
	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( int $form_id ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ],
				'form_id' => $form_id,
			]
		);
	}

	/**
	 * Register an ACF form for verification.
	 *
	 * @param array|false|int $form Form data.
	 *
	 * @return void
	 */
	private function register_acf_form( $form ): void {
		if ( false === $form ) {
			return;
		}

		$form = is_int( $form ) ? [ 'ID' => $form ] : $form;
		$form = array_merge( [ 'id' => 'acf-form' ], $form );

		acf()->form_front->add_form( $form );
	}

	/**
	 * Test get_entry() and get_data().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_entry_and_data(): void {
		$post_id   = wp_insert_post(
			[
				'post_title'  => 'ACFE Test Form',
				'post_status' => 'publish',
			]
		);
		$form_id   = (int) $post_id;
		$form_post = get_post( $post_id );
		$acf_data  = [
			'_validate_email'         => '',
			'field_name'              => 'Jane Doe',
			'field_email'             => 'jane.doe@example.com',
			'field_array'             => [ 'Hello', 'World' ],
			'field_empty'             => '',
			'field_key_without_label' => 'Value',
			''                        => 'empty-key-value',
		];
		$post_data = [
			'acf'                => $acf_data,
			'h-captcha-response' => 'captcha-response',
			'other'              => 'value',
		];

		$acf_fields = [
			'field_name'              => [
				'label' => 'Name',
				'type'  => 'text',
				'name'  => 'name',
			],
			'field_email'             => [
				'label' => 'Email',
				'type'  => 'email',
				'name'  => 'email',
			],
			'field_array'             => [
				'label' => 'Message',
				'type'  => 'textarea',
				'name'  => 'message',
			],
			'field_key_without_label' => [
				'label' => '',
				'type'  => 'text',
				'name'  => '',
			],
			'field_empty'             => [
				'label' => 'Empty',
				'type'  => 'text',
				'name'  => 'empty',
			],
		];

		acf_add_local_field_group(
			[
				'key'      => 'group_hcaptcha_acfe_entry',
				'title'    => 'ACFE entry fields',
				'fields'   => array_map(
					static function ( $field_key, $field ) {
						return array_merge( [ 'key' => $field_key ], $field );
					},
					array_keys( $acf_fields ),
					$acf_fields
				),
				'location' => [],
			]
		);

		$subject = new Form();
		$this->set_protected_property( $subject, 'form_id', $form_id );

		$get_entry = $this->set_method_accessibility( $subject, 'get_entry' );
		$entry     = $get_entry->invoke( $subject, $post_data );

		self::assertSame( 'captcha-response', $entry['h-captcha-response'] );
		self::assertSame( $form_post->post_modified_gmt, $entry['form_date_gmt'] );
		self::assertSame( $post_data, $entry['post_data'] );
		self::assertNull( $entry['nonce_name'] );
		self::assertNull( $entry['nonce_action'] );
		self::assertSame(
			[
				'Name'                    => 'Jane Doe',
				'email'                   => 'jane.doe@example.com',
				'Email'                   => 'jane.doe@example.com',
				'Message'                 => 'Hello World',
				'field_key_without_label' => 'Value',
				'name'                    => 'Jane Doe',
			],
			$entry['data']
		);
	}

	/**
	 * Test get_form() branches.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_form(): void {
		$subject  = new Form();
		$get_form = $this->set_method_accessibility( $subject, 'get_form' );

		unset( $_POST['_acf_form'] );
		self::assertFalse( $get_form->invoke( $subject ) );

		$_POST['_acf_form'] = 'acf-form';
		$this->register_acf_form( [ 'ID' => 55 ] );
		self::assertSame( 55, $get_form->invoke( $subject )['ID'] );

		$_POST['_acf_form'] = acf_encrypt( wp_json_encode( [ 'ID' => 77 ] ) );
		self::assertSame( [ 'ID' => 77 ], $get_form->invoke( $subject ) );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts(): void {
		$field = [
			'type' => 'acfe_recaptcha',
			'key'  => 'some-key',
			'name' => 'some-name',
		];

		$subject = new Form();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( Form::HANDLE ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_end_clean();

		self::assertFalse( wp_script_is( Form::HANDLE ) );

		hcaptcha()->init_hooks();

		ob_start();
		$subject->add_hcaptcha( $field );
		do_action( 'wp_print_footer_scripts' );
		ob_end_clean();

		self::assertTrue( wp_script_is( Form::HANDLE ) );
	}
}
