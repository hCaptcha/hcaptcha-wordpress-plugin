<?php
/**
 * UltimateAddons RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\UltimateAddons;

use Elementor\Element_Base;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\UltimateAddons\Register;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Ultimate Addons Register class.
 *
 * @group ultimate-addons-register
 * @group ultimate-addons
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Register();

		// Hooks from Base.
		self::assertSame( 10, has_action( 'elementor/frontend/widget/before_render', [ $subject, 'before_render' ] ) );
		self::assertSame( 10, has_action( 'elementor/frontend/widget/after_render', [ $subject, 'add_hcaptcha' ] ) );

		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );

		// Own hooks.
		self::assertSame( 0, has_action( 'wp_ajax_uael_register_user', [ $subject, 'verify' ] ) );
		self::assertSame( 0, has_action( 'wp_ajax_nopriv_uael_register_user', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test before_render() and add_hcaptcha().
	 */
	public function test_render(): void {
		$form = '<form>some HTML<div class="uael-reg-form-submit something"><button type="submit">Register</button></div></form>';

		$subject = new Register();

		// Test with a wrong element.
		$element = Mockery::mock( Element_Base::class );

		ob_start();
		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha( $element );
		$output = ob_get_clean();

		self::assertSame( $form, $output );

		// Test with a correct element.
		$element = Mockery::mock( 'alias:UltimateElementor\Modules\RegistrationForm\Widgets\RegistrationForm', Element_Base::class );

		$args      = [
			'action' => 'hcaptcha_ultimate_addons_register',
			'name'   => 'hcaptcha_ultimate_addons_register_nonce',
			'id'     => [
				'source'  => [ 'ultimate-elementor/ultimate-elementor.php' ],
				'form_id' => 'register',
			],
		];
		$hcaptcha  = $this->get_hcap_form( $args );
		$hcap_wrap =
			'<div class="elementor-field-group elementor-column elementor-col-100 elementor-hcaptcha">' .
			'<div class="uael-urf-field-wrapper">' .
			$hcaptcha .
			'</div>' .
			'</div>';

		$pattern     = '/(<div class="uael-reg-form-submit)/';
		$replacement = $hcap_wrap . "\n$1";
		$expected    = preg_replace( $pattern, $replacement, $form );

		ob_start();
		$subject->before_render( $element );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;

		$subject->add_hcaptcha( $element );
		$output = ob_get_clean();

		self::assertSame( $expected, $output );
	}

	/**
	 * Test verify() when verification succeeds -> outputs nothing.
	 */
	public function test_verify_success(): void {
		$subject = new Register();

		// Simulate successful verification.
		$this->prepare_verify_post( 'hcaptcha_ultimate_addons_register_nonce', 'hcaptcha_ultimate_addons_register' );
		$this->prepare_widget_id();

		$subject->verify();
	}

	/**
	 * Test verify() when verification FAILS -> sends a JSON error.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_not_verified(): void {
		$response     = [
			'success' => false,
			'data'    => [ 'hCaptchaError' => 'The hCaptcha is invalid.' ],
		];
		$expected_die = [
			'',
			'',
			[ 'response' => null ],
		];

		// Simulate failed verification.
		$this->prepare_verify_post( 'hcaptcha_ultimate_addons_register_nonce', 'hcaptcha_ultimate_addons_register', false );
		$this->prepare_widget_id();

		$subject = new Register();

		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();

		$subject->verify();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		self::assertSame( json_encode( $response ), ob_get_clean() );

		self::assertSame( $expected_die, $die_arr );
	}

	/**
	 * Test verify() when widget id is bad.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_bad_widget_id(): void {
		$response     = [
			'success' => false,
			'data'    => [ 'hCaptchaError' => 'Bad hCaptcha signature!' ],
		];
		$expected_die = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_verify_post( 'hcaptcha_ultimate_addons_register_nonce', 'hcaptcha_ultimate_addons_register' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'register',
			]
		);

		$subject = new Register();

		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();

		$subject->verify();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		self::assertSame( json_encode( $response ), ob_get_clean() );

		self::assertSame( $expected_die, $die_arr );
	}

	/**
	 * Test print_inline_styles().
	 *
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
	.uael-registration-form .h-captcha {
		margin-top: 1rem;
		margin-bottom: 0;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Register();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param array $id Widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'ultimate-elementor/ultimate-elementor.php' ],
			'form_id' => 'register',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
}
