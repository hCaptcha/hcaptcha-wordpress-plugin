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
	public function test_add_captcha(): void {
		$args     = [
			'action' => 'hcaptcha_bp_register',
			'name'   => 'hcaptcha_bp_register_nonce',
			'id'     => [
				'source'  => 'buddypress/bp-loader.php',
				'form_id' => 'register',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() with error.
	 */
	public function test_register_error(): void {
		global $bp;

		$args                     = [
			'action' => 'hcaptcha_bp_register',
			'name'   => 'hcaptcha_bp_register_nonce',
			'id'     => [
				'source'  => 'buddypress/bp-loader.php',
				'form_id' => 'register',
			],
		];
		$hcaptcha_response_verify = 'some response';

		$bp->signup = (object) [
			'errors' => [
				'hcaptcha_response_verify' => $hcaptcha_response_verify,
			],
		];

		$expected =
			'<div class="error">' .
			$hcaptcha_response_verify .
			'</div>' .
			$this->get_hcap_form( $args );
		$subject  = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_bp_register_nonce', 'hcaptcha_bp_register' );

		$subject = new Register();

		self::assertTrue( $subject->verify() );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
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

		$this->prepare_verify_post( 'hcaptcha_bp_register_nonce', 'hcaptcha_bp_register', null );

		self::assertFalse( $subject->verify() );

		self::assertEquals( $expected, $bp->signup );
	}
}
