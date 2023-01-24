<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\BuddyPress;

use HCaptcha\BuddyPress\Register;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Register.
 *
 * @group bp
 */
class RegisterTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'buddypress/bp-loader.php';

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		global $bp;

		unset( $bp->signup );

		parent::tearDown();
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$nonce    = wp_nonce_field( 'hcaptcha_bp_register', 'hcaptcha_bp_register_nonce', true, false );
		$expected = $this->get_hcap_form() . $nonce;
		$subject  = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() with error.
	 *
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function test_register_error() {
		global $bp;

		$hcaptcha_response_verify = 'some response';

		$bp->signup = (object) [
			'errors' => [
				'hcaptcha_response_verify' => $hcaptcha_response_verify,
			],
		];

		$nonce    = wp_nonce_field( 'hcaptcha_bp_register', 'hcaptcha_bp_register_nonce', true, false );
		$expected =
			'<div class="error">' .
			$hcaptcha_response_verify .
			'</div>' .
			$this->get_hcap_form() . $nonce;
		$subject  = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_bp_register_nonce', 'hcaptcha_bp_register' );

		$subject = new Register();

		self::assertTrue( $subject->verify() );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function test_verify_not_verified() {
		global $bp;

		$bp->signup = (object) [
			'errors' => [],
		];
		$expected   = (object) [
			'errors' => [
				'hcaptcha_response_verify' => 'Please complete the hCaptcha.',
			],
		];
		$subject    = new Register();

		self::assertFalse( $subject->verify() );

		self::assertEquals( $expected, $bp->signup );
	}
}
