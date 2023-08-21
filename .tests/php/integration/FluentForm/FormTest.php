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
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Test FluentForm.
 *
 * @group fluentform
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Form();

		self::assertSame(
			9,
			has_action( 'fluentform/render_item_submit_button', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'fluentform/validation_errors', [ $subject, 'verify' ] )
		);
		self::assertSame(
			9,
			has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] )
		);
		self::assertSame(
			10,
			has_filter( 'fluentform/rendering_form', [ $subject, 'fluentform_rendering_form_filter' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		hcaptcha()->init_hooks();

		$form = (object) [
			'id' => 1,
		];

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive( 'has_own_hcaptcha' )->with( $form )->andReturn( false );

		$hcap_form = $this->get_hcap_form(
			'hcaptcha_fluentform',
			'hcaptcha_fluentform_nonce'
		);

		ob_start();
		?>
		<div class="ff-el-group">
			<div class="ff-el-input--content">
				<div data-fluent_id="<?php echo (int) $form->id; ?>" name="h-captcha-response">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $hcap_form;
					?>
				</div>
			</div>
		</div>
		<?php
		$expected = ob_get_clean();

		ob_start();
		$mock->add_captcha( [], $form );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() with own captcha.
	 */
	public function test_add_captcha_with_own_captcha() {
		hcaptcha()->init_hooks();

		$form = (object) [];

		$mock = Mockery::mock( Form::class )->makePartial();
		$mock->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive( 'has_own_hcaptcha' )->with( $form )->andReturn( true );

		ob_start();
		$mock->add_captcha( [], $form );

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test verify() with bad response.
	 *
	 * @return void
	 */
	public function test_verify_no_success() {
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
	public function test_verify() {
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
}
