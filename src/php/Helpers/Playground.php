<?php
/**
 * Playground class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\Integrations;
use WP_Admin_Bar;
use WP_Error;
use WP_Theme;

/**
 * Class Playground.
 */
class Playground {
	/**
	 * Admin script handle.
	 */
	private const HANDLE = 'hcaptcha-playground';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaPlaygroundObject';

	/**
	 * Update menu action.
	 */
	private const UPDATE_MENU_ACTION = 'hcaptcha-playground-update-menu';

	/**
	 * Priority of the plugins_loaded action to load Playground.
	 */
	public const LOAD_PRIORITY = Migrations::LOAD_PRIORITY + 5;

	/**
	 * Transient key for storing Playground data.
	 */
	private const PLAYGROUND_DATA = 'hcaptcha_playground_data';

	/**
	 * Menu ID for the admin bar menu.
	 */
	private const HCAPTCHA_MENU_ID = 'hcaptcha-menu';

	/**
	 * The hCaptcha settings menu.
	 */
	private const HCAPTCHA_MENU_HCAPTCHA_SETTINGS = 'hcaptcha-menu-hcaptcha-settings';

	/**
	 * WordPress group ID for the admin bar menu.
	 */
	private const HCAPTCHA_MENU_WORDPRESS_ID = 'hcaptcha-menu-wordpress';

	/**
	 * Woocommerce group ID for the admin bar menu.
	 */
	private const HCAPTCHA_MENU_WOOCOMMERCE_ID = 'hcaptcha-menu-woocommerce';

