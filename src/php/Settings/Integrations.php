<?php
/**
 * Integrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

/**
 * Class Tables
 *
 * Settings page "Integrations" (main).
 */
class Integrations extends PluginSettingsBase {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'hcaptcha-integrations';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'HCaptchaIntegrationsObject';

	/**
	 * Get screen id.
	 *
	 * @return string
	 */
	public function screen_id() {
		return 'settings_page_hcaptcha';
	}

	/**
	 * Get option group.
	 *
	 * @return string
	 */
	protected function option_group() {
		return 'hcaptcha_group';
	}

	/**
	 * Get option page.
	 *
	 * @return string
	 */
	protected function option_page() {
		return 'hcaptcha';
	}

	/**
	 * Get option name.
	 *
	 * @return string
	 */
	protected function option_name() {
		return 'hcaptcha_settings';
	}

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __( 'Integrations', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	protected function menu_title() {
		return __( 'hCaptcha', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title() {
		return '';
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'hcaptcha_lf_status'                   => [
				'label' => __( 'WP Login Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcap_t' => [
				'label' => __( 'Test multiple checkbox', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
				'options' => [
						'one' => 'One',
						'two' => 'Two',
				],
			],
			'hcaptcha_rf_status'                   => [
				'label' => __( 'WP Register Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_lpf_status'                  => [
				'label' => __( 'WP Lost Password Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_cmf_status'                  => [
				'label' => __( 'WP Comment Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_bbp_new_topic_status'        => [
				'label' => __( 'bbPress New Topic Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_bbp_reply_status'            => [
				'label' => __( 'bbPress Reply Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_bp_create_group_status'      => [
				'label' => __( 'BuddyPress Create Group Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_bp_reg_status'               => [
				'label' => __( 'BuddyPress Registration Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_cf7_status'                  => [
				'label' => __( 'Contact Form 7', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_divi_cmf_status'             => [
				'label' => __( 'Divi Comment Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_divi_cf_status'              => [
				'label' => __( 'Divi Contact Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_divi_lf_status'              => [
				'label' => __( 'Divi Login Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_elementor__pro_form_status'  => [
				'label' => __( 'Elementor Pro Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_fluentform_status'           => [
				'label' => __( 'Fluent Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_gravityform_status'          => [
				'label' => __( 'Gravity Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_jetpack_cf_status'           => [
				'label' => __( 'Jetpack Contact Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_mc4wp_status'                => [
				'label' => __( 'Mailchimp for WP Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_memberpress_register_status' => [
				'label' => __( 'MemberPress Registration Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_nf_status'                   => [
				'label' => __( 'Ninja Forms', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_subscribers_status'          => [
				'label' => __( 'Subscribers Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_um_login_status'             => [
				'label' => __( 'Ultimate Member Login Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_um_lost_pass_status'         => [
				'label' => __( 'Ultimate Member Lost Password Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_um_register_status'          => [
				'label' => __( 'Ultimate Member Register Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wc_login_status'             => [
				'label' => __( 'WooCommerce Login Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wc_reg_status'               => [
				'label' => __( 'WooCommerce Registration Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wc_lost_pass_status'         => [
				'label' => __( 'WooCommerce Lost Password Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wc_checkout_status'          => [
				'label' => __( 'WooCommerce Checkout Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wc_order_tracking_status'    => [
				'label' => __( 'WooCommerce Order Tracking Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wc_wl_create_list_status'    => [
				'label' => __( 'WooCommerce Wishlists Create List Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wpforms_status'              => [
				'label' => __( 'WPForms Lite', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wpforms_pro_status'          => [
				'label' => __( 'WPForms Pro', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wpforo_new_topic_status'     => [
				'label' => __( 'WPForo New Topic Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
			'hcaptcha_wpforo_reply_status'         => [
				'label' => __( 'WPForo Reply Form', 'hcaptcha-for-forms-and-more' ),
				'type'  => 'checkbox',
			],
		];
	}

	/**
	 * Show settings page.
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1>
				<?php
				// Admin panel title.
				esc_html_e( 'hCaptcha Plugin Options', 'hcaptcha-for-forms-and-more' );
				?>
			</h1>

			<form id="hcaptcha-options" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
				<?php
				do_settings_sections( $this->option_page() ); // Sections with options.
				settings_fields( $this->option_group() ); // Hidden protection fields.
				submit_button();
				?>
			</form>

			<div id="appreciation">
				<h2>
					<?php echo esc_html( __( 'Your Appreciation', 'hcaptcha-for-forms-and-more' ) ); ?>
				</h2>
				<a
					target="_blank"
					href="https://wordpress.org/support/view/plugin-reviews/hcaptcha-for-forms-and-more?rate=5#new-post">
					<?php echo esc_html( __( 'Leave a ★★★★★ plugin review on WordPress.org', 'hcaptcha-for-forms-and-more' ) ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( $arguments ) {
		?>
		<h2>
			<?php esc_html_e( 'Enable hCaptcha on the Following Forms:', 'hcaptcha-for-forms-and-more' ); ?>
		</h2>
		<?php
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/integrations/app$this->min_prefix.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'optionsSaveSuccessMessage' => __( 'Options saved.', 'hcaptcha-for-forms-and-more' ),
				'optionsSaveErrorMessage'   => __( 'Error saving options.', 'hcaptcha-for-forms-and-more' ),
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/integrations$this->min_prefix.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
	}
}
