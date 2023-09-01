<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UM;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\UM\LostPassword;

/**
 * Class LostPasswordTest.
 *
 * @group um-lost-password
 * @group um
 */
class LostPasswordTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ultimate-member/ultimate-member.php';

	/**
	 * Tear down the test.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		UM()->form()->errors = null;

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = $this->get_subject();

		self::assertSame(
			100,
			has_action( 'um_get_form_fields', [ $subject, 'add_um_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_hcaptcha_form_edit_field', [ $subject, 'display_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_reset_password_errors_hook', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_after_password_reset_fields', [ $subject, 'um_after_password_reset_fields' ] )
		);
	}

	/**
	 * Test um_after_password_reset_fields().
	 *
	 * @return void
	 */
	public function test_um_after_password_reset_fields() {
		$subject = $this->get_subject();

		$expected = $subject->display_captcha( '', $subject::UM_MODE );

		ob_start();
		$subject->um_after_password_reset_fields( [] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Get subject.
	 *
	 * @return LostPassword
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_subject(): LostPassword {
		$subject = new LostPassword();

		UM()->fields()->set_mode = $subject::UM_MODE;

		return $subject;
	}
}
