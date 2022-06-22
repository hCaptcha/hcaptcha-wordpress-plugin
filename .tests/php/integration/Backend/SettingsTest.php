<?php
/**
 * SettingsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Backend;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test settings file.
 */
class SettingsTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST );

		parent::tearDown();
	}

	/**
	 * Tests hcap_display_options_page() output.
	 */
	public function test_hcap_display_options_page_output() {
		$nonce_field = wp_nonce_field( 'hcaptcha_settings', 'hcaptcha_settings_nonce', true, false );

		$expected = '	<div class="wrap">
				<h3>hCaptcha Settings</h3>
		<h3>
			In order to use <a href="https://hCaptcha.com/?r=wp" target="_blank">hCaptcha</a> please register <a href="https://hCaptcha.com/?r=wp" target="_blank">here</a> to get your site key and secret key.		</h3>
		<form method="post" action="">
						<strong>
				hCaptcha Site Key			</strong>
			<br><br>
			<input
					type="text" size="50"
					id="hcaptcha_api_key"
					name="hcaptcha_api_key"
					value=""/>
						<br><br>
						<strong>
				hCaptcha Secret Key			</strong>
			<br><br>
			<input
					type="password" size="50"
					id="hcaptcha_secret_key"
					name="hcaptcha_secret_key"
					value=""/>
						<br><br>
							<strong>hCaptcha Theme</strong>
				<br><br>
				<select
						id="hcaptcha_theme"
						name="hcaptcha_theme">
											<option
								value="light"
							>
							Light						</option>
												<option
								value="dark"
							>
							Dark						</option>
										</select>
				<br><br>
								<strong>hCaptcha Size</strong>
				<br><br>
				<select
						id="hcaptcha_size"
						name="hcaptcha_size">
											<option
								value="normal"
							>
							Normal						</option>
												<option
								value="compact"
							>
							Compact						</option>
												<option
								value="invisible"
							>
							Invisible						</option>
										</select>
				<br><br>
							<strong>
				Override Language Detection (optional)			</strong>
			<br><br>
			<input
					type="text" size="50"
					id="hcaptcha_language"
					name="hcaptcha_language"
					value=""/>
			<br>Info on <a href="https://hcaptcha.com/docs/languages" target="_blank">language codes</a>.			<br><br>
				<strong>Enable/Disable Features</strong>
	<br><br>
				<input
					type="checkbox"
					id="hcaptcha_off_when_logged_in"
					name="hcaptcha_off_when_logged_in"
				/>
			&nbsp;
			<span>Turn off when logged in</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_recaptchacompat"
					name="hcaptcha_recaptchacompat"
				/>
			&nbsp;
			<span>Disable reCAPTCHA Compatibility (use if including both hCaptcha and reCAPTCHA on the same page)</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_lf_status"
					name="hcaptcha_lf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Login Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_rf_status"
					name="hcaptcha_rf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Register Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_lpf_status"
					name="hcaptcha_lpf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Lost Password Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_cmf_status"
					name="hcaptcha_cmf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Comment Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bbp_new_topic_status"
					name="hcaptcha_bbp_new_topic_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on bbPress New Topic Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bbp_reply_status"
					name="hcaptcha_bbp_reply_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on bbPress Reply Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bp_create_group_status"
					name="hcaptcha_bp_create_group_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on BuddyPress Create Group Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bp_reg_status"
					name="hcaptcha_bp_reg_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on BuddyPress Registration Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_cf7_status"
					name="hcaptcha_cf7_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Contact Form 7</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_divi_cf_status"
					name="hcaptcha_divi_cf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Divi Contact Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_divi_lf_status"
					name="hcaptcha_divi_lf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Divi Login Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_elementor__pro_form_status"
					name="hcaptcha_elementor__pro_form_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Elementor Pro Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_fluentform_status"
					name="hcaptcha_fluentform_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Fluent Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_gravityform_status"
					name="hcaptcha_gravityform_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Gravity Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_jetpack_cf_status"
					name="hcaptcha_jetpack_cf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Jetpack Contact Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_mc4wp_status"
					name="hcaptcha_mc4wp_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Mailchimp for WP Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_memberpress_register_status"
					name="hcaptcha_memberpress_register_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on MemberPress Registration Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_nf_status"
					name="hcaptcha_nf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Ninja Forms</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_subscribers_status"
					name="hcaptcha_subscribers_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Subscribers Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_um_login_status"
					name="hcaptcha_um_login_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Ultimate Member Login</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_um_lost_pass_status"
					name="hcaptcha_um_lost_pass_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Ultimate Member Lost Password</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_um_register_status"
					name="hcaptcha_um_register_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Ultimate Member Register</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_login_status"
					name="hcaptcha_wc_login_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Login Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_reg_status"
					name="hcaptcha_wc_reg_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Registration Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_lost_pass_status"
					name="hcaptcha_wc_lost_pass_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Lost Password Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_checkout_status"
					name="hcaptcha_wc_checkout_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Checkout Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_order_tracking_status"
					name="hcaptcha_wc_order_tracking_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Order Tracking Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_wl_create_list_status"
					name="hcaptcha_wc_wl_create_list_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Wishlists Create List Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforms_status"
					name="hcaptcha_wpforms_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WPForms Lite</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforms_pro_status"
					name="hcaptcha_wpforms_pro_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WPForms Pro</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforo_new_topic_status"
					name="hcaptcha_wpforo_new_topic_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WPForo New Topic Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforo_reply_status"
					name="hcaptcha_wpforo_reply_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WPForo Reply Form</span>
			<br><br>
						<p>
				<input
						type="submit"
						value="Save hCaptcha Settings"
						class="button button-primary"
						name="submit"/>
			</p>
			' . $nonce_field . '		</form>
	</div>
	';

		foreach ( hcap_options() as $option_name => $option_value ) {
			self::assertFalse( get_option( $option_name ) );
		}

		ob_start();

		hcap_display_options_page();

		self::assertSame( $expected, ob_get_clean() );

		foreach ( hcap_options() as $option_name => $option_value ) {
			self::assertFalse( get_option( $option_name ) );
		}
	}

	/**
	 * Tests hcap_display_options_page() update.
	 */
	public function test_hcap_display_options_page_update() {
		$nonce       = wp_create_nonce( 'hcaptcha_settings' );
		$nonce_field = wp_nonce_field( 'hcaptcha_settings', 'hcaptcha_settings_nonce', true, false );
		$options     =
			[
				'hcaptcha_api_key'                  => 'Some API key',
				'hcaptcha_secret_key'               => 'Some secret key',
				'hcaptcha_theme'                    => 'Light',
				'hcaptcha_size'                     => 'Normal',
				'hcaptcha_language'                 => 'ru',
				'hcaptcha_nf_status'                => 'on',
				'hcaptcha_cf7_status'               => 'off',
				'hcaptcha_lf_status'                => '',
				'hcaptcha_rf_status'                => 'on',
				'hcaptcha_cmf_status'               => 'on',
				'hcaptcha_lpf_status'               => 'on',
				'hcaptcha_wc_login_status'          => 'on',
				'hcaptcha_wc_reg_status'            => 'on',
				'hcaptcha_wc_lost_pass_status'      => 'on',
				'hcaptcha_wc_checkout_status'       => 'on',
				'hcaptcha_bp_reg_status'            => 'on',
				'hcaptcha_bp_create_group_status'   => 'on',
				'hcaptcha_bbp_new_topic_status'     => 'on',
				'hcaptcha_bbp_reply_status'         => 'on',
				'hcaptcha_wpforms_status'           => 'on',
				'hcaptcha_wpforms_pro_status'       => 'on',
				'hcaptcha_wpforo_new_topic_status'  => 'on',
				'hcaptcha_wpforo_reply_status'      => 'on',
				'hcaptcha_mc4wp_status'             => 'on',
				'hcaptcha_jetpack_cf_status'        => 'on',
				'hcaptcha_subscribers_status'       => 'on',
				'hcaptcha_wc_wl_create_list_status' => 'on',
			];

		$expected = '	<div class="wrap">
					<div id="message" class="updated fade">
				<p>
					Settings Updated				</p>
			</div>
					<h3>hCaptcha Settings</h3>
		<h3>
			In order to use <a href="https://hCaptcha.com/?r=wp" target="_blank">hCaptcha</a> please register <a href="https://hCaptcha.com/?r=wp" target="_blank">here</a> to get your site key and secret key.		</h3>
		<form method="post" action="">
						<strong>
				hCaptcha Site Key			</strong>
			<br><br>
			<input
					type="text" size="50"
					id="hcaptcha_api_key"
					name="hcaptcha_api_key"
					value="' . $options['hcaptcha_api_key'] . '"/>
						<br><br>
						<strong>
				hCaptcha Secret Key			</strong>
			<br><br>
			<input
					type="password" size="50"
					id="hcaptcha_secret_key"
					name="hcaptcha_secret_key"
					value="' . $options['hcaptcha_secret_key'] . '"/>
						<br><br>
							<strong>hCaptcha Theme</strong>
				<br><br>
				<select
						id="hcaptcha_theme"
						name="hcaptcha_theme">
											<option
								value="light"
							>
							Light						</option>
												<option
								value="dark"
							>
							Dark						</option>
										</select>
				<br><br>
								<strong>hCaptcha Size</strong>
				<br><br>
				<select
						id="hcaptcha_size"
						name="hcaptcha_size">
											<option
								value="normal"
							>
							Normal						</option>
												<option
								value="compact"
							>
							Compact						</option>
												<option
								value="invisible"
							>
							Invisible						</option>
										</select>
				<br><br>
							<strong>
				Override Language Detection (optional)			</strong>
			<br><br>
			<input
					type="text" size="50"
					id="hcaptcha_language"
					name="hcaptcha_language"
					value="' . $options['hcaptcha_language'] . '"/>
			<br>Info on <a href="https://hcaptcha.com/docs/languages" target="_blank">language codes</a>.			<br><br>
				<strong>Enable/Disable Features</strong>
	<br><br>
				<input
					type="checkbox"
					id="hcaptcha_off_when_logged_in"
					name="hcaptcha_off_when_logged_in"
				/>
			&nbsp;
			<span>Turn off when logged in</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_recaptchacompat"
					name="hcaptcha_recaptchacompat"
				/>
			&nbsp;
			<span>Disable reCAPTCHA Compatibility (use if including both hCaptcha and reCAPTCHA on the same page)</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_lf_status"
					name="hcaptcha_lf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Login Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_rf_status"
					name="hcaptcha_rf_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on Register Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_lpf_status"
					name="hcaptcha_lpf_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on Lost Password Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_cmf_status"
					name="hcaptcha_cmf_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on Comment Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bbp_new_topic_status"
					name="hcaptcha_bbp_new_topic_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on bbPress New Topic Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bbp_reply_status"
					name="hcaptcha_bbp_reply_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on bbPress Reply Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bp_create_group_status"
					name="hcaptcha_bp_create_group_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on BuddyPress Create Group Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_bp_reg_status"
					name="hcaptcha_bp_reg_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on BuddyPress Registration Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_cf7_status"
					name="hcaptcha_cf7_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Contact Form 7</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_divi_cf_status"
					name="hcaptcha_divi_cf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Divi Contact Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_divi_lf_status"
					name="hcaptcha_divi_lf_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Divi Login Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_elementor__pro_form_status"
					name="hcaptcha_elementor__pro_form_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Elementor Pro Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_fluentform_status"
					name="hcaptcha_fluentform_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Fluent Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_gravityform_status"
					name="hcaptcha_gravityform_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on Gravity Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_jetpack_cf_status"
					name="hcaptcha_jetpack_cf_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on Jetpack Contact Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_mc4wp_status"
					name="hcaptcha_mc4wp_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on Mailchimp for WP Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_memberpress_register_status"
					name="hcaptcha_memberpress_register_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on MemberPress Registration Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_nf_status"
					name="hcaptcha_nf_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on Ninja Forms</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_subscribers_status"
					name="hcaptcha_subscribers_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on Subscribers Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_login_status"
					name="hcaptcha_wc_login_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Login Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_reg_status"
					name="hcaptcha_wc_reg_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Registration Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_lost_pass_status"
					name="hcaptcha_wc_lost_pass_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Lost Password Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_checkout_status"
					name="hcaptcha_wc_checkout_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Checkout Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_order_tracking_status"
					name="hcaptcha_wc_order_tracking_status"
				/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Order Tracking Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wc_wl_create_list_status"
					name="hcaptcha_wc_wl_create_list_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WooCommerce Wishlists Create List Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforms_status"
					name="hcaptcha_wpforms_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WPForms Lite</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforms_pro_status"
					name="hcaptcha_wpforms_pro_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WPForms Pro</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforo_new_topic_status"
					name="hcaptcha_wpforo_new_topic_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WPForo New Topic Form</span>
			<br><br>
						<input
					type="checkbox"
					id="hcaptcha_wpforo_reply_status"
					name="hcaptcha_wpforo_reply_status"
				 checked=\'checked\'/>
			&nbsp;
			<span>Enable hCaptcha on WPForo Reply Form</span>
			<br><br>
						<p>
				<input
						type="submit"
						value="Save hCaptcha Settings"
						class="button button-primary"
						name="submit"/>
			</p>
			' . $nonce_field . '		</form>
	</div>
	';

		$_POST['hcaptcha_settings_nonce'] = $nonce;
		$_POST['submit']                  = 'Save hCaptcha Settings';

		foreach ( $options as $option_name => $option_value ) {
			$_POST[ $option_name ] = $option_value;
		}

		foreach ( hcap_options() as $option_name => $option_value ) {
			self::assertFalse( get_option( $option_name ) );
		}

		ob_start();

		hcap_display_options_page();

		self::assertSame( $expected, ob_get_clean() );

		$options['hcaptcha_lf_status'] = 'off';

		foreach ( $options as $option_name => $option_value ) {
			self::assertSame( $option_value, get_option( $option_name ) );
		}
	}

	/**
	 * Test hcap_display_option().
	 *
	 * @param string $option_name Option name.
	 * @param array  $option      Option content.
	 * @param string $expected    Expected output.
	 *
	 * @dataProvider dp_test_hcap_display_option
	 */
	public function test_hcap_display_option( $option_name, $option, $expected ) {
		ob_start();

		hcap_display_option( $option_name, $option );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider for test_hcap_display_option().
	 *
	 * @return array
	 */
	public function dp_test_hcap_display_option() {
		return [
			'text'     => [
				'text_option',
				[
					'label' => 'Some text label',
					'type'  => 'text',
				],
				'			<strong>
				Some text label			</strong>
			<br><br>
			<input
					type="text" size="50"
					id="text_option"
					name="text_option"
					value=""/>
						<br><br>
			',
			],
			'password' => [
				'password_option',
				[
					'label' => 'Some password label',
					'type'  => 'password',
				],
				'			<strong>
				Some password label			</strong>
			<br><br>
			<input
					type="password" size="50"
					id="password_option"
					name="password_option"
					value=""/>
						<br><br>
			',
			],
			'number'   => [
				'number_option',
				[
					'label' => 'Some number label',
					'type'  => 'number',
				],
				'			<strong>
				Some number label			</strong>
			<br><br>
			<input
					type="number" size="50"
					id="number_option"
					name="number_option"
					value=""/>
						<br><br>
			',
			],
			'checkbox' => [
				'checkbox_option',
				[
					'label' => 'Some checkbox label',
					'type'  => 'checkbox',
				],
				'			<input
					type="checkbox"
					id="checkbox_option"
					name="checkbox_option"
				/>
			&nbsp;
			<span>Some checkbox label</span>
			<br><br>
			',
			],
			'select'   => [
				'select_option',
				[
					'label'   => 'Some select label',
					'type'    => 'select',
					'options' => [
						'light' => 'Light',
						'dark'  => 'Dark',
					],
				],
				'				<strong>Some select label</strong>
				<br><br>
				<select
						id="select_option"
						name="select_option">
											<option
								value="light"
							>
							Light						</option>
												<option
								value="dark"
							>
							Dark						</option>
										</select>
				<br><br>
				',
			],
			'wrong'    => [
				'wrong_option',
				[
					'label' => 'Some wrong label',
					'type'  => 'wrong',
				],
				'',
			],
		];
	}
}
