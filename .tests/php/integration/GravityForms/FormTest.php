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

use GFFormsModel;
use HCaptcha\GravityForms\Base;
use HCaptcha\GravityForms\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test GravityForms.
 *
 * @group gravityforms
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
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
	public function test_constructor_and_init_hooks( bool $mode_auto ) {
		if ( $mode_auto ) {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'form' ] ] );
		} else {
			update_option( 'hcaptcha_settings', [ 'gravity_status' => [] ] );
		}

		hcaptcha()->init_hooks();

		$subject = new Form();

		if ( $mode_auto ) {
			self::assertSame( 10, has_filter( 'gform_submit_button', [ $subject, 'add_captcha' ] ) );
		} else {
			self::assertFalse( has_filter( 'gform_submit_button', [ $subject, 'add_captcha' ] ) );
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
	 * @dataProvider dp_test_add_captcha
	 */
	public function test_add_captcha( bool $is_admin ) {
		$form = [
			'id' => 23,
		];

		if ( $is_admin ) {
			$expected = '';
			set_current_screen( 'edit-post' );
		} else {
			$expected = $this->get_hcap_form( Base::ACTION, Base::NONCE );
		}

		$subject = new Form();

		self::assertSame( $expected, $subject->add_captcha( '', $form ) );
	}

	/**
	 * Data provider for test_add_captcha().
	 *
	 * @return array
	 */
	public function dp_test_add_captcha(): array {
		return [
			'admin'     => [ true ],
			'not admin' => [ false ],
		];
	}

	/**
	 * Test add_captcha() in embed mode.
	 */
	public function test_add_captcha_in_embed_mode() {
		$button_input   = '';
		$hcaptcha_field = (object) [
			'type' => 'hcaptcha',
		];
		$form           = [
			'id'     => 23,
			'fields' => [],
		];
		$expected       = $this->get_hcap_form( Base::ACTION, Base::NONCE );

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ 'embed' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();

		// Form does not exist (strange case), add hCaptcha.
		FunctionMocker::replace( 'GFFormsModel::get_form_meta' );

		self::assertSame( $expected, $subject->add_captcha( $button_input, $form ) );

		// Does not have hCaptcha in the form, add hCaptcha.
		FunctionMocker::replace( 'GFFormsModel::get_form_meta', $form );

		self::assertSame( $expected, $subject->add_captcha( $button_input, $form ) );

		// Has hCaptcha in the form, do not add hCaptcha.
		$form['fields'] = [ $hcaptcha_field ];

		FunctionMocker::replace( 'GFFormsModel::get_form_meta', $form );

		self::assertSame( $button_input, $subject->add_captcha( $button_input, $form ) );
	}

	/**
	 * Test verify().
	 *
	 * @param string $mode Mode.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 */
	public function test_verify( string $mode ) {
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

		$this->prepare_hcaptcha_verify_post( Form::NONCE, Form::ACTION );

		update_option( 'hcaptcha_settings', [ 'gravity_status' => [ $mode ] ] );
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
	public function test_verify_not_verified( string $mode ) {
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

		$this->prepare_hcaptcha_verify_post( Form::NONCE, Form::ACTION, false );

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
	public function test_verify_when_should_not_be_verified() {
		$form_id           = 23;
		$target_page       = "gform_target_page_number_$form_id";
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

		$subject = new Form();

		// The POST 'gform_submit' not set.
		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		$_POST['gform_submit'] = $form_id;

		// The POST 'gpnf_parent_form_id' is set.
		$_POST['gpnf_parent_form_id'] = 5;

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		unset( $_POST['gpnf_parent_form_id'] );

		// The POST target_page is set and not 0.
		$_POST[ $target_page ] = 3;

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// The POST target_page is set and 0.
		$_POST[ $target_page ] = 0;

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );

		// The POST target_page is unset.
		unset( $_POST[ $target_page ] );

		self::assertSame( $validation_result, $subject->verify( $validation_result, $context ) );
	}

	/**
	 * Test form_validation_errors().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_form_validation_errors() {
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
	public function test_form_validation_errors_markup() {
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
	 */
	public function test_print_inline_styles() {
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
	public function test_enqueue_scripts() {
		self::assertFalse( wp_script_is( Form::HANDLE ) );

		$subject = new Form();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( Form::HANDLE ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( Form::HANDLE ) );
	}
}
