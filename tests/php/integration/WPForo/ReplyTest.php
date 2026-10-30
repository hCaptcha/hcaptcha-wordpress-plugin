<?php
/**
 * ReplyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WPForo;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WPForo\Reply;
use tad\FunctionMocker\FunctionMocker;
use wpforo\classes\Notices;

/**
 * Test Reply class.
 *
 * @group wpforo
 */
class ReplyTest extends HCaptchaPluginWPTestCase {

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
	public function setUp(): void {
		set_current_screen( 'edit-post' );

		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'wpforo_status', [ 'new_topic', 'reply' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );

		WPF()->notice = new Notices();
	}

	/**
	 * Tear down the test.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		WPF()->session_token = '';
		WPF()->notice->clear();
		WPF()->session_token = '';

		parent::tearDown();
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$topic_id = 21;
		$topic    = [
			'forumid'  => 2,
			'topicid'  => $topic_id,
			'some key' => 'some value',
		];
		$args     = [
			'action' => 'hcaptcha_wpforo_reply',
			'name'   => 'hcaptcha_wpforo_reply_nonce',
			'id'     => [
				'source'  => [ 'wpforo/wpforo.php' ],
				'form_id' => $topic_id,
			],
		];
		$expected = $this->get_hcap_form( $args );

		new Reply();

		ob_start();

		do_action( Reply::ADD_CAPTCHA_HOOK, $topic );

		$output = (string) ob_get_clean();

		self::assertSame( $expected, $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$data    = [ 'topicid' => 21 ];
		$subject = new Reply();

		$this->prepare_verify_post( 'hcaptcha_wpforo_reply_nonce', 'hcaptcha_wpforo_reply' );
		$this->prepare_reply_identity();

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertSame( $data, $subject->verify( $data ) );
		self::assertSame( '', WPF()->notice->get_notices() );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$expected = '<p class="error">The hCaptcha is invalid.</p>';
		$subject  = new Reply();

		$this->prepare_verify_post( 'hcaptcha_wpforo_reply_nonce', 'hcaptcha_wpforo_reply', false );
		$this->prepare_reply_identity();

		FunctionMocker::replace( 'wpforo_is_ajax', true );

		WPF()->session_token = '23';

		self::assertSame( '', WPF()->notice->get_notices() );
		self::assertFalse( $subject->verify( [ 'topicid' => 21 ] ) );

		WPF()->session_token = '';

		self::assertSame( $expected, WPF()->notice->get_notices() );
	}

	/**
	 * Test verify() with a filled honeypot.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_filled_honeypot(): void {
		$subject = new Reply();

		$this->prepare_verify_post( 'hcaptcha_wpforo_reply_nonce', 'hcaptcha_wpforo_reply' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wpforo/wpforo.php' ],
				'form_id' => 21,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		FunctionMocker::replace( 'wpforo_is_ajax', true );

		WPF()->session_token = '23';

		self::assertFalse( $subject->verify( [ 'topicid' => 21 ] ) );

		WPF()->session_token = '';

		self::assertSame( '<p class="error">Anti-spam check failed.</p>', WPF()->notice->get_notices() );
	}

	/**
	 * Replies without a native topic ID still reject another topic's widget.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_missing_topic_id_rejects_foreign_widget(): void {
		$this->prepare_verify_post( 'hcaptcha_wpforo_reply_nonce', 'hcaptcha_wpforo_reply' );
		$this->prepare_reply_identity();
		FunctionMocker::replace( 'wpforo_is_ajax', true );
		WPF()->session_token = '23';

		self::assertFalse( ( new Reply() )->verify( [] ) );
		WPF()->session_token = '';
		self::assertSame(
			'<p class="error">' . hcap_get_error_messages()['bad-signature'] . '</p>',
			WPF()->notice->get_notices()
		);
	}

	/**
	 * Prepare the widget rendered for the native reply topic.
	 */
	private function prepare_reply_identity(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wpforo/wpforo.php' ],
				'form_id' => 21,
			]
		);
	}
}
