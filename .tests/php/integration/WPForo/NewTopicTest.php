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
 *
 * @group wpforo
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
		$args     = [
			'action' => 'hcaptcha_wpforo_new_topic',
			'name'   => 'hcaptcha_wpforo_new_topic_nonce',
			'id'     => [
				'source'  => [ 'wpforo/wpforo.php' ],
				'form_id' => 'new_topic',
			],
		];
		$expected = $this->get_hcap_form( $args );

		new NewTopic();

		ob_start();

		do_action( NewTopic::ADD_CAPTCHA_HOOK, $topic );

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

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 */
	public function test_print_hcaptcha_scripts() {
		$subject = new NewTopic();

		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->print_hcaptcha_scripts( true ) );

		apply_filters( 'wpforo_template', [] );

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->print_hcaptcha_scripts( true ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts() {
		$subject = new NewTopic();

		self::assertFalse( wp_script_is( 'hcaptcha-wpforo' ) );

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-wpforo' ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function test_print_inline_styles() {
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

		$expected = <<<CSS
	#wpforo #wpforo-wrap div .h-captcha {
		position: relative;
		display: block;
		margin-bottom: 2rem;
		padding: 0;
		clear: both;
	}

	#wpforo #wpforo-wrap.wpft-topic div .h-captcha,
	#wpforo #wpforo-wrap.wpft-forum div .h-captcha {
		margin: 0 -20px;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new NewTopic();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );

		// Test when styles are already shown.
		ob_start();

		$subject->print_inline_styles();

		self::assertSame( '', ob_get_clean() );
	}
}
