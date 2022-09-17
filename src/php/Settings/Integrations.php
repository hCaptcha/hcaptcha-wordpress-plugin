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
			'wp_status'                    => [
				'label'   => __( 'WP Core', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'comment'   => __( 'Comment Form', 'hcaptcha-for-forms-and-more' ),
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bbp_status'                   => [
				'label'   => __( 'bbPress', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bp_status'                    => [
				'label'   => __( 'BuddyPress', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'create_group' => __( 'Create Group Form', 'hcaptcha-for-forms-and-more' ),
					'registration' => __( 'Registration Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'cf7_status'                   => [
				'label'   => __( 'Contact Form 7', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'divi_status'                  => [
				'label'   => __( 'Divi', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'comment' => __( 'Divi Comment Form', 'hcaptcha-for-forms-and-more' ),
					'contact' => __( 'Divi Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'   => __( 'Divi Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'elementor_pro_status'         => [
				'label'   => __( 'Elementor Pro', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'fluent_status'                => [
				'label'   => __( 'Fluent Forms', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'gravity_status'               => [
				'label'   => __( 'Gravity Forms', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'jetpack_status'               => [
				'label'   => __( 'Jetpack', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailchimp_status'             => [
				'label'   => __( 'Mailchimp for WP', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'memberpress_status'           => [
				'label'   => __( 'MemberPress', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'register' => __( 'Registration Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ninja_status'                 => [
				'label'   => __( 'Ninja Forms', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'subscriber_status'            => [
				'label'   => __( 'Subscriber', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ultimate_member_status'       => [
				'label'   => __( 'Ultimate Member', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_status'           => [
				'label'   => __( 'WooCommerce', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'checkout'       => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'          => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass'      => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'order_tracking' => __( 'Order Tracking Form', 'hcaptcha-for-forms-and-more' ),
					'register'       => __( 'Registration Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_wishlists_status' => [
				'label'   => __( 'WooCommerce Wishlists', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'create_list' => __( 'Create List Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforms_status'               => [
				'label'   => __( 'WPForms', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'lite' => __( 'Lite', 'hcaptcha-for-forms-and-more' ),
					'pro'  => __( 'Pro', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforo_status'                => [
				'label'   => __( 'WPForo', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
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
		<p>
			<?php
			$shortcode_url   = 'https://wordpress.org/plugins/hcaptcha-for-forms-and-more/#does%20the%20%5Bhcaptcha%5D%20shortcode%20have%20arguments%3F';
			$integration_url = 'https://github.com/hCaptcha/hcaptcha-wordpress-plugin/issues';

			echo wp_kses_post(
				sprintf(
				/* translators: 1: hCaptcha shortcode doc link, 2: integration doc link. */
					__( 'Don\'t see your plugin here? Use the `[hcaptcha]` %1$s or %2$s.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$shortcode_url,
						__( 'shortcode', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$integration_url,
						__( 'request an integration', 'hcaptcha-for-forms-and-more' )
					)
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @todo Update with proper scripts and styles.
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
