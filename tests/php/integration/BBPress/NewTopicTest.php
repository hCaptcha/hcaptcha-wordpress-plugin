<?php
/**
 * NewTopic class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\NewTopic;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use WP_Error;

/**
 * Test NewTopic class.
 *
 * @group bbpress
 */
class NewTopicTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'bbpress/bbpress.php';

	/**
	 * Teardown test.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST );
		bbpress()->errors = new WP_Error();

		parent::tearDown();
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$args = [
			'action' => 'hcaptcha_bbp_new_topic',
			'name'   => 'hcaptcha_bbp_new_topic_nonce',
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'new_topic',
			],
		];

		$expected = $this->get_hcap_form( $args );

		$subject = new NewTopic();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_bbp_new_topic_nonce', 'hcaptcha_bbp_new_topic' );

		$expected = new WP_Error();
		$subject  = new NewTopic();

		self::assertTrue( $subject->verify() );

		self::assertEquals( $expected, bbpress()->errors );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$expected = new WP_Error( 'hcap_error', 'Please complete the hCaptcha.' );
		$subject  = new NewTopic();

		$this->prepare_verify_post( 'hcaptcha_bbp_new_topic_nonce', 'hcaptcha_bbp_new_topic', null );

		self::assertFalse( $subject->verify() );

		self::assertEquals( $expected, bbpress()->errors );
	}
}
