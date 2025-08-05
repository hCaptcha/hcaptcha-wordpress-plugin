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

use HCaptcha\GravityForms\Base;
use HCaptcha\GravityForms\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test GravityForms Form class.
 *
 * @group gravityforms
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $GLOBALS['current_screen'] );

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
		$button_input   = '';
		$hcaptcha_field = (object) [
			'type' => 'hcaptcha',
		];
		$form_id        = 23;
		$form           = [
			'id'     => $form_id,
			'fields' => [],
		];
		$expected       = $this->get_hcap_form(
			[
				'action' => Base::ACTION,
				'name'   => Base::NONCE,
				'id'     => [
					'source'  => [ 'gravityforms/gravityforms.php' ],
					'form_id' => $form_id,
				],
			]
		);

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'embed' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();

		$this->set_protected_property( $subject, 'form_id', $form_id );

		add_filter( 'hcap_form_args', [ $subject, 'hcap_form_args' ] );

		// Form does not exist (strange case), add hCaptcha.
		FunctionMocker::replace( 'GFFormsModel::get_form_meta' );

		self::assertSame( $expected, $subject->add_hcaptcha( $button_input, $form ) );

		// Does not have hCaptcha in the form, add hCaptcha.
		FunctionMocker::replace( 'GFFormsModel::get_form_meta', $form );

		self::assertSame( $expected, $subject->add_hcaptcha( $button_input, $form ) );

		// Has hCaptcha in the form, do not add hCaptcha.
		$form['fields'] = [ $hcaptcha_field ];

		FunctionMocker::replace( 'GFFormsModel::get_form_meta', $form );

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
		$form_id           = 23;
		$hcaptcha_field    = (object) [
			'type' => 'hcaptcha',
		];
		$form              = [
			'id'     => $form_id,
			'fields' => [ $hcaptcha_field ],
		];
		$validation_result = [
			'is_valid'               => true,
			'form'                   => [],
			'failed_validation_page' => 0,
		];
		$context           = 'form-submit';

		$_POST['gform_submit'] = $form_id;

		FunctionMocker::replace( 'GFFormsModel::get_form_meta', $form );

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ $mode ] ] );

		$this->prepare_verify_post( Base::NONCE, Base::ACTION );

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
		$form_id           = 23;
		$hcaptcha_field    = (object) [
			'type' => 'hcaptcha',
		];
		$form              = [
			'id'     => $form_id,
			'fields' => [ $hcaptcha_field ],
		];
		$validation_result = [
			'is_valid'               => true,
			'form'                   => [],
			'failed_validation_page' => 0,
		];
		$expected          = [
			'is_valid'               => false,
			'form'                   => [
				'validationSummary' => '1',
			],
			'failed_validation_page' => 0,
		];
		$context           = 'form-submit';

		$_POST['gform_submit'] = $form_id;

		FunctionMocker::replace( 'GFFormsModel::get_form_meta', $form );

		$this->prepare_verify_post( Base::NONCE, Base::ACTION, false );

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ $mode ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();

		self::assertSame( $expected, $subject->verify( $validation_result, $context ) );
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
		$form_id           = 2;
		$nested_form_id    = 9;
		$multipage_form_id = 3;
		$source_page_name  = "gform_source_page_number_$multipage_form_id";
		$target_page_name  = "gform_target_page_number_$multipage_form_id";
		$hcaptcha_field    = (object) [
			'type' => 'hcaptcha',
		];
		$nested_form_field = Mockery::mock( 'GP_Field_Nested_Form' );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$nested_form_field->gpnfForm = $form_id;
		$form_fields                 = [
			'id'     => $form_id,
			'fields' => [ $hcaptcha_field ],
		];
		$nested_form_fields          = [
			'id'     => $nested_form_id,
			'fields' => [ $nested_form_field ],
		];
		$multipage_form_fields       = [
			'id'         => $nested_form_id,
			'pagination' => [
				'pages' => [
					0 => [],
					1 => [],
				],
			],
			'fields'     => [ $hcaptcha_field ],
		];
		$validation_result           = [
			'is_valid'               => true,
			'form'                   => [],
			'failed_validation_page' => 0,
		];
		$context                     = 'form-submit';

		$subject = new Form();

		// The POST 'gform_submit' not set.
		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// Nested form.
		$_POST['gform_submit']        = $form_id;
		$_POST['gpnf_parent_form_id'] = $nested_form_id;

		FunctionMocker::replace(
			'GFFormsModel::get_form_meta',
			static function ( $id ) use (
				$form_id,
				$form_fields,
				$nested_form_id,
				$nested_form_fields,
				$multipage_form_id,
				$multipage_form_fields
			) {
				if ( $id === $form_id ) {
					return $form_fields;
				}

				if ( $id === $nested_form_id ) {
					return $nested_form_fields;
				}

				if ( $id === $multipage_form_id ) {
					return $multipage_form_fields;
				}

				return [];
			}
		);

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// Not a nested form.
		unset( $_POST['gpnf_parent_form_id'] );

		// Multipage form.
		$_POST['gform_submit'] = $multipage_form_id;

		// The POST target_page is set and not 0.
		$_POST[ $source_page_name ] = 1;
		$_POST[ $target_page_name ] = 2;

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// The POST target_page is set and 0.
		$_POST[ $source_page_name ] = 2;
		$_POST[ $target_page_name ] = 0;

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// The POST target_page is unset.
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
	.gform_previous_button + .h-captcha {
		margin-top: 2rem;
	}

	.gform_footer.before .h-captcha[data-size="normal"] {
		margin-bottom: 3px;
	}

	.gform_footer.before .h-captcha[data-size="compact"] {
		margin-bottom: 0;
	}

	.gform_wrapper.gravity-theme .gform_footer,
	.gform_wrapper.gravity-theme .gform_page_footer {
		flex-wrap: wrap;
	}

	.gform_wrapper.gravity-theme .h-captcha,
	.gform_wrapper.gravity-theme .h-captcha {
		margin: 0;
		flex-basis: 100%;
	}

	.gform_wrapper.gravity-theme input[type="submit"],
	.gform_wrapper.gravity-theme input[type="submit"] {
		align-self: flex-start;
	}

	.gform_wrapper.gravity-theme .h-captcha ~ input[type="submit"],
	.gform_wrapper.gravity-theme .h-captcha ~ input[type="submit"] {
		margin: 1em 0 0 0 !important;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Form();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
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
	}
}
