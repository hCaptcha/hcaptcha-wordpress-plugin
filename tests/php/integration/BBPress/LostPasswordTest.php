<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use WP_Error;

/**
 * Test LostPassword class.
 *
 * @group bbpress
 * @group bbpress-lost-password
 */
class LostPasswordTest extends HCaptchaPluginWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		hcaptcha()->settings()->set( 'bbp_status', 'lost_pass' );

		$subject = new LostPassword();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );

		hcaptcha()->settings()->set( 'bbp_status', 'some' );

		$subject = new LostPassword();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_filter( 'hcap_protect_form', [ $subject, 'hcap_protect_form' ] ) );
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$tag  = 'bbp-lost-pass';
		$attr = [];
		$m    = [];

		$placeholder = '===hcaptcha placeholder===';
		$template    = <<<HTML
<form action="https://test.test/wp-login.php">
		$placeholder<button type="submit"/>
</form>
HTML;

		$args     = [
			'action' => 'hcaptcha_action',
			'name'   => 'hcaptcha_nonce',
			'auto'   => true,
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'lost_password',
			],
		];
		$hcaptcha = $this->get_hcap_form( $args );

		$output   = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha . "\n", $template );

		hcaptcha()->settings()->set( 'bbp_status', 'lost_pass' );
		$subject = new LostPassword();

		// Wrong tag.
		self::assertSame( $output, $subject->add_captcha( $output, 'some', $attr, $m ) );

		// Logged in.
		wp_set_current_user( 1 );

		self::assertSame( $output, $subject->add_captcha( $output, $tag, $attr, $m ) );

		// Add hCaptcha..
		wp_set_current_user( 0 );

		add_action(
			'hcap_auto_verify_register',
			static function ( $html ) use ( &$registered_output ) {
				$registered_output = $html;
			}
		);

		self::assertSame( $expected, $subject->add_captcha( $output, $tag, $attr, $m ) );
		self::assertSame( 1, did_action( 'hcap_auto_verify_register' ) );
		self::assertSame( $expected, $registered_output );
	}

	/**
	 * Test hcap_protect_form().
	 *
	 * @return void
	 */
	public function test_hcap_protect_form(): void {
		$source  = [ 'bbpress/bbpress.php' ];
		$form_id = 'lost_password';

		$subject = new LostPassword();

		self::assertTrue( $subject->hcap_protect_form( true, [ 'some source' ], $form_id ) );
		self::assertTrue( $subject->hcap_protect_form( true, $source, 'some form id' ) );
		self::assertFalse( $subject->hcap_protect_form( true, $source, $form_id ) );
	}
}
