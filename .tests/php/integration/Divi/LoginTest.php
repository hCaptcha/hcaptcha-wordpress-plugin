<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Divi;

use HCaptcha\Divi\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class LoginTest
 *
 * @group divi
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Login();

		self::assertSame(
			10,
			has_filter( Login::TAG . '_shortcode_output', [ $subject, 'add_divi_captcha' ] )
		);
	}

	/**
	 * Test add_divi_captcha().
	 */
	public function test_add_divi_captcha() {
		FunctionMocker::replace( 'et_core_is_fb_enabled', false );

		$output = '<div class="et_pb_module et_pb_login et_pb_login_0 et_pb_newsletter clearfix  et_pb_text_align_left et_pb_bg_layout_dark">
				
				
				<div class="et_pb_newsletter_description"><h2 class="et_pb_module_header">Your Title Goes Here</h2><div class="et_pb_newsletter_description_content"><p>Your content goes here. Edit or remove this text inline or in the module Content settings. You can also style every aspect of this content in the module Design settings and even apply custom CSS to this text in the module Advanced settings.</p></div></div>
				
				<div class="et_pb_newsletter_form et_pb_login_form">
					<form action="http://test.test/wp-login.php" method="post">
						<p class="et_pb_contact_form_field">
							<label class="et_pb_contact_form_label" for="user_login_61e5e64ddf4d8" style="display: none;">Username</label>
							<input id="user_login_61e5e64ddf4d8" placeholder="Username" class="input" type="text" value="" name="log" />
						</p>
						<p class="et_pb_contact_form_field">
							<label class="et_pb_contact_form_label" for="user_pass_61e5e64ddf4d8" style="display: none;">Password</label>
							<input id="user_pass_61e5e64ddf4d8" placeholder="Password" class="input" type="password" value="" name="pwd" />
						</p>
						<p class="et_pb_forgot_password"><a href="http://test.test/wp-login.php?action=lostpassword">Forgot your password?</a></p>
						<p>
							<button type="submit" name="et_builder_submit_button" class="et_pb_newsletter_button et_pb_button">Login</button>
							
						</p>
					</form>
				</div>
			</div>';

		$module_slug = 'et_pb_login';

		$expected = '<div class="et_pb_module et_pb_login et_pb_login_0 et_pb_newsletter clearfix  et_pb_text_align_left et_pb_bg_layout_dark">
				
				
				<div class="et_pb_newsletter_description"><h2 class="et_pb_module_header">Your Title Goes Here</h2><div class="et_pb_newsletter_description_content"><p>Your content goes here. Edit or remove this text inline or in the module Content settings. You can also style every aspect of this content in the module Design settings and even apply custom CSS to this text in the module Advanced settings.</p></div></div>
				
				<div class="et_pb_newsletter_form et_pb_login_form">
					<form action="http://test.test/wp-login.php" method="post">
						<p class="et_pb_contact_form_field">
							<label class="et_pb_contact_form_label" for="user_login_61e5e64ddf4d8" style="display: none;">Username</label>
							<input id="user_login_61e5e64ddf4d8" placeholder="Username" class="input" type="text" value="" name="log" />
						</p>
						<p class="et_pb_contact_form_field">
							<label class="et_pb_contact_form_label" for="user_pass_61e5e64ddf4d8" style="display: none;">Password</label>
							<input id="user_pass_61e5e64ddf4d8" placeholder="Password" class="input" type="password" value="" name="pwd" />
						</p>
						<p class="et_pb_forgot_password"><a href="http://test.test/wp-login.php?action=lostpassword">Forgot your password?</a></p>
						' . $this->get_hcap_form( 'hcaptcha_login', 'hcaptcha_login_nonce' ) . "\n" . '<p>
							<button type="submit" name="et_builder_submit_button" class="et_pb_newsletter_button et_pb_button">Login</button>
							
						</p>
					</form>
				</div>
			</div>';

		update_option(
			'hcaptcha_settings',
			[
				'divi_status' => [ 'login' ],
			]
		);

		hcaptcha()->init_hooks();

		$subject = new Login();

		self::assertSame( $expected, $subject->add_divi_captcha( $output, $module_slug ) );
	}

	/**
	 * Test add_divi_captcha() in frontend builder.
	 */
	public function test_add_divi_captcha_in_frontend_builder() {
		FunctionMocker::replace( 'et_core_is_fb_enabled', true );

		$output      = 'some string';
		$module_slug = 'et_pb_login';

		$subject = new Login();

		self::assertSame( $output, $subject->add_divi_captcha( $output, $module_slug ) );
	}
}
