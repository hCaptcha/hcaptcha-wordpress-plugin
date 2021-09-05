<?php
/**
 * CommentFormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\DefaultForms;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test comment form file.
 */
class CommentFormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test hcap_wp_comment_form().
	 */
	public function test_hcap_wp_comment_form() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_comment_form', 'hcaptcha_comment_form_nonce', true, false );

		ob_start();

		hcap_wp_comment_form();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_wp_login_comment_form().
	 */
	public function test_hcap_wp_login_comment_form() {
		$field = 'some field';

		wp_set_current_user( 1 );

		$expected =
			$field .
			$this->get_hcap_form() .
			wp_nonce_field(
				'hcaptcha_comment_form',
				'hcaptcha_comment_form_nonce',
				true,
				false
			);

		self::assertSame( $expected, hcap_wp_login_comment_form( $field ) );
	}

	/**
	 * Test hcap_wp_login_comment_form() not logged in.
	 */
	public function test_hcap_wp_login_comment_form_not_logged_in() {
		$field = 'some field';

		$expected = $field;

		self::assertSame( $expected, hcap_wp_login_comment_form( $field ) );
	}

	/**
	 * Test hcap_verify_comment_captcha().
	 */
	public function test_hcap_verify_comment_captcha() {
		$commentdata = [ 'some comment data' ];

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_form_nonce', 'hcaptcha_comment_form' );

		ob_start();
		self::assertSame( $commentdata, hcap_verify_comment_captcha( $commentdata ) );
		ob_get_clean();
	}

	/**
	 * Test hcap_verify_comment_captcha() not verified in admin.
	 */
	public function test_hcap_verify_comment_captcha_not_verified() {
		$commentdata = [ 'some comment data' ];

		set_current_screen( 'edit-post' );

		ob_start();
		self::assertSame( $commentdata, hcap_verify_comment_captcha( $commentdata ) );
		ob_get_clean();
	}

	/**
	 * Test hcap_verify_comment_captcha() not verified, not in admin.
	 */
	public function test_hcap_verify_comment_captcha_not_verified_not_admin() {
		$die_arr     = [];
		$expected    =
			[
				'<strong>Error</strong>: The Captcha is invalid.',
				'hCaptcha',
				[ 'back_link' => true ],
			];
		$commentdata = [ 'some comment data' ];

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_form_nonce', 'hcaptcha_comment_form', false );

		remove_filter( 'wp_die_handler', '_default_wp_die_handler' );

		add_filter(
			'wp_die_handler',
			function ( $name ) use ( &$die_arr ) {
				return function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			},
			10,
			1
		);

		ob_start();
		hcap_verify_comment_captcha( $commentdata );
		ob_get_clean();

		self::assertSame( $expected, $die_arr );
	}
}
