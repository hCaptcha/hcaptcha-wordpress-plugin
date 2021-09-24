<?php
/**
 * FunctionsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Common;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test functions file.
 *
 * @group functions
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

		$action = 'some_action';
		$name   = 'some_name';
		$auto   = true;

		self::assertSame( $this->get_hcap_form( $action, $name, $auto ), hcap_form( $action, $name, $auto ) );
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

		$action = 'some_action';
		$name   = 'some_name';
		$auto   = true;

		ob_start();
		hcap_form_display( $action, $name, $auto );
		self::assertSame( $this->get_hcap_form( $action, $name, $auto ), ob_get_clean() );

		update_option( 'hcaptcha_size', 'invisible' );
		ob_start();
		hcap_form_display( $action, $name, $auto );
		self::assertSame( $this->get_hcap_form( $action, $name, $auto, true ), ob_get_clean() );
	}

	/**
	 * Test hcap_shortcode().
	 *
	 * @param string $action Action name for wp_nonce_field.
	 * @param string $name   Nonce name for wp_nonce_field.
	 * @param string $auto   Auto argument.
	 *
	 * @dataProvider dp_test_hcap_shortcode
	 */
	public function test_hcap_shortcode( $action, $name, $auto ) {
		$filtered = ' filtered ';

		$form_action = empty( $action ) ? 'hcaptcha_action' : $action;
		$form_name   = empty( $name ) ? 'hcaptcha_nonce' : $name;
		$form_auto   = filter_var( $auto, FILTER_VALIDATE_BOOLEAN );

		$expected = $filtered . $this->get_hcap_form( $form_action, $form_name, $form_auto );

		add_filter(
			'hcap_hcaptcha_content',
			function ( $hcaptcha_content ) use ( $filtered ) {
				return $filtered . $hcaptcha_content;
			}
		);

		$shortcode = '[hcaptcha';

		$shortcode .= empty( $action ) ? '' : ' action="' . $action . '"';
		$shortcode .= empty( $name ) ? '' : ' name="' . $name . '"';
		$shortcode .= empty( $auto ) ? '' : ' auto="' . $auto . '"';

		$shortcode .= ']';

		self::assertSame( $expected, do_shortcode( $shortcode ) );
	}

	/**
	 * Data provider for test_hcap_shortcode().
	 *
	 * @return array
	 */
	public function dp_test_hcap_shortcode() {
		return [
			'no arguments'   => [ '', '', '' ],
			'action only'    => [ 'some_action', '', '' ],
			'name only'      => [ '', 'some_name', '' ],
			'with arguments' => [ 'some_action', 'some_name', '' ],
			'auto false'     => [ 'some_action', 'some_name', 'false' ],
			'auto 0'         => [ 'some_action', 'some_name', 'false' ],
			'auto wrong'     => [ 'some_action', 'some_name', 'false' ],
			'auto true'      => [ 'some_action', 'some_name', 'true' ],
			'auto 1'         => [ 'some_action', 'some_name', '1' ],
		];
	}

	/**
	 * Test hcap_options().
	 */
	public function test_hcap_options() {
		$expected = [
			'hcaptcha_api_key'                     =>
				[
					'label' => 'hCaptcha Site Key',
					'type'  => 'text',
				],
			'hcaptcha_secret_key'                  =>
				[
					'label' => 'hCaptcha Secret Key',
					'type'  => 'password',
				],
			'hcaptcha_theme'                       =>
				[
					'label'   => 'hCaptcha Theme',
					'type'    => 'select',
					'options' =>
						[
							'light' => 'Light',
							'dark'  => 'Dark',
						],
				],
			'hcaptcha_size'                        =>
				[
					'label'   => 'hCaptcha Size',
					'type'    => 'select',
					'options' =>
						[
							'normal'    => 'Normal',
							'compact'   => 'Compact',
							'invisible' => 'Invisible',
						],
				],
			'hcaptcha_language'                    =>
				[
					'label'       => 'Override Language Detection (optional)',
					'type'        => 'text',
					'description' => 'Info on <a href="https://hcaptcha.com/docs/languages" target="_blank">language codes</a>.',
				],
			'hcaptcha_off_when_logged_in'          => [
				'label' => 'Turn off when logged in',
				'type'  => 'checkbox',
			],
			'hcaptcha_recaptchacompat'             => [
				'label' => 'Disable reCAPTCHA Compatibility (use if including both hCaptcha and reCAPTCHA on the same page)',
				'type'  => 'checkbox',
			],
			'hcaptcha_lf_status'                   =>
				[
					'label' => 'Enable hCaptcha on Login Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_rf_status'                   =>
				[
					'label' => 'Enable hCaptcha on Register Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_lpf_status'                  =>
				[
					'label' => 'Enable hCaptcha on Lost Password Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_cmf_status'                  =>
				[
					'label' => 'Enable hCaptcha on Comment Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bbp_new_topic_status'        =>
				[
					'label' => 'Enable hCaptcha on bbPress New Topic Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bbp_reply_status'            =>
				[
					'label' => 'Enable hCaptcha on bbPress Reply Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bp_reg_status'               =>
				[
					'label' => 'Enable hCaptcha on Buddypress Registration Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_bp_create_group_status'      =>
				[
					'label' => 'Enable hCaptcha on BuddyPress Create Group Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_cf7_status'                  =>
				[
					'label' => 'Enable hCaptcha on Contact Form 7',
					'type'  => 'checkbox',
				],
			'hcaptcha_elementor__pro_form_status'  => [
				'label' => 'Enable hCaptcha on Elementor Pro Form',
				'type'  => 'checkbox',
			],
			'hcaptcha_jetpack_cf_status'           =>
				[
					'label' => 'Enable hCaptcha on Jetpack Contact Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_mc4wp_status'                =>
				[
					'label' => 'Enable hCaptcha on Mailchimp for WP Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_memberpress_register_status' =>
				[
					'label' => 'Enable hCaptcha on MemberPress Registration Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_nf_status'                   =>
				[
					'label' => 'Enable hCaptcha on Ninja Forms',
					'type'  => 'checkbox',
				],
			'hcaptcha_subscribers_status'          =>
				[
					'label' => 'Enable hCaptcha on Subscribers Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_login_status'             =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Login Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_reg_status'               =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Registration Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_lost_pass_status'         =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Lost Password Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_checkout_status'          =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Checkout Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_order_tracking_status'    =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Order Tracking Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wc_wl_create_list_status'    =>
				[
					'label' => 'Enable hCaptcha on WooCommerce Wishlists Create List Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforms_status'              =>
				[
					'label' => 'Enable hCaptcha on WPForms Lite',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforms_pro_status'          =>
				[
					'label' => 'Enable hCaptcha on WPForms Pro',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforo_new_topic_status'     =>
				[
					'label' => 'Enable hCaptcha on WPForo New Topic Form',
					'type'  => 'checkbox',
				],
			'hcaptcha_wpforo_reply_status'         =>
				[
					'label' => 'Enable hCaptcha on WPForo Reply Form',
					'type'  => 'checkbox',
				],
		];

		self::assertSame( $expected, hcap_options() );
	}
}
