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
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test ACFE class.
 *
 * @group acfe
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_POST['_acf_post_id'], $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

		wp_dequeue_script( 'hcaptcha-acfe' );
		wp_deregister_script( 'hcaptcha-acfe' );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks() {
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
	public function test_before_fields() {
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
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function test_remove_recaptcha_render( array $field, $expected ) {
		$recaptcha = Mockery::mock( 'acfe_field_recaptcha' );
		$recaptcha->shouldReceive( 'render_field' );

		FunctionMocker::replace( 'acf_get_field_type', $recaptcha );

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
	public function test_add_hcaptcha( array $field, string $expected ) {
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
				'<div class="acf-input-wrap acfe-field-recaptcha"> <div>		<div
			class="h-captcha"
			data-sitekey=""
			data-theme=""
			data-size=""
			data-auto="false">
		</div>
		</div><input type="hidden" id="acf-some-key" name="some-name"></div>',
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
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function test_remove_recaptcha_verify() {
		$value = 'some value';
		$field = [ 'type' => 'some' ];
		$input = 'some_input_name';

		$recaptcha = Mockery::mock( 'acfe_field_recaptcha' );
		$recaptcha->shouldReceive( 'render_field' );

		FunctionMocker::replace( 'acf_get_field_type', $recaptcha );

		add_filter( Form::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] );

		$subject = new Form();

		self::assertSame( 10, has_action( Form::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] ) );

		$subject->remove_recaptcha_verify( true, $value, $field, $input );

		self::assertFalse( has_action( Form::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] ) );
	}

	/**
	 * Test verify.
	 *
	 * @param bool $result   Request result.
	 * @param bool $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify( bool $result, bool $expected ) {
		$valid   = ! $expected;
		$value   = 'some hcaptcha response';
		$input   = 'some_input_name';
		$form_id = 5;
		$field   = [ 'required' => true ];

		$_POST['_acf_post_id']                 = $form_id;
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = 'encoded-hash';

		$this->prepare_hcaptcha_request_verify( $value, $result );

		$subject = new Form();

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
			'request not verified' => [ false, false ],
		];
	}

	/**
	 * Test verify when field NOT required.
	 *
	 * @return void
	 */
	public function test_verify_when_NOT_required() {
		$value = 'some hcaptcha response';
		$input = 'some_input_name';
		$field = [ 'required' => false ];

		$subject = new Form();

		self::assertTrue( $subject->verify( true, $value, $field, $input ) );
		self::assertFalse( $subject->verify( false, $value, $field, $input ) );
	}

	/**
	 * Test verify on ajax.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_ajax() {
		$value = 'some hcaptcha response';
		$input = 'some_input_name';
		$field = [ 'required' => true ];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = 'encoded-hash';

		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = new Form();

		self::assertTrue( $subject->verify( true, $value, $field, $input ) );
		self::assertFalse( $subject->verify( false, $value, $field, $input ) );

		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts() {
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
