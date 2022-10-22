<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\FluentForm;

use HCaptcha\FluentForm\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WPDieException;

/**
 * Test FluentForm.
 *
 * @group fluentform
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Form();

		self::assertSame(
			10,
			has_action( 'fluentform_render_item_submit_button', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'fluentform_before_insert_submission', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		hcaptcha()->init_hooks();

		$subject = new Form();

		$expected = $this->get_hcap_form(
			'hcaptcha_fluentform',
			'hcaptcha_fluentform_nonce'
		);

		ob_start();
		$subject->add_captcha( [] );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify() with bad response.
	 *
	 * @return void
	 */
	public function test_verify_no_success() {
		$subject = new Form();

		$data['hcaptcha_fluentform_nonce'] = 'some nonce';
		$data['h-captcha-response']        = 'some response';

		// Simulate ajax, as it is the only case to stop execution of wp_send_json() via wp_die().
		add_filter(
			'wp_doing_ajax',
			static function() {
				return true;
			}
		);

		add_filter( 'wp_die_ajax_handler', [ $this, 'get_wp_die_handler' ] );

		$this->expectException( WPDieException::class );

		ob_start();
		$subject->verify( [], $data, [] );
	}

	/**
	 * The wp_die handler.
	 *
	 * @return array
	 */
	public function get_wp_die_handler() {
		self::assertSame( '{"errors":{"g-recaptcha-response":["The Captcha is invalid."]}}', ob_get_clean() );

		return parent::get_wp_die_handler();
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify() {
		$subject = new Form();

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_fluentform_nonce', 'hcaptcha_fluentform' );

		$data['hcaptcha_fluentform_nonce'] = wp_create_nonce( 'hcaptcha_fluentform' );
		$data['h-captcha-response']        = 'some response';

		$subject->verify( [], $data, [] );
	}
}