	/**
	 * Playground data.
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * Whether to renew forms and pages upon plugin/theme activation.
	 *
	 * @var bool
	 */
	private bool $renew;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	private function init(): void {
		if ( ! $this->is_wp_playground() ) {
			return;
		}

		// Renew forms and pages only locally.
		$this->renew = ! $this->is_playground_host();

		$this->data = get_transient( self::PLAYGROUND_DATA ) ?: [];

		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'setup_playground' ], self::LOAD_PRIORITY );
		add_action( 'activated_plugin', [ $this, 'setup_plugin' ], 10, 2 );
		add_action( 'switch_theme', [ $this, 'setup_theme' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'head_styles' ] );
		add_action( 'admin_head', [ $this, 'head_styles' ] );
		add_action( 'login_head', [ $this, 'head_styles' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 10000 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::UPDATE_MENU_ACTION, [ $this, 'update_menu' ] );

		// Do not send hCaptcha statistics from Playground.
		add_filter( 'hcap_allow_send_plugin_stats', '__return_false' );

		// Always show the admin bar.
		add_filter( 'show_admin_bar', '__return_true' );

		// Include styles/script of the bar in the <head> of the login page.
		add_action(
			'login_head',
			static function () {
				wp_enqueue_style( 'admin-bar' );
				wp_enqueue_script( 'admin-bar' );
			}
		);

		// Render the admin bar on the login page.
		add_action(
			'login_footer',
			static function () {
				if ( ! function_exists( 'wp_admin_bar_render' ) ) {
					require_once ABSPATH . WPINC . '/admin-bar.php';
				}

				global $wp_admin_bar;

				if ( ! is_object( $wp_admin_bar ) ) {
					require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$wp_admin_bar = new WP_Admin_Bar();

					$wp_admin_bar->initialize();
					$wp_admin_bar->add_menus();
				}

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				do_action( 'admin_bar_menu', $wp_admin_bar );

				// Output markup.
				$wp_admin_bar->render();
			}
		);

		// Prevent mail sending errors.
		add_filter( 'wpcf7_skip_mail', '__return_true' );
		add_filter( 'pre_wp_mail', '__return_true' );

		// Do not use WooCommerce session-based nonce. Otherwise, WC login does not work on the Playground.
		add_action(
			'init',
			static function () {
				if ( function_exists( 'WC' ) && WC()->session ) {
					// WooCommerce adds nonce_user_logged_out for not logged-in users.
					remove_filter( 'nonce_user_logged_out', [ WC()->session, 'maybe_update_nonce_user_logged_out' ] );
				}
			},
			20
		);
	}

	/**
	 * Set up the WP Playground.
	 *
	 * @return void
	 */
	public function setup_playground(): void {
		if ( $this->data['setup'] ?? false ) {
			return;
		}

		$this->setup_permalinks();
		$this->setup_settings();

		$this->data['setup'] = true;

		set_transient( self::PLAYGROUND_DATA, $this->data );
	}

	/**
	 * Setup plugin.
	 *
	 * @param string $plugin       Path to the plugin file relative to the plugins' directory.
	 * @param bool   $network_wide Whether to enable the plugin network-wide.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function setup_plugin( string $plugin, bool $network_wide ): void {
		if ( ! $this->renew && ( $this->data['plugins'][ $plugin ] ?? false ) ) {
			return;
		}

		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
		$map = [
			'contact-form-7/wp-contact-form-7.php'                              => [ $this, 'setup_contact_form_7' ],
			'elementor-pro/elementor-pro.php'                                   => [ $this, 'setup_elementor_pro' ],
			'essential-addons-elementor/essential_adons_elementor.php'          => [ $this, 'setup_essential_addons' ],
			'essential-addons-for-elementor-lite/essential_adons_elementor.php' => [ $this, 'setup_essential_addons' ],
			'jetpack/jetpack.php'                                               => [ $this, 'setup_jetpack' ],
			'mailchimp-for-wp/mailchimp-for-wp.php'                             => [ $this, 'setup_mailchimp' ],
			'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php'   => [ $this, 'setup_spectra' ],
			'ultimate-elementor/ultimate-elementor.php'                         => [ $this, 'setup_ultimate_addons' ],
			'woocommerce/woocommerce.php'                                       => [ $this, 'setup_woocommerce' ],
			'wpforms/wpforms.php'                                               => [ $this, 'setup_wpforms' ],
			'wpforms-lite/wpforms.php'                                          => [ $this, 'setup_wpforms' ],
		];
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow

		foreach ( $map as $key => $method ) {
			if ( $plugin === $key ) {
				$method();
			}
		}

		$this->data['plugins'][ $plugin ] = true;

		set_transient( self::PLAYGROUND_DATA, $this->data );
	}

	/**
	 * Setup Contact Form 7.
	 *
	 * @return void
	 */
	private function setup_contact_form_7(): void {
		// Find the preinstalled Contact Form 7 form.
		$forms = get_posts(
			[
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			]
		);

		$form = reset( $forms );

		// Create a new page with the Contact Form 7 shortcode.
		$this->insert_post(
			[
				'title'   => 'Contact Form 7 Test Page',
				'name'    => 'contact-form-7-test',
				'content' => '[contact-form-7 id="' . (int) $form->ID . '"]',
			]
		);
	}


	/**
	 * Setup Elementor Pro.
	 *
	 * @return void
	 */
	private function setup_elementor_pro(): void {
		// Create a new page with the Elementor form.
		$this->insert_post(
			[
				'title'      => 'Elementor Pro Test Page',
				'name'       => 'elementor-pro-test',
				'content'    => '',
				'meta_input' => [
					'_elementor_data'      => '[{"id":"a21d3c3","elType":"section","settings":[],"elements":[{"id":"5097cb3","elType":"column","settings":{"_column_size":100},"elements":[{"id":"f9221e1","elType":"widget","settings":{"title":"Elementor Pro Form"},"elements":[],"widgetType":"heading"}],"isInner":false}],"isInner":false},{"id":"1ed4d3b","elType":"section","settings":[],"elements":[{"id":"85c33a9","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"687b087","elType":"widget","settings":{"form_name":"New Form","form_fields":[{"custom_id":"name","field_label":"Name","placeholder":"Name","dynamic":{"active":true},"_id":"986c004"},{"custom_id":"email","field_type":"email","required":"true","field_label":"Email","placeholder":"Email","_id":"3453398"},{"custom_id":"message","field_type":"textarea","field_label":"Message","placeholder":"Message","_id":"134e6d7"},{"_id":"c9d1c91","field_type":"hcaptcha","custom_id":"field_c9d1c91"}],"step_next_label":"Next","step_previous_label":"Previous","button_text":"Send","email_to":"info@kagg.eu","email_subject":"New message from &quot;test&quot;","email_content":"[all-fields]","email_from":"email@https:\/\/test.test","email_from_name":"test","email_to_2":"info@kagg.eu","email_subject_2":"New message from &quot;test&quot;","email_content_2":"[all-fields]","email_from_2":"email@https:\/\/test.test","email_from_name_2":"test","email_reply_to_2":"info@kagg.eu","mailchimp_fields_map":[],"drip_fields_map":[],"activecampaign_fields_map":[],"getresponse_fields_map":[],"convertkit_fields_map":[],"mailerlite_fields_map":[],"success_message":"The form was sent successfully.","error_message":"An error occurred.","required_field_message":"This field is required.","invalid_message":"There&#039;s something wrong. The form is invalid.","form_id":"test_form","server_message":"Your submission failed because of a server error."},"elements":[],"widgetType":"form"}],"isInner":false}],"isInner":false}]',
					'_elementor_edit_mode' => 'builder',
				],
			]
		);
	}

	/**
	 * Setup Essential Addons.
	 *
	 * @return void
	 */
	private function setup_essential_addons(): void {
		// Create a new page with the Essential Addons Login form.
		$this->insert_post(
			[
				'title'      => 'Essential Addons Test Page',
				'name'       => 'essential-addons-test',
				'content'    => '',
				'meta_input' => [
					'_elementor_data'       => '[{"id":"f15315f","elType":"section","settings":{"eael_image_masking_custom_clip_path":"clip-path: polygon(50% 0%, 80% 10%, 100% 35%, 100% 70%, 80% 90%, 50% 100%, 20% 90%, 0% 70%, 0% 35%, 20% 10%);","eael_image_masking_custom_clip_path_hover":"clip-path: polygon(50% 0%, 80% 10%, 100% 35%, 100% 70%, 80% 90%, 50% 100%, 20% 90%, 0% 70%, 0% 35%, 20% 10%);"},"elements":[{"id":"f174b30","elType":"column","settings":{"_column_size":100,"_inline_size":null,"eael_image_masking_custom_clip_path":"clip-path: polygon(50% 0%, 80% 10%, 100% 35%, 100% 70%, 80% 90%, 50% 100%, 20% 90%, 0% 70%, 0% 35%, 20% 10%);","eael_image_masking_custom_clip_path_hover":"clip-path: polygon(50% 0%, 80% 10%, 100% 35%, 100% 70%, 80% 90%, 50% 100%, 20% 90%, 0% 70%, 0% 35%, 20% 10%);"},"elements":[{"id":"9303ea8","elType":"widget","settings":{"log_out_link_text":"You are already logged in as [username]. ([logout_link])","lost_password_text":"Forgot Password?","remember_text":"Remember Me","registration_link_text":" \nRegister Now","login_link_text":" \nSign In","login_link_text_lostpassword":" \nSign In","login_user_label":"Username or Email Address","login_password_label":"Password","login_user_placeholder":"Username or Email Address","login_password_placeholder":"Password","login_button_text":"Log In","register_fields":[{"field_type":"user_name","field_label":"Username","placeholder":"Username","_id":"1ed16d5"},{"field_type":"email","field_label":"Email","placeholder":"Email","required":"yes","_id":"57b9cfb"},{"field_type":"password","field_label":"Password","placeholder":"Password","required":"yes","_id":"113ef0b"}],"reg_button_text":"Register","reg_email_subject":"Thank you for registering on \"test\"!","reg_admin_email_subject":"[\"test\"] New User Registration","lostpassword_user_label":"Username or Email Address","lostpassword_user_placeholder":"Username or Email Address","lostpassword_button_text":"Reset Password","lostpassword_email_subject":"Password Reset Confirmation","lostpassword_email_message_reset_link_text":"Click here to reset your password","resetpassword_password_label":"New Password","resetpassword_confirm_password_label":"Confirm New Password","resetpassword_password_placeholder":"New Password","resetpassword_confirm_password_placeholder":"Confirm New Password","resetpassword_button_text":"Save Password","acceptance_label":"I Accept\n the Terms and Conditions.","err_email":"You have used an invalid email","err_email_missing":"Email is missing or Invalid","err_email_used":"The provided email is already registered with other account. Please login or reset password or use another email.","err_username":"You have used an invalid username","err_username_used":"Invalid username provided or the username already registered.","err_pass":"Your password is invalid.","err_conf_pass":"Your confirmed password did not match","err_loggedin":"You are already logged in","err_recaptcha":"You did not pass reCAPTCHA challenge.","err_cloudflare_turnstile":"You did not pass Cloudflare Turnstile challenge.","err_reset_password_key_expired":"Your password reset link appears to be invalid. Please request a new link.","err_tc":"You did not accept the Terms and Conditions. Please accept it and try again.","err_unknown":"Something went wrong!","err_phone_number_missing":"Phone number is missing","err_phone_number_invalid":"Invalid phone number provided","success_login":"You have logged in successfully","success_register":"Registration completed successfully, Check your inbox for password if you did not provided while registering.","success_lostpassword":"Check your email for the confirmation link.","success_resetpassword":"Your password has been reset.","rmark_sign":"*","eael_image_masking_custom_clip_path":"clip-path: polygon(50% 0%, 80% 10%, 100% 35%, 100% 70%, 80% 90%, 50% 100%, 20% 90%, 0% 70%, 0% 35%, 20% 10%);","eael_image_masking_custom_clip_path_hover":"clip-path: polygon(50% 0%, 80% 10%, 100% 35%, 100% 70%, 80% 90%, 50% 100%, 20% 90%, 0% 70%, 0% 35%, 20% 10%);"},"elements":[],"widgetType":"eael-login-register"}],"isInner":false}],"isInner":false}]',
					'_elementor_edit_mode'  => 'builder',
					'_eael_widget_elements' => maybe_unserialize( 'a:1:{s:14:"login-register";s:14:"login-register";}' ),
				],
			]
		);
	}

	/**
	 * Setup Jetpack.
	 *
	 * @return void
	 */
	private function setup_jetpack(): void {
		// Create a new page with a Jetpack form.
		$this->insert_post(
			[
				'title'   => 'Jetpack Test Page',
				'name'    => 'jetpack-test',
				'content' => '
<!-- wp:jetpack/contact-form {"className":"is-style-default"} -->
<div class="wp-block-jetpack-contact-form is-style-default">
<!-- wp:jetpack/field-name {"required":true} /-->
<!-- wp:jetpack/field-email {"required":true,"width":50} /-->
<!-- wp:jetpack/field-textarea {"label":""} /-->
<!-- wp:jetpack/button {"element":"button","text":"Contact Us","lock":{"move":false,"remove":true}} /-->
</div>
<!-- /wp:jetpack/contact-form -->
',
			]
		);

		$active_modules = get_option( 'jetpack_active_modules', [] );
		$active_modules = array_merge( $active_modules, [ 'blocks', 'contact-form' ] );

		update_option( 'jetpack_active_modules', array_unique( $active_modules ) );
	}

	/**
	 * Setup Mailchimp.
	 *
	 * @return void
	 */
	private function setup_mailchimp(): void {
		// Create a new page with a Mailchimp form.
		$form_id = $this->insert_post(
			[
				'title'      => 'Mailchimp Test Form',
				'name'       => 'mailchimp-test-form',
				'content'    => '
<p>
	<label>Email address: 
		<input type="email" name="EMAIL" placeholder="Your email address" required />
	</label>
</p>
<p>
	<input type="submit" value="Sign up" />
</p>
',
				'post_type'  => 'mc4wp-form',
				'meta_input' => [
					'_mc4wp_settings'             => maybe_unserialize( 'a:9:{s:5:"lists";a:1:{i:0;s:10:"bd06792328";}s:15:"required_fields";s:5:"EMAIL";s:12:"double_optin";s:1:"1";s:15:"update_existing";s:1:"0";s:17:"replace_interests";s:1:"1";s:15:"subscriber_tags";s:0:"";s:18:"hide_after_success";s:1:"0";s:8:"redirect";s:0:"";s:3:"css";s:1:"0";}' ),
					'text_subscribed'             => 'Thank you, your sign-up request was successful! Please check your email inbox to confirm.',
					'text_invalid_email'          => 'Please provide a valid email address.',
					'text_required_field_missing' => 'Please fill in the required fields.',
					'text_already_subscribed'     => 'Given email address is already subscribed, thank you!',
					'text_error'                  => 'Oops. Something went wrong. Please try again later.',
					'text_unsubscribed'           => 'You were successfully unsubscribed.',
					'text_not_subscribed'         => 'Given email address is not subscribed.',
					'text_no_lists_selected'      => 'Please select at least one list.',
					'text_updated'                => 'Thank you, your records have been updated!',
				],
			]
		);

		// Create a new page with a Mailchimp form.
		$this->insert_post(
			[
				'title'   => 'Mailchimp Test Page',
				'name'    => 'mailchimp-test',
				'content' => '[mc4wp_form id="' . $form_id . '"]',
			]
		);
	}

	/**
	 * Setup Spectra.
	 *
	 * @return void
	 */
	private function setup_spectra(): void {
		// Create a new page with a Spectra form.
		$this->insert_post(
			[
				'title'   => 'Spectra Test Page',
				'name'    => 'spectra-test',
				'content' => '
<!-- wp:uagb/forms {"block_id":"f89cebda","labelAlignment":"left","fieldBorderTopWidth":1,"fieldBorderLeftWidth":1,"fieldBorderRightWidth":1,"fieldBorderBottomWidth":1,"fieldBorderTopLeftRadius":3,"fieldBorderTopRightRadius":3,"fieldBorderBottomLeftRadius":3,"fieldBorderBottomRightRadius":3,"fieldBorderStyle":"solid","fieldBorderColor":"#BDBDBD","checkBoxToggleBorderTopWidth":1,"checkBoxToggleBorderLeftWidth":1,"checkBoxToggleBorderRightWidth":1,"checkBoxToggleBorderBottomWidth":1,"checkBoxToggleBorderTopLeftRadius":3,"checkBoxToggleBorderTopRightRadius":3,"checkBoxToggleBorderBottomLeftRadius":3,"checkBoxToggleBorderBottomRightRadius":3,"checkBoxToggleBorderStyle":"solid","checkBoxToggleBorderColor":"#BDBDBD","btnBorderTopLeftRadius":3,"btnBorderTopRightRadius":3,"btnBorderBottomLeftRadius":3,"btnBorderBottomRightRadius":3,"variationSelected":true} -->
<div class="wp-block-uagb-forms uagb-forms__outer-wrap uagb-block-f89cebda uagb-forms__medium-btn">
	<form class="uagb-forms-main-form" method="post" autocomplete="on" name="uagb-form-f89cebda">

	<!-- wp:uagb/forms-name {"block_id":"046ed4b7","nameRequired":true,"name":"First Name","placeholder":"John","autocomplete":"given-name"} -->
	<div class="wp-block-uagb-forms-name uagb-forms-name-wrap uagb-forms-field-set uagb-block-046ed4b7">
		<div class="uagb-forms-name-label required uagb-forms-input-label" id="046ed4b7">First Name</div>
		<input type="text" placeholder="John" required class="uagb-forms-name-input uagb-forms-input" name="046ed4b7" autocomplete="given-name"/>
	</div>
	<!-- /wp:uagb/forms-name -->

	<!-- wp:uagb/forms-name {"block_id":"75b30804","nameRequired":true,"name":"Last Name","placeholder":"Doe","autocomplete":"family-name"} -->
	<div class="wp-block-uagb-forms-name uagb-forms-name-wrap uagb-forms-field-set uagb-block-75b30804">
		<div class="uagb-forms-name-label required uagb-forms-input-label" id="75b30804">Last Name</div>
		<input type="text" placeholder="Doe" required class="uagb-forms-name-input uagb-forms-input" name="75b30804" autocomplete="family-name"/>
	</div>
	<!-- /wp:uagb/forms-name -->

	<!-- wp:uagb/forms-email {"block_id":"9f2177c9"} -->
	<div class="wp-block-uagb-forms-email uagb-forms-email-wrap uagb-forms-field-set uagb-block-9f2177c9">
		<div class="uagb-forms-email-label  uagb-forms-input-label" id="9f2177c9">Email</div>
		<input type="email" class="uagb-forms-email-input uagb-forms-input" placeholder="example@mail.com" name="9f2177c9" autocomplete="email"/>
	</div>
	<!-- /wp:uagb/forms-email -->

	<!-- wp:uagb/forms-textarea {"block_id":"147f2552","textareaRequired":true} -->
	<div class="wp-block-uagb-forms-textarea uagb-forms-textarea-wrap uagb-forms-field-set uagb-block-147f2552">
		<div class="uagb-forms-textarea-label required uagb-forms-input-label" id="147f2552">Message</div>
		<textarea required class="uagb-forms-textarea-input uagb-forms-input" rows="4" placeholder="Enter your message" name="147f2552" autocomplete="off"></textarea>
	</div>
	<!-- /wp:uagb/forms-textarea -->

	<div class="uagb-forms-form-hidden-data">
		<input type="hidden" class="uagb_forms_form_label" value="Spectra Form"/>
		<input type="hidden" class="uagb_forms_form_id" value="uagb-form-f89cebda"/>
	</div>

	<div class="uagb-form-reacaptcha-error-f89cebda"></div>

	<div class="uagb-forms-main-submit-button-wrap wp-block-button">
		<button class="uagb-forms-main-submit-button wp-block-button__link">
			<div class="uagb-forms-main-submit-button-text">Submit</div>
		</button>
	</div>

	</form>

	<div class="uagb-forms-success-message-f89cebda uagb-forms-submit-message-hide">
		<span>The form has been submitted successfully!</span>
	</div>

	<div class="uagb-forms-failed-message-f89cebda uagb-forms-submit-message-hide">
		<span>There has been some error while submitting the form. Please verify all form fields again.</span>
	</div>
</div>
<!-- /wp:uagb/forms -->
',
			]
		);
	}

	/**
	 * Setup Ultimate Addons.
	 *
	 * @return void
	 */
	private function setup_ultimate_addons(): void {
		// Create a new page with the `Ultimate Addons` Login form.
		$this->insert_post(
			[
				'title'      => 'Ultimate Addons Test Page',
				'name'       => 'ultimate-addons-test',
				'content'    => '',
				'meta_input' => [
					'_elementor_data'      => '[{"id":"f64215e","elType":"section","settings":[],"elements":[{"id":"53fddb6","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"29808ca","elType":"widget","settings":{"user_label":"Username or Email Address","password_label":"Password","user_placeholder":"Username or Email Address","password_placeholder":"Password","separator_line_text":"Or","button_text":"Log In","show_register_text":"Register","show_lost_password_text":"Lost your password?","footer_divider":"|"},"elements":[],"widgetType":"uael-login-form"}],"isInner":false}],"isInner":false}]',
					'_elementor_edit_mode' => 'builder',
				],
			]
		);
	}

	/**
	 * Setup WooCommerce.
	 *
	 * @return void
	 */
	private function setup_woocommerce(): void {
		// Create a new page with the WooCommerce Order Tracking shortcode.
		$this->insert_post(
			[
				'title'   => 'WooCommerce Order Tracking Test Page',
				'name'    => 'wc-order-tracking-test',
				'content' => '[woocommerce_order_tracking]',
			]
		);
	}

	/**
	 * Setup WPForms.
	 *
	 * @return void
	 */
	private function setup_wpforms(): void {
		// Create a new WPForms form.
		$wpforms_form_id = $this->insert_post(
			[
				'title'     => 'WPForms Test Form',
				'name'      => 'wpforms-test-form',
				'post_type' => 'wpforms',
			]
		);

		// We need to insert form ID into the content.
		wp_update_post(
			[
				'ID'           => $wpforms_form_id,
				'post_content' => '{"fields": {"1": {"id": "1", "type": "name", "label": "Name", "format": "first-last", "description": "", "required": "1", "size": "medium", "simple_placeholder": "", "simple_default": "", "first_placeholder": "", "first_default": "", "middle_placeholder": "", "middle_default": "", "last_placeholder": "", "last_default": "", "css": ""}, "2": {"id": "2", "type": "email", "label": "Email", "description": "", "required": "1", "size": "medium", "placeholder": "", "confirmation_placeholder": "", "default_value": false, "filter_type": "", "allowlist": "", "denylist": "", "css": ""}, "3": {"id": "3", "type": "textarea", "label": "Comment or Message", "description": "", "size": "medium", "placeholder": "", "limit_count": "1", "limit_mode": "characters", "default_value": "", "css": ""}}, "id": ' . $wpforms_form_id . ', "field_id": 4, "settings": {"form_title": "Simple Contact Form", "form_desc": "", "submit_text": "Submit", "submit_text_processing": "Sending...", "form_class": "", "submit_class": "", "ajax_submit": "1", "notification_enable": "1", "notifications": {"1": {"enable": "1", "notification_name": "Default Notification", "email": "{admin_email}", "subject": "New Entry: Simple Contact Form (ID #5717)", "sender_name": "test", "sender_address": "{admin_email}", "replyto": "", "message": "{all_fields}"}}, "confirmations": {"1": {"name": "Default Confirmation", "type": "message", "message": "<p>Thanks for contacting us! We will be in touch with you shortly.<\\/p>", "message_scroll": "1", "page": "4992", "redirect": "", "message_entry_preview_style": "basic"}}, "antispam_v3": "1", "store_spam_entries": "1", "anti_spam": {"time_limit": {"enable": "1", "duration": "2"}, "filtering_store_spam": "1", "country_filter": {"action": "allow", "country_codes": [], "message": "Sorry, this form does not accept submissions from your country."}, "keyword_filter": {"message": "Sorry, your message cannot be submitted because it contains prohibited words."}}, "form_tags": []}, "search_terms": "", "providers": {"constant-contact-v3": []}}',
			]
		);

		// Create a new page with the WPForms form.
		$this->insert_post(
			[
				'title'   => 'WPForms Test Page',
				'name'    => 'wpforms-test',
				'content' => '[wpforms id="' . $wpforms_form_id . '"]',
			]
		);
	}

	/**
	 * Setup theme.
	 *
	 * @param string   $new_name  Name of the new theme.
	 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
	 * @param WP_Theme $old_theme WP_Theme instance of the old theme.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function setup_theme( string $new_name, WP_Theme $new_theme, WP_Theme $old_theme ): void {
		if ( ! $this->renew && ( $this->data['themes'][ $new_name ] ?? false ) ) {
			return;
		}

		switch ( $new_name ) {
			case 'Avada':
				// Create a new Avada form.
				$avada_form_id = $this->insert_post(
					[
						'title'     => 'Avada Test Form',
						'name'      => 'avada-test-form',
						'content'   => '[fusion_builder_container type="flex"][fusion_builder_row][fusion_builder_column type="1_1" layout="1_1"][fusion_form_text label="Text" name="text" required="yes" /][fusion_form_email label="EMail" name="email" /][fusion_form_textarea label="Message" name="textarea" /][fusion_form_submit]Submit[/fusion_form_submit][fusion_form_notice success="VGhhbmsgeW91IGZvciB5b3VyIG1lc3NhZ2UuIEl0IGhhcyBiZWVuIHNlbnQu" error="VGhlcmUgd2FzIGFuIGVycm9yIHRyeWluZyB0byBzZW5kIHlvdXIgbWVzc2FnZS4gUGxlYXNlIHRyeSBhZ2FpbiBsYXRlci4=" /][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]',
						'post_type' => 'fusion_form',
					]
				);

				// Create a new page with the Avada Form shortcode.
				$this->insert_post(
					[
						'title'   => 'Avada Test Page',
						'name'    => 'avada-test',
						'content' => '[fusion_builder_container type="flex"][fusion_builder_row][fusion_builder_column type="1_1" layout="1_1"][fusion_form form_post_id="' . $avada_form_id . '" /][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]',
					]
				);

				break;
			case 'Divi':
				// Detect a Divi theme version and create a suitable test page.
				$divi_version_raw = (string) $new_theme->get( 'Version' );

				// Extract numeric part to avoid suffixes like `-beta`.
				$divi_version = preg_replace( '/[^0-9.].*$/', '', $divi_version_raw );

				if ( $divi_version && version_compare( $divi_version, '5.0', '>=' ) ) {
					// Divi 5: create a block-based page with Divi 5 contact form blocks.
					$divi5_content = '
<!-- wp:divi/placeholder -->
<!-- wp:divi/section {"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/row {"module":{"advanced":{"flexColumnStructure":{"desktop":{"value":"equal-columns_1"}}},"decoration":{"layout":{"desktop":{"value":{"flexWrap":"nowrap"}}}}},"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/column {"module":{"decoration":{"sizing":{"desktop":{"value":{"flexType":"24_24"}}}}},"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/contact-form {"module":{"advanced":{"uniqueId":{"desktop":{"value":"a5de6c48-f800-45b5-bf2b-8ebb58b73d59"}},"spamProtection":{"desktop":{"value":{"useBasicCaptcha":"off"}}}}},"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/contact-field {"module":{"decoration":{"sizing":{"desktop":{"value":{"flexType":"12_24"}}}}},"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"id":{"desktop":{"value":"Name"}},"type":{"desktop":{"value":"input"}}},"innerContent":{"desktop":{"value":"Name"}}},"builderVersion":"5.0.0-public-beta.2"} /-->
<!-- wp:divi/contact-field {"module":{"decoration":{"sizing":{"desktop":{"value":{"flexType":"12_24"}}}}},"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"id":{"desktop":{"value":"Email"}},"type":{"desktop":{"value":"email"}}},"innerContent":{"desktop":{"value":"Email Address"}}},"builderVersion":"5.0.0-public-beta.2"} /-->
<!-- wp:divi/contact-field {"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"id":{"desktop":{"value":"Message"}},"type":{"desktop":{"value":"text"}}},"innerContent":{"desktop":{"value":"Message"}}},"builderVersion":"5.0.0-public-beta.2"} /-->
<!-- /wp:divi/contact-form -->
<!-- /wp:divi/column -->
<!-- /wp:divi/row -->
<!-- /wp:divi/section -->
<!-- /wp:divi/placeholder -->
';

					$this->insert_post(
						[
							'title'   => 'Divi 5 Test Page',
							'name'    => 'divi5-test',
							'content' => $divi5_content,
						]
					);
				} else {
					$this->create_divi_test_page();
				}

				break;
			case 'Extra':
				$this->create_divi_test_page();

				break;
			default:
				return;
		}

		$this->data['themes'][ $new_name ] = true;

		set_transient( self::PLAYGROUND_DATA, $this->data );
	}

	/**
	 * Add styles to the head.
	 *
	 * @return void
	 */
	public function head_styles(): void {
		?>
		<style>
			#wpadminbar #wp-admin-bar-hcaptcha-menu {
				background: #00bbbf;
			}

			#wpadminbar:not(.mobile) .ab-top-menu > li#wp-admin-bar-hcaptcha-menu:hover > .ab-item {
				color: white;
				background: #00bbbf;
			}

			#wpadminbar:not(.mobile) > #wp-toolbar li#wp-admin-bar-hcaptcha-menu:hover span.ab-label {
				color: white;
			}

			#wpadminbar > #wp-toolbar > #wp-admin-bar-root-default .ab-icon.hcaptcha-icon,
			#wpadminbar .ab-icon.hcaptcha-icon {
				width: 20px;
				height: 20px;
				background-image: url('<?php echo esc_url( $this->icon_url() ); ?>') !important;
				background-repeat: no-repeat;
				background-position: center;
				background-size: 20px 20px;
				top: 2px;
			}
		</style>
		<?php
	}

	/**
	 * Add a menu to the admin bar.
	 *
	 * @param WP_Admin_Bar $bar Admin bar instance.
	 *
	 * @return void
	 */
	public function admin_bar_menu( WP_Admin_Bar $bar ): void {
		if ( did_action( 'login_init' ) ) {
			$bar->remove_node( 'search' );
		}

		$nodes = $this->get_admin_bar_menu_nodes();

		foreach ( $nodes as $node ) {
			$bar->add_node( $node );
		}
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/playground$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::UPDATE_MENU_ACTION,
				'nonce'   => wp_create_nonce( self::UPDATE_MENU_ACTION ),
			]
		);
	}

	/**
	 * Filter the integrations' object.
	 *
	 * @return void
	 */
	public function update_menu(): void {
		// Run a security check.
		if ( ! check_ajax_referer( self::UPDATE_MENU_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}

		$nodes         = $this->get_admin_bar_menu_nodes();
		$dynamic_nodes = [];

		foreach ( $nodes as $node ) {
			$id   = $node['id'] ?? '';
			$href = $node['href'] ?? '';

			if ( ! $id || ! $href ) {
				continue;
			}

			$dynamic_nodes[] = compact( 'id', 'href' );
		}

		wp_send_json_success( $dynamic_nodes );
	}

	/**
	 * Get icon url.
	 *
	 * @return string
	 */
	private function icon_url(): string {
		return constant( 'HCAPTCHA_URL' ) . '/assets/images/playground-icon.svg';
	}

	/**
	 * Get href.
	 *
	 * @param string $status Module status name.
	 * @param string $url    URL.
	 *
	 * @return string
	 */
	private function get_href( string $status, string $url ): string {
		$entity_names = [];

		foreach ( hcaptcha()->modules as $module ) {
			$module_status = $module[0][0] ?? '';

			if ( $status === $module_status ) {
				$entity_names[] = (array) $module[1];
			}
		}

		if ( hcaptcha()->plugin_or_theme_active( array_unique( array_merge( ...$entity_names ) ) ) ) {
			return $url;
		}

		return add_query_arg(
			[
				'suggest_activate' => $status,
				'nonce'            => wp_create_nonce( Integrations::ACTIVATE_ACTION ),
			],
			'/wp-admin/admin.php?page=hcaptcha-integrations'
		);
	}

	/**
	 * Detect if the current site is a WP Playground site.
	 *
	 * @return bool
	 */
	private function is_wp_playground(): bool {
		if ( defined( 'HCAPTCHA_PLAYGROUND_MODE' ) && constant( 'HCAPTCHA_PLAYGROUND_MODE' ) ) {
			return true;
		}

		return $this->is_playground_host();
	}

	/**
	 * Detect if the current site is a WP Playground host.
	 *
	 * @return bool
	 */
	private function is_playground_host(): bool {

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return strpos( $host, 'playground.wordpress.net' ) !== false;
	}

	/**
	 * Get Divi test page slug depending on a Divi version.
	 *
	 * - If the active theme is Divi: detect a version from the active theme.
	 * - Else if Divi theme is installed: detect a version from installed theme data.
	 * - Else fallback by checking if a page with slug 'divi5-test' exists.
	 * - Default to 'divi-test'.
	 *
	 * @return string
	 */
	private function get_divi_test_slug(): string {
		// Check the active theme first.
		$active_theme = wp_get_theme();

		if ( $active_theme && strtolower( (string) $active_theme->get( 'Name' ) ) === 'divi' ) {
			$divi_version_raw = (string) $active_theme->get( 'Version' );
			$divi_version     = preg_replace( '/[^0-9.].*$/', '', $divi_version_raw );

			if ( $divi_version && version_compare( $divi_version, '5.0', '>=' ) ) {
				return 'divi5-test';
			}

			return 'divi-test';
		}

		// If Divi is installed (but not active), try to get its version from installed themes.
		$themes = wp_get_themes();

		if ( isset( $themes['Divi'] ) ) {
			$divi_version_raw = (string) $themes['Divi']->get( 'Version' );
			$divi_version     = preg_replace( '/[^0-9.].*$/', '', $divi_version_raw );

			if ( $divi_version && version_compare( $divi_version, '5.0', '>=' ) ) {
				return 'divi5-test';
			}
		}

		// Fallback by existing page presence (helps when the theme was switched earlier in the session).
		if ( get_page_by_path( 'divi5-test' ) ) {
			return 'divi5-test';
		}

		return 'divi-test';
	}

	/**
	 * Insert a post with content.
	 *
	 * @param array $args      {
	 *                         Arguments for the post to insert.
	 *
	 * @type string $title     Post title.
	 * @type string $name      Post name (slug).
	 * @type string $content   Post content.
	 * @type string $post_type Post type. Default 'page'.
	 *                         }
	 *
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	private function insert_post( array $args ) {
		$defaults = [
			'title'      => '',
			'name'       => '',
			'content'    => '',
			'post_type'  => 'page',
			'meta_input' => [],
		];

		$args = wp_parse_args( $args, $defaults );

		$name      = (string) $args['name'];
		$post_type = (string) $args['post_type'];

		// Check if the post already exists by path within the specified post type.
		$post    = get_page_by_path( $name, OBJECT, $post_type );
		$post_id = $post->ID ?? 0;

		if ( $post_id ) {
			if ( $this->renew ) {
				wp_delete_post( $post_id, true );
			} else {
				return $post_id;
			}
		}

		$postarr = [
			'post_type'    => $post_type,
			'post_title'   => (string) $args['title'],
			'post_status'  => 'publish',
			'post_content' => (string) $args['content'],
			'post_name'    => $name,
		];

		if ( ! empty( $args['meta_input'] ) ) {
			$postarr['meta_input'] = $args['meta_input'];
		}

		return wp_insert_post( wp_slash( $postarr ) );
	}

	/**
	 * Set up the permalinks.
	 *
	 * @return void
	 */
	private function setup_permalinks(): void {
		update_option( 'permalink_structure', '/%postname%/' );
		flush_rewrite_rules();
	}

	/**
	 * Set up the settings.
	 *
	 * @return void
	 */
	private function setup_settings(): void {
		$settings = get_option( 'hcaptcha_settings', [] );

		$settings['wp_status']                    = [
			'comment',
			'login',
			'lost_pass',
			'password_protected',
			'register',
		];
		$settings['avada_status']                 = [ 'form' ];
		$settings['cf7_status']                   = [ 'form', 'embed', 'live', 'replace_rsc' ];
		$settings['divi_status']                  = [ 'comment', 'contact', 'email_optin', 'login' ];
		$settings['elementor_pro_status']         = [ 'form', 'login' ];
		$settings['essential_addons_status']      = [ 'login', 'register' ];
		$settings['extra_status']                 = [ 'comment', 'contact', 'email_optin', 'login' ];
		$settings['jetpack_status']               = [ 'contact' ];
		$settings['mailchimp_status']             = [ 'form' ];
		$settings['maintenance_status']           = [ 'login' ];
		$settings['spectra_status']               = [ 'form' ];
		$settings['ultimate_addons_status']       = [ 'login', 'register' ];
		$settings['wpforms_status']               = [ 'form', 'embed' ];
		$settings['woocommerce_status']           = [
			'checkout',
			'login',
			'lost_pass',
			'order_tracking',
			'register',
		];
		$settings['_network_wide']                = [];
		$settings['off_when_logged_in']           = [];
		$settings['recaptcha_compat_off']         = [];
		$settings['secret_key']                   = '';
		$settings['theme']                        = 'light';
		$settings['size']                         = 'normal';
		$settings['language']                     = '';
		$settings['whitelisted_ips']              = "100.200.0.2\n220.45.45.1\n";
		$settings['mode']                         = 'test:publisher';
		$settings['site_key']                     = '';
		$settings['delay']                        = '0';
		$settings['login_limit']                  = '0';
		$settings['login_interval']               = '5';
		$settings['force']                        = [ 'on' ];
		$settings['statistics']                   = [ 'on' ];
		$settings['custom_themes']                = [];
		$settings['api_host']                     = 'js.hcaptcha.com';
		$settings['asset_host']                   = '';
		$settings['endpoint']                     = '';
		$settings['host']                         = '';
		$settings['image_host']                   = '';
		$settings['report_api']                   = '';
		$settings['sentry']                       = '';
		$settings['backend']                      = 'api.hcaptcha.com';
		$settings['license']                      = 'pro';
		$settings['menu_position']                = [];
		$settings['sample_hcaptcha']              = '';
		$settings['check_config']                 = '';
		$settings['reset_notifications']          = '';
		$settings['custom_prop']                  = '';
		$settings['custom_value']                 = '';
		$settings['hide_login_errors']            = [];
		$settings['anonymous']                    = [];
		$settings['protect_content']              = [];
		$settings['protected_urls']               = '/protected-content';
		$settings['cleanup_on_uninstall']         = [];
		$settings['whats_new_last_shown_version'] = '4.18.0';
		$settings['blacklisted_ips']              = '';
		$settings['antispam']                     = [ 'on' ];
		$settings['antispam_provider']            = 'akismet';
		$settings['honeypot']                     = [ 'on' ];
		$settings['set_min_submit_time']          = [ 'on' ];
		$settings['min_submit_time']              = '2';
		$settings['show_antispam_coverage']       = [ 'on' ];

		update_option( 'hcaptcha_settings', $settings );
	}

	/**
	 * Create a Divi/Extra test page.
	 *
	 * @return void
	 */
	private function create_divi_test_page(): void {
		// Divi 4 (Classic): create a page with the Divi contact form shortcode.
		$this->insert_post(
			[
				'title'   => 'Divi Test Page',
				'name'    => 'divi-test',
				'content' => '[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"][et_pb_contact_form captcha="off" email="" _module_preset="default"][et_pb_contact_field field_id="Name" field_title="Name"][/et_pb_contact_field][et_pb_contact_field field_id="Email" field_title="Email Address" field_type="email"][/et_pb_contact_field][et_pb_contact_field field_id="Message" field_title="Message" field_type="text" fullwidth_field="on"][/et_pb_contact_field][/et_pb_contact_form][/et_pb_column][/et_pb_row][/et_pb_section]',
			]
		);
	}

	/**
	 * Get the admin bar menu nodes.
	 *
	 * @return array[]
	 */
	private function get_admin_bar_menu_nodes(): array {
		return [
			// Parent item without href — just opens subitems. For it, no href.
			[
				'id'    => self::HCAPTCHA_MENU_ID,
				'title' => sprintf(
					'<span class="ab-icon hcaptcha-icon"></span><span class="ab-label">%1$s</span>',
					__( 'hCaptcha Samples', 'hcaptcha-for-forms-and-more' )
				),
				'meta'  => [ 'class' => self::HCAPTCHA_MENU_ID ],
			],

			// hCaptcha settings.
			[
				'id'     => self::HCAPTCHA_MENU_HCAPTCHA_SETTINGS,
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-admin/admin.php?page=hcaptcha' ),
			],

			// hCaptcha settings general.
			[
				'id'     => 'hcaptcha-menu-hcaptcha-general',
				'parent' => self::HCAPTCHA_MENU_HCAPTCHA_SETTINGS,
				'title'  => __( 'General', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-admin/admin.php?page=hcaptcha' ),
			],

			// hCaptcha settings general.
			[
				'id'     => 'hcaptcha-menu-hcaptcha-integrations',
				'parent' => self::HCAPTCHA_MENU_HCAPTCHA_SETTINGS,
				'title'  => __( 'Integrations', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-admin/admin.php?page=hcaptcha-integrations' ),
			],

			// WordPress group.
			[
				'id'     => self::HCAPTCHA_MENU_WORDPRESS_ID,
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'WordPress Core', 'hcaptcha-for-forms-and-more' ),
			],

			// WordPress Login page.
			[
				'id'     => 'hcaptcha-menu-wp-login',
				'parent' => self::HCAPTCHA_MENU_WORDPRESS_ID,
				'title'  => __( 'Login', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-login.php?action=logout' ),
			],

			// WordPress Comments.
			[
				'id'     => 'hcaptcha-menu-wp-comments',
				'parent' => self::HCAPTCHA_MENU_WORDPRESS_ID,
				'title'  => __( 'Comments', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '?p=1' ),
			],

			// Avada test page.
			[
				'id'     => 'hcaptcha-menu-avada',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Avada', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'avada_status', home_url( 'avada-test' ) ),
			],

			// CF7 test page.
			[
				'id'     => 'hcaptcha-menu-cf7',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Contact Form 7', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'cf7_status', home_url( 'contact-form-7-test' ) ),
			],

			// Divi test page.
			[
				'id'     => 'hcaptcha-menu-divi',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Divi', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'divi_status', home_url( $this->get_divi_test_slug() ) ),
			],

			// Elementor Pro test page.
			[
				'id'     => 'hcaptcha-menu-elementor',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Elementor Pro', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'elementor_pro_status', home_url( 'elementor-pro-test' ) ),
			],

			// Essential Addons test page.
			[
				'id'     => 'hcaptcha-menu-essential-addons',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Essential Addons', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'essential_addons_status', home_url( 'essential-addons-test' ) ),
			],

			// Extra test page.
			[
				'id'     => 'hcaptcha-menu-extra',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Extra', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'extra_status', home_url( 'divi-test' ) ),
			],

			// Jetpack test page.
			[
				'id'     => 'hcaptcha-menu-jetpack',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Jetpack', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'jetpack_status', home_url( 'jetpack-test' ) ),
			],

			// Mailchimp test page.
			[
				'id'     => 'hcaptcha-menu-mailchimp',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Mailchimp', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'mailchimp_status', home_url( 'mailchimp-test' ) ),
			],

			// Maintenance test page.
			[
				'id'     => 'hcaptcha-menu-maintenance',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Maintenance', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'maintenance_status', home_url( '/wp-login.php?action=logout' ) ),
			],

			// Spectra test page.
			[
				'id'     => 'hcaptcha-menu-spectra',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Spectra', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'spectra_status', home_url( 'spectra-test' ) ),
			],

			// Ultimate Addons test page.
			[
				'id'     => 'hcaptcha-menu-ultimate-addons',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Ultimate Addons', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'ultimate_addons_status', home_url( 'ultimate-addons-test' ) ),
			],

			// WooCommerce group.
			[
				'id'     => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'WooCommerce', 'hcaptcha-for-forms-and-more' ),
			],

			// WooCommerce Checkout page.
			[
				'id'     => 'hcaptcha-menu-wc-checkout',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'Checkout', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( 'checkout' ) ),
			],

			// WooCommerce Login/Register page.
			[
				'id'     => 'hcaptcha-menu-wc-login-register',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'Login/Register', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( 'my-account' ) ),
			],

			// WooCommerce Order Tracking page.
			[
				'id'     => 'hcaptcha-menu-wc-order-tracking',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'Order Tracking', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( 'wc-order-tracking' ) ),
			],

			// WPForms test page.
			[
				'id'     => 'hcaptcha-menu-wpforms',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'WPForms', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'wpforms_status', home_url( 'wpforms-test' ) ),
			],
		];
	}
}
