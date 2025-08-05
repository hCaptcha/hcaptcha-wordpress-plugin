<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EssentialAddons;

use HCaptcha\EssentialAddons\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use Elementor\Widget_Base;

/**
 * Class RegisterTest
 *
 * @group essential-addons
 * @group essential-addons-register
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['widget_id'], $_SERVER['HTTP_REFERER'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'eael/login-register/after-password-field', [ $subject, 'add_register_hcaptcha' ] )
		);
		self::assertSame( 10, has_action( 'eael/login-register/before-register', [ $subject, 'verify' ] ) );

		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );

		self::assertSame( 0, has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
	}

	/**
	 * Test add_register_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_register_hcaptcha(): void {
		$widget   = Mockery::mock( Widget_Base::class );
		$args     = [
			'action' => 'hcaptcha_essential_addons_register',
			'name'   => 'hcaptcha_essential_addons_register_nonce',
			'id'     => [
				'source'  => 'essential-addons-for-elementor-lite/essential_adons_elementor.php',
				'form_id' => 'register',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Register();

		ob_start();

		$subject->add_register_hcaptcha( $widget );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$this->prepare_verify_post(
			'hcaptcha_essential_addons_register',
			'hcaptcha_essential_addons_register_nonce'
		);

		$subject = new Register();

		$subject->verify();
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @param bool $has_referer Has referer.
	 *
	 * @return void
	 * @dataProvider dp_test_verify_not_verified
	 */
	public function test_verify_not_verified( bool $has_referer ): void {
		$widget_id     = 'some_id';
		$error_message = 'The hCaptcha is invalid.';
		$referer       = 'some-referer';
		$die_arr       = [];
		$setcookie     = [];
		$redirect      = false;
		$expected      = [
			'',
			'',
			[ 'response' => null ],
		];

		$_POST['widget_id'] = $widget_id;

		if ( $has_referer ) {
			$_SERVER['HTTP_REFERER'] = $referer;
		}

		$this->prepare_verify_post(
			'hcaptcha_essential_addons_register',
			'hcaptcha_essential_addons_register_nonce',
			false
		);

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter(
			'wp_redirect',
			static function () use ( &$redirect ) {
				$redirect = true;

				return false;
			}
		);

		FunctionMocker::replace(
			'setcookie',
			static function ( $name, $value ) use ( &$setcookie ) {
				$setcookie = [ $name, $value ];
			}
		);

		$subject = Mockery::mock( Register::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'exit' )->with()->times( (int) $has_referer );

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":false,"data":"' . $error_message . '"}', $json );
		self::assertSame( 'eael_login_error_' . $widget_id, $setcookie[0] );
		self::assertSame( $error_message, $setcookie[1] );
		self::assertSame( $redirect, $has_referer );
	}

	/**
	 * Data provider for test_verify_not_verified().
	 *
	 * @return array
	 */
	public function dp_test_verify_not_verified(): array {
		return [
			[ false ],
			[ true ],
		];
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
	#eael-register-form .h-captcha {
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
}
