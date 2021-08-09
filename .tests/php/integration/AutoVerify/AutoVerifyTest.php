<?php
/**
 * AutoVerifyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\AutoVerify;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WPDieException;

/**
 * Test AutoVerify class.
 *
 * @group auto-verify
 */
class AutoVerifyTest extends HCaptchaWPTestCase {
	/**
	 * Disable wp_die.
	 *
	 * @var bool
	 */
	private $disable_wp_die = false;

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		unset( $_SERVER['REQUEST_METHOD'], $GLOBALS['_wp_die_disabled'] );
		delete_transient( AutoVerify::TRANSIENT );
		$this->disable_wp_die = false;

		parent::tearDown();
	}

	/**
	 * Test init() and init_hooks().
	 */
	public function test_init_and_init_hooks() {
		$subject = new AutoVerify();
		$subject->init();

		self::assertSame(
			10,
			has_action( 'init', [ $subject, 'verify_form' ] )
		);

		self::assertSame(
			PHP_INT_MAX,
			has_filter( 'the_content', [ $subject, 'content_filter' ] )
		);
	}

	/**
	 * Test content_filter().
	 */
	public function test_content_filter() {
		$request_uri = $this->get_test_request_uri();
		$content     = $this->get_test_content();

		$_SERVER['REQUEST_URI'] = $request_uri;

		$expected = $this->get_registered_forms();

		$subject = new AutoVerify();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		self::assertSame( $content, $subject->content_filter( $content ) );
		self::assertSame( $expected, get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test content_filter() with action containing host.
	 */
	public function test_content_filter_with_action() {
		$request_uri = $this->get_test_request_uri();
		$content     = $this->get_test_content();
		$content     = str_replace(
			'<form method="post">',
			'<form method="post" action="http://test.test' . $request_uri . '">',
			$content
		);

		unset( $_SERVER['REQUEST_URI'] );

		$expected = $this->get_registered_forms();

		$subject = new AutoVerify();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		self::assertSame( $content, $subject->content_filter( $content ) );
		self::assertSame( $expected, get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test content_filter() when form action cannot be determined.
	 */
	public function test_content_filter_without_form_action() {
		$content = $this->get_test_content();

		unset( $_SERVER['REQUEST_URI'] );

		$subject = new AutoVerify();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		self::assertSame( $content, $subject->content_filter( $content ) );
		self::assertSame( [], get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test verify_form() when not POST request.
	 */
	public function test_verify_form_when_not_post() {
		$subject = new AutoVerify();
		$subject->verify_form();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$subject->verify_form();
	}

	/**
	 * Test verify_form() when no $_SERVER['REQUEST_URI'] defined.
	 */
	public function test_verify_form_when_no_request_uri() {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$subject = new AutoVerify();
		$subject->verify_form();
	}

	/**
	 * Test verify_form() when no forms are registered.
	 */
	public function test_verify_form_when_no_forms_are_registered() {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$subject = new AutoVerify();
		$subject->verify_form();
	}

	/**
	 * Test verify_form() when verify is not successful.
	 */
	public function test_verify_form_when_no_success() {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$_POST['test_input'] = 'some input';

		set_transient( AutoVerify::TRANSIENT, $this->get_registered_forms() );

		$this->disable_wp_die = true;

		$subject = new AutoVerify();
		$subject->verify_form();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [], $_POST );
	}

	/**
	 * Test verify_form() when verify is successful.
	 */
	public function test_verify_form_when_success() {
		$request_uri       = $this->get_test_request_uri();
		$hcaptcha_response = 'some response';
		$expected          = [
			'test_input'         => 'some input',
			'hcaptcha_nonce'     => $this->get_test_nonce(),
			'h-captcha-response' => $hcaptcha_response,
		];

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$_POST['test_input'] = 'some input';

		set_transient( AutoVerify::TRANSIENT, $this->get_registered_forms() );

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		$subject = new AutoVerify();
		$subject->verify_form();

		$_POST[ HCAPTCHA_NONCE ] = $this->get_test_nonce();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( $expected, $_POST );
	}

	/**
	 * WP die handler.
	 *
	 * @param string $message Message.
	 *
	 * @throws WPDieException WPDieException.
	 */
	public function wp_die_handler( $message ) {
		if ( $this->disable_wp_die ) {
			return;
		}

		parent::wp_die_handler( $message );
	}

	/**
	 * Get test request URI.
	 *
	 * @return string
	 */
	private function get_test_request_uri() {
		return '/hcaptcha-arbitrary-form/';
	}

	/**
	 * Get test nonce.
	 *
	 * @return string
	 */
	private function get_test_nonce() {
		return '5e9f1e63ed';
	}

	/**
	 * Get test content.
	 *
	 * @return string
	 */
	private function get_test_content() {
		$request_uri = $this->get_test_request_uri();
		$nonce       = $this->get_test_nonce();

		return '
<form method="post">
	<input type="text" name="test_input">
	<input type="submit" value="Send">
	<div
			class="h-captcha"
			data-sitekey="95d60c5a-68cf-4db1-a583-6a22bdd558f2"
			data-theme="light"
			data-size="normal"
			data-auto="true">
	</div>
	<input type="hidden" id="hcaptcha_nonce" name="hcaptcha_nonce" value="' . $nonce . '"/>
	<input type="hidden" name="_wp_http_referer" value="' . $request_uri . '"/>
</form>

<form role="search" method="get" action="http://test.test/"
	  class="wp-block-search__button-outside wp-block-search__text-button wp-block-search">
	<label for="wp-block-search__input-1" class="wp-block-search__label">Search</label>
	<div class="wp-block-search__inside-wrapper">
		<input type="search" id="wp-block-search__input-1"
			   class="wp-block-search__input" name="s" value="" placeholder=""
			   required/>
		<button type="submit" class="wp-block-search__button ">Search</button>
	</div>
</form>
';
	}

	/**
	 * Get registered forms.
	 *
	 * @return \string[][][]
	 */
	private function get_registered_forms() {
		$request_uri = $this->get_test_request_uri();

		return [
			untrailingslashit( $request_uri ) =>
				[
					[ 'test_input' ],
				],
		];
	}
}
