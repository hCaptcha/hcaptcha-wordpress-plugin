<?php
/**
 * ProtectTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\PasswordProtected;

use HCaptcha\PasswordProtected\Protect;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test Protect class.
 *
 * @group password-protected
 */
class ProtectTest extends HCaptchaWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Protect();

		self::assertSame( 10, has_filter( 'password_protected_below_password_field', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_action( 'password_protected_verify_recaptcha', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'password_protected_login_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$form_id   = 'protect';
		$hcap_form = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_password_protected',
				'name'   => 'hcaptcha_password_protected_nonce',
				'id'     => [
					'source'  => [ 'password-protected/password-protected.php' ],
					'form_id' => $form_id,
				],
			]
		);

		$subject = new Protect();

		ob_start();

		$subject->add_hcaptcha();

		self::assertSame( $hcap_form, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @param bool $verified Verified or not.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 */
	public function test_verify( bool $verified ): void {
		$action   = 'hcaptcha_password_protected';
		$nonce    = 'hcaptcha_password_protected_nonce';
		$errors   = new WP_Error();
		$expected = $verified ? $errors : new WP_Error( 'fail', 'The hCaptcha is invalid.', 400 );

		$this->prepare_verify_post( $nonce, $action, $verified );

		$subject = new Protect();

		// Verify the hCaptcha.
		self::assertEquals( $expected, $subject->verify( $errors ) );
	}

	/**
	 * Data provider for test_verify().
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			[ 'not verified' => false ],
			[ 'verified' => true ],
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
	body.login-password-protected #loginform {
		min-width: 302px;
	}
	body.login-password-protected p.submit + div {
		margin-bottom: 15px;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Protect();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
