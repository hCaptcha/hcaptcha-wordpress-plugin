<?php
/**
 * FunctionsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Common;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test functions file.
 */
class FunctionsTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		global $hcaptcha_wordpress_plugin;

		$hcaptcha_wordpress_plugin->form_shown = false;

		parent::tearDown();
	}

	/**
	 * Test hcap_form().
	 */
	public function test_hcap_form() {
		self::assertSame( $this->get_hcap_form(), hcap_form() );
	}

	/**
	 * Test hcap_form_display().
	 */
	public function test_hcap_form_display() {
		global $hcaptcha_wordpress_plugin;

		self::assertFalse( $hcaptcha_wordpress_plugin->form_shown );

		ob_start();
		hcap_form_display();

		self::assertSame( $this->get_hcap_form(), ob_get_clean() );
		self::assertTrue( $hcaptcha_wordpress_plugin->form_shown );
	}

	/**
	 * Test hcap_shortcode().
	 */
	public function test_hcap_shortcode() {
		$content  = 'some content';
		$filtered = ' filtered ';
		$expected = $content . $filtered . $this->get_hcap_form();

		add_filter(
			'hcap_hcaptcha_content',
			function ( $hcaptcha_content ) use ( $filtered ) {
				return $filtered . $hcaptcha_content;
			}
		);

		self::assertSame( $expected, hcap_shortcode( $content ) );
	}

	/**
	 * Test hcap_options().
	 */
	public function test_hcap_options() {
		$expected = [
			'hcaptcha_api_key'                  =>
				[
					'label' => 'hCaptcha Site Key',
					'type'  => 'text',
				],
			'hcaptcha_secret_key'               =>
				[
					'label' => 'hCaptcha Secret Key',
					'type'  => 'password',
				],
			'hcaptcha_theme'                    =>
				[
					'label'   => 'hCaptcha Theme',
					'type'    => 'select',
					'options' =>
						[
							'light' => 'Light',
							'dark'  => 'Dark',
						],
				],
			'hcaptcha_size'                     =>
				[
					'label'   => 'hCaptcha Size',
					'type'    => 'select',
					'options' =>
						[
							'normal'  => 'Normal',
							'compact' => 'Compact',
						],
				],
			'hcaptcha_language'                 =>
				[
					'label'       => 'Override Language Detection (optional)',
					'type'        => 'text',
					'description' => 'Info on <a href="https://hcaptcha.com/docs/languages" target="_blank">language codes</a>.',
				],
			'hcaptcha_off_when_logged_in'       => [
				'label' => 'Turn off when logged in',
				'type'  => 'checkbox',
			],
			'hcaptcha_recaptchacompat'          => [
				'label' => 'Disable reCAPTCHA Compatibility (use if including both hCaptcha and reCAPTCHA on the same page)',
				'type'  => 'checkbox',
			],
			'hcaptcha_nf_status'                =>
				[
					'label' => 'Enable Ninja Forms Addon',
					'type'  => 'checkbox',
				],
			'hcaptcha_cf7_status'               =>
				[
					'label' => 'Enable Contact Form 7 Addon',
					'type'  => 'checkbox',
				],
			'hcaptcha_lf_status'                =>
				[
					'label' => 'Enable hCaptcha on Login Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_rf_status'                =>
				[
					'label' => 'Enable hCaptcha on Register Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_cmf_status'               =>
				[
					'label' => 'Enable hCaptcha on Comment Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_lpf_status'               =>
				[
					'label' => 'Enable hCaptcha on Lost Password Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_login_status'          =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Login Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_reg_status'            =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Registration Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_lost_pass_status'      =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Lost Password Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_checkout_status'       =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Checkout Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bp_reg_status'            =>
				[
					'label' => 'Enable hCaptcha on Buddypress Registration Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bp_create_group_status'   =>
				[
					'label' => 'Enable hCaptcha on BuddyPress Create Group Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bbp_new_topic_status'     =>
				[
					'label' => 'Enable hCaptcha on bbPress New Topic Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bbp_reply_status'         =>
				[
					'label' => 'Enable hCaptcha on bbPress Reply Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforms_status'           =>
				[
					'label' => 'Enable hCaptcha on WPForms Lite',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforms_pro_status'       =>
				[
					'label' => 'Enable hCaptcha on WPForms Pro',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforo_new_topic_status'  =>
				[
					'label' => 'Enable hCaptcha on WPForo New Topic Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforo_reply_status'      =>
				[
					'label' => 'Enable hCaptcha on WPForo Reply Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_mc4wp_status'             =>
				[
					'label' => 'Enable hCaptcha on Mailchimp for WP Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_jetpack_cf_status'        =>
				[
					'label' => 'Enable hCaptcha on Jetpack Contact Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_subscribers_status'       =>
				[
					'label' => 'Enable hCaptcha on Subscribers Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_wl_create_list_status' =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Wishlists Create List Form',
					'type'  => 'checkbox',
				],
		];

		self::assertSame( $expected, hcap_options() );
	}
}
