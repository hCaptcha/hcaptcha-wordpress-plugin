<?php
/**
 * LoginOutTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\LoginOut;
use Mockery;

/**
 * Class LoginOutTest.
 *
 * @group wp-login-out
 * @group wp
 */
class LoginOutTest extends HCaptchaWPTestCase {

	/**
	 * Tests init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = Mockery::mock( LoginOut::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->init_hooks();

		self::assertSame( 10, has_action( 'hcap_signature', [ $subject, 'display_signature' ] ) );
		self::assertSame( 10, has_filter( 'login_form_middle', [ $subject, 'add_wp_login_out_hcaptcha' ] ) );
	}

	/**
	 * Test add_wp_login_out_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_wp_login_out_hcaptcha(): void {
		$content  = 'some content';
		$args     = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => 'WordPress',
				'form_id' => 'login',
			],
		];
		$expected = $content . $this->get_hcap_form( $args );

		$subject = new LoginOut();

		// Not a login_out_form.
		self::assertSame( $content, $subject->add_wp_login_out_hcaptcha( $content, $args ) );

		apply_filters( 'login_form_defaults', [] );

		// A login_out_form.
		self::assertSame( $expected, $subject->add_wp_login_out_hcaptcha( $content, $args ) );
	}
}
