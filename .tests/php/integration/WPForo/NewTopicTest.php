<?php
/**
 * NewTopicTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WPForo;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WPForo\NewTopic;
use tad\FunctionMocker\FunctionMocker;
use wpforo\classes\Notices;

/**
 * Test NewTopic class.
 */
class NewTopicTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'wpforo/wpforo.php';

	/**
	 * Set up test.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function setUp(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		set_current_screen( 'edit-post' );

		parent::setUp();

		WPF()->notice = new Notices();
	}

	/**
	 * Tear down test.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		WPF()->session_token = '';
		WPF()->notice->clear();
		WPF()->session_token = '';

		parent::tearDown();
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$topic    = 2;
		$nonce    = wp_nonce_field( 'hcaptcha_wpforo_new_topic', 'hcaptcha_wpforo_new_topic_nonce', true, false );
		$expected = $this->get_hcap_form() . $nonce;
		$subject  = new NewTopic();

		ob_start();

		$subject->add_captcha( $topic );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$data    = [ 'some data' ];
		$subject = new NewTopic();

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforo_new_topic_nonce', 'hcaptcha_wpforo_new_topic' );

		WPF()->session_token = '23';

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertEquals( $data, $subject->verify( $data ) );
		self::assertSame( '', WPF()->notice->get_notices() );
	}

	/**
	 * Test verify() when not verified.
	 */
	public function test_verify_not_verified() {
		$expected = '<p class="error">The hCaptcha is invalid.</p>';
		$subject  = new NewTopic();

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wpforo_new_topic_nonce', 'hcaptcha_wpforo_new_topic', false );

		FunctionMocker::replace( 'wpforo_is_ajax', true );

		WPF()->session_token = '23';

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertFalse( $subject->verify( [] ) );

		WPF()->session_token = '';

		self::assertSame( $expected, WPF()->notice->get_notices() );
	}
}
