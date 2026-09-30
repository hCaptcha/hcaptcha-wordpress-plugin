<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Subscriber;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Subscriber\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Form class.
 *
 * @group subscriber
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'subscriber_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Tests add_captcha().
	 */
	public function test_add_captcha(): void {
		hcaptcha()->init_hooks();

		$content  = '<!--some form content-->';
		$args     = [
			'action' => 'hcaptcha_subscriber_form',
			'name'   => 'hcaptcha_subscriber_form_nonce',
			'id'     => [
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			],
		];
		$expected = $content . $this->get_hcap_form( $args );
		$subject  = new Form();

		$output = $subject->add_captcha( $content );

		self::assertSame( $expected, $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		$subject = new Form();

		self::assertTrue( $subject->verify( true ) );
		self::assertFalse( $subject->verify( false ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form', false );

		$subject = new Form();

		self::assertSame( 'The hCaptcha is invalid.', $subject->verify( true ) );
	}

	/**
	 * Test verify() with a filled honeypot.
	 *
	 * @return void
	 */
	public function test_verify_filled_honeypot(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		self::assertSame( 'Anti-spam check failed.', ( new Form() )->verify( true ) );
	}
}
