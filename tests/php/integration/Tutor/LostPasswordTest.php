<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Tutor;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\Tutor\LostPassword;
use WP_Error;

/**
 * Test LostPassword class.
 *
 * @group tutor
 * @group tutor-lost-password
 */
class LostPasswordTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'tutor/tutor.php';

	/**
	 * Tutor's activation creates tables that the WordPress test transaction cannot copy.
	 *
	 * @var string[]
	 */
	protected static array $plugin_silent_activation = [ 'tutor/tutor.php' ];

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'tutor_status', 'lost_pass' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test hCaptcha output uses the nonce verified by the integration.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$subject = new LostPassword();

		ob_start();
		$subject->add_hcaptcha();
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'tutor-pro/tutor-pro.php', 'tutor/tutor.php' ],
			'form_id' => 'lost_password',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcaptcha_tutor_lost_password_nonce"', $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test the hCaptcha widget in the Tutor LMS Lite lost-password form.
	 *
	 * @return void
	 */
	public function test_lite_lost_password_form(): void {
		new LostPassword();

		add_filter( 'hcap_delay_api_event', '__return_true' );
		ob_start();
		include WP_PLUGIN_DIR . '/tutor/templates/template-part/retrieve-password.php';
		$output = (string) ob_get_clean();
		remove_filter( 'hcap_delay_api_event', '__return_true' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringContainsString( 'class="tutor-forgot-password-form', $output );
		self::assertStringContainsString( 'name="hcaptcha_tutor_lost_password_nonce"', $output );
		self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $output );
	}

	/**
	 * Test lost-password entry includes only the submitted username.
	 *
	 * @return void
	 */
	public function test_entry_data(): void {
		$_POST['user_login'] = 'student';
		$_POST['password']   = 'secret';

		$subject = new LostPassword();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject );

		self::assertSame( [ 'user_login' => 'student' ], $entry['data'] );
		self::assertSame( 'lost_password', $entry['expected_id']['form_id'] );
	}

	/**
	 * Test a filled honeypot blocks lost-password processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_tutor_lost_password_nonce',
			'hcaptcha_tutor_lost_password'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'tutor-pro/tutor-pro.php', 'tutor/tutor.php' ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$result = ( new LostPassword() )->verify( new WP_Error() );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Anti-spam check failed.', $result->get_error_message( 'spam' ) );
	}

	/**
	 * Test Tutor password recovery rejects a missing widget ID.
	 *
	 * @return void
	 */
	public function test_missing_widget_id_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_tutor_lost_password_nonce', 'hcaptcha_tutor_lost_password' );

		$result = ( new LostPassword() )->verify( new WP_Error() );

		self::assertSame( 'Bad hCaptcha signature!', $result->get_error_message( 'bad-signature' ) );
	}
}
