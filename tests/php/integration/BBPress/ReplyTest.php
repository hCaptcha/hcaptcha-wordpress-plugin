<?php
/**
 * ReplyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\Reply;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use WP_Error;

/**
 * Test Reply class.
 *
 * @group bbpress
 */
class ReplyTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'bbpress/bbpress.php';

	/**
	 * Tear down the test.
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
			'action' => 'hcaptcha_bbp_reply',
			'name'   => 'hcaptcha_bbp_reply_nonce',
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'reply',
			],
		];

		$expected = $this->get_hcap_form( $args );
		$subject  = new Reply();

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
		$this->prepare_verify_post( 'hcaptcha_bbp_reply_nonce', 'hcaptcha_bbp_reply' );
		$this->prepare_reply_topic();

		$expected = new WP_Error();
		$subject  = new Reply();

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
		$subject  = new Reply();

		$this->prepare_verify_post( 'hcaptcha_bbp_reply_nonce', 'hcaptcha_bbp_reply', null );
		$this->prepare_reply_topic();

		self::assertFalse( $subject->verify() );

		self::assertEquals( $expected, bbpress()->errors );
	}

	/**
	 * Prepare reply topic data.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function prepare_reply_topic(): void {
		$topic_id = wp_insert_post(
			[
				'post_title'   => 'Test topic',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_type'    => bbp_get_topic_post_type(),
			]
		);

		$_POST['bbp_topic_id'] = (string) $topic_id;
	}
}
