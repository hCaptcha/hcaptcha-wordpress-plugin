<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\BBPress;

use HCaptcha\BBPress\Login;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test Login class.
 *
 * @group bbpress
 * @group bbpress-login
 */
class LoginTest extends HCaptchaPluginWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Login();

		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'do_shortcode_tag' ] ) );
	}

	/**
	 * Test do_shortcode_tag().
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_do_shortcode_tag(): void {
		$tag  = 'bbp-login';
		$attr = [];
		$m    = [];

		$placeholder = '===hcaptcha placeholder===';
		$template    = <<<HTML
<form action="https://test.test/wp-login.php">
		$placeholder<button type="submit"/>
</form>
HTML;

		$args      = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'login',
			],
		];
		$hcaptcha  = $this->get_hcap_form( $args );
		$signature = HCaptcha::get_signature( Login::class, 'login', true );

		$output   = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha . $signature . "\n", $template );

		$subject = new Login();

		// Wrong tag.
		self::assertSame( $output, $subject->do_shortcode_tag( $output, 'some', $attr, $m ) );

		// Logged in.
		wp_set_current_user( 1 );

		self::assertSame( $output, $subject->do_shortcode_tag( $output, $tag, $attr, $m ) );

		// Login limit not exceeded.
		wp_set_current_user( 0 );
		add_filter(
			'hcap_login_limit_exceeded',
			static function ( $value ) use ( &$limit_exceeded ) {
				return $limit_exceeded;
			}
		);

		$limit_exceeded = false;

		self::assertSame( $output, $subject->do_shortcode_tag( $output, $tag, $attr, $m ) );

		// Status is 'login'.
		$limit_exceeded = true;

		hcaptcha()->settings()->set( 'bbp_status', 'login' );

		self::assertSame( $expected, $subject->do_shortcode_tag( $output, $tag, $attr, $m ) );
		self::assertSame( 1, did_action( 'hcap_signature' ) );

		// Status is not 'login'.
		hcaptcha()->settings()->set( 'bbp_status', 'some' );

		$expected = str_replace( $placeholder, $signature . "\n", $template );

		self::assertSame( $expected, $subject->do_shortcode_tag( $output, $tag, $attr, $m ) );
		self::assertSame( 2, did_action( 'hcap_signature' ) );
	}
}
