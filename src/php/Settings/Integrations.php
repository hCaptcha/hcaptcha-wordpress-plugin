<?php
/**
 * Integrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Dialog;
use KAGG\Settings\Abstracts\SettingsBase;
use WP_Theme;

/**
 * Class Integrations
 *
 * Settings page "Integrations".
 */
class Integrations extends PluginSettingsBase {

	/**
	 * Dialog scripts and style handle.
	 */
	const DIALOG_HANDLE = 'kagg-dialog';

	/**
	 * Admin script and style handle.
	 */
	const HANDLE = 'hcaptcha-integrations';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'HCaptchaIntegrationsObject';

	/**
	 * Activate plugin ajax action.
	 */
	const ACTIVATE_ACTION = 'hcaptcha-integrations-activate';

	/**
	 * Enabled section id.
	 */
	const SECTION_ENABLED = 'enabled';

	/**
	 * Disabled section id.
	 */
	const SECTION_DISABLED = 'disabled';

	/**
	 * Dialog class instance.
	 *
	 * @var Dialog
	 */
	protected $dialog;

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Integrations', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'integrations';
	}

	/**
	 * Init class hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'kagg_settings_tab', [ $this, 'search_box' ] );
		add_action( 'wp_ajax_' . self::ACTIVATE_ACTION, [ $this, 'activate' ] );
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'wp_status'                        => [
				'entity'  => 'core',
				'label'   => 'WP Core',
				'type'    => 'checkbox',
				'options' => [
					'comment'            => __( 'Comment Form', 'hcaptcha-for-forms-and-more' ),
					'login'              => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass'          => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'password_protected' => __( 'Post/Page Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'           => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'acfe_status'                      => [
				'label'   => 'ACF Extended',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'ACF Extended Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'affiliates_status'                => [
				'label'   => 'Affiliates',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Affiliates Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Affiliates Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'asgaros_status'                   => [
				'label'   => 'Asgaros',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'avada_status'                     => [
				'entity'  => 'theme',
				'label'   => 'Avada',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Avada Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'back_in_stock_notifier_status'    => [
				'label'   => 'Back In Stock Notifier',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Back In Stock Notifier Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bbp_status'                       => [
				'label'   => 'bbPress',
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'beaver_builder_status'            => [
				'label'   => 'Beaver Builder',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'   => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'brizy_status'                     => [
				'label'   => 'Brizy',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bp_status'                        => [
				'label'   => 'BuddyPress',
				'type'    => 'checkbox',
				'options' => [
					'create_group' => __( 'Create Group Form', 'hcaptcha-for-forms-and-more' ),
					'registration' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'classified_listing_status'        => [
				'label'   => 'Classified Listing',
				'type'    => 'checkbox',
				'options' => [
					'contact'   => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'colorlib_customizer_status'       => [
				'label'   => 'Colorlib Login Customizer',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'cf7_status'                       => [
				'label'   => 'Contact Form 7',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'divi_status'                      => [
				'entity'  => 'theme',
				'label'   => 'Divi',
				'type'    => 'checkbox',
				'options' => [
					'comment'     => __( 'Divi Comment Form', 'hcaptcha-for-forms-and-more' ),
					'contact'     => __( 'Divi Contact Form', 'hcaptcha-for-forms-and-more' ),
					'email_optin' => __( 'Divi Email Optin Form', 'hcaptcha-for-forms-and-more' ),
					'login'       => __( 'Divi Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'download_manager_status'          => [
				'label'   => 'Download Manager',
				'type'    => 'checkbox',
				'options' => [
					'button' => __( 'Button', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'easy_digital_downloads_status'    => [
				'label'   => 'Easy Digital Downloads',
				'type'    => 'checkbox',
				'options' => [
					'checkout'  => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'elementor_pro_status'             => [
				'label'   => 'Elementor Pro',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'fluent_status'                    => [
				'label'   => 'Fluent Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'formidable_forms_status'          => [
				'label'   => 'Formidable Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'forminator_status'                => [
				'label'   => 'Forminator',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'give_wp_status'                   => [
				'label'   => 'GiveWP',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'gravity_status'                   => [
				'label'   => 'Gravity Forms',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed' => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'html_forms_status'                => [
				'label'   => 'HTML Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'jetpack_status'                   => [
				'label'   => 'Jetpack',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'kadence_status'                   => [
				'label'   => 'Kadence',
				'type'    => 'checkbox',
				'options' => [
					'form'          => __( 'Kadence Form', 'hcaptcha-for-forms-and-more' ),
					'advanced_form' => __( 'Kadence Advanced Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'learn_dash_status'                => [
				'label'   => 'LearnDash LMS',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailchimp_status'                 => [
				'label'   => 'Mailchimp for WP',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailpoet_status'                  => [
				'label'   => 'MailPoet',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'memberpress_status'               => [
				'label'   => 'MemberPress',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ninja_status'                     => [
				'label'   => 'Ninja Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'otter_status'                     => [
				'label'   => 'Otter Blocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'paid_memberships_pro_status'      => [
				'label'   => 'Paid Memberships Pro',
				'type'    => 'checkbox',
				'options' => [
					'checkout' => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'passster_status'                  => [
				'label'   => 'Passster',
				'type'    => 'checkbox',
				'options' => [
					'protect' => __( 'Protection Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'profile_builder_status'           => [
				'label'   => 'Profile Builder',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Recover Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'quform_status'                    => [
				'label'   => 'Quform',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'sendinblue_status'                => [
				'label'   => 'Brevo',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'simple_basic_contact_form_status' => [
				'label'   => 'Simple Basic Contact Form',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'simple_download_monitor_status'   => [
				'label'   => 'Simple Download Monitor',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'subscriber_status'                => [
				'label'   => 'Subscriber',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'supportcandy_status'              => [
				'label'   => 'Support Candy',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'theme_my_login_status'            => [
				'label'   => 'Theme My Login',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ultimate_member_status'           => [
				'label'   => 'Ultimate Member',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'users_wp_status'                  => [
				'label'   => 'Users WP',
				'type'    => 'checkbox',
				'options' => [
					'forgot'   => __( 'Forgot Password Form', 'hcaptcha-for-forms-and-more' ),
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_status'               => [
				'label'   => 'WooCommerce',
				'type'    => 'checkbox',
				'options' => [
					'checkout'       => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'          => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass'      => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'order_tracking' => __( 'Order Tracking Form', 'hcaptcha-for-forms-and-more' ),
					'register'       => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_wishlists_status'     => [
				'label'   => 'WooCommerce Wishlists',
				'type'    => 'checkbox',
				'options' => [
					'create_list' => __( 'Create List Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wordfence_status'                 => [
				'label'   => 'Wordfence',
				'type'    => 'checkbox',
				'options' => [
					'login' => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforms_status'                   => [
				'label'   => 'WPForms',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed' => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpdiscuz_status'                  => [
				'label'   => 'WPDiscuz',
				'type'    => 'checkbox',
				'options' => [
					'comment_form'   => __( 'Comment Form', 'hcaptcha-for-forms-and-more' ),
					'subscribe_form' => __( 'Subscribe Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforo_status'                    => [
				'label'   => 'WPForo',
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wp_job_openings_status'           => [
				'label'   => 'WP Job Openings',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
		];
	}

	/**
	 * Get logo image.
	 *
	 * @param array $form_field Label.
	 *
	 * @return string
	 * @noinspection HtmlUnknownTarget
	 */
	private function logo( array $form_field ): string {
		$label     = $form_field['label'];
		$logo_file = sanitize_file_name( strtolower( $label ) . '-logo.png' );
		$entity    = $form_field['entity'] ?? 'plugin';

		return sprintf(
			'<div class="hcaptcha-integrations-logo">' .
			'<img src="%1$s" alt="%2$s Logo" data-label="%2$s" data-entity="%3$s">' .
			'</div>',
			esc_url( constant( 'HCAPTCHA_URL' ) . "/assets/images/$logo_file" ),
			$label,
			$entity
		);
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$this->form_fields = $this->sort_fields( $this->form_fields );

		foreach ( $this->form_fields as &$form_field ) {
			if ( isset( $form_field['label'] ) ) {
				$form_field['label'] = $this->logo( $form_field );
			}

			if ( $form_field['disabled'] ) {
				$form_field['section'] = self::SECTION_DISABLED;
			} else {
				$form_field['section'] = self::SECTION_ENABLED;
			}
		}

		unset( $form_field );

		parent::setup_fields();
	}

	/**
	 * Sort fields. First, by enabled status, then by label.
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	public function sort_fields( array $fields ): array {
		uasort(
			$fields,
			static function ( $a, $b ) {
				$a_disabled = $a['disabled'] ?? false;
				$b_disabled = $b['disabled'] ?? false;

				$a_label = strtolower( $a['label'] ?? '' );
				$b_label = strtolower( $b['label'] ?? '' );

				if ( $a_disabled === $b_disabled ) {
					return $a_label <=> $b_label;
				}

				if ( ! $a_disabled && $b_disabled ) {
					return -1;
				}

				return 1;
			}
		);

		return $fields;
	}

	/**
	 * Show search box.
	 */
	public function search_box() {
		?>
		<span id="hcaptcha-integrations-search-wrap">
			<label for="hcaptcha-integrations-search"></label>
			<input
					type="search" id="hcaptcha-integrations-search"
					placeholder="<?php esc_html_e( 'Search plugins and themes...', 'hcaptcha-for-forms-and-more' ); ?>">
		</span>
		<?php
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function section_callback( array $arguments ) {
		if ( self::SECTION_DISABLED === $arguments['id'] ) {
			$this->submit_button();

			?>
			<hr class="hcaptcha-disabled-section">
			<h3><?php esc_html_e( 'Inactive plugins and themes', 'hcaptcha-for-forms-and-more' ); ?></h3>
			<?php

			return;
		}

		?>
		<h2>
			<?php echo esc_html( $this->page_title() ); ?>
		</h2>
		<div id="hcaptcha-message"></div>
		<p>
			<?php esc_html_e( 'Manage integrations with popular plugins such as Contact Form 7, WPForms, Gravity Forms, and more.', 'hcaptcha-for-forms-and-more' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'You can activate and deactivate a plugin by clicking on its logo.', 'hcaptcha-for-forms-and-more' ); ?>
		</p>
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
		<h3><?php esc_html_e( 'Active plugins and themes', 'hcaptcha-for-forms-and-more' ); ?></h3>
		<?php
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/kagg-dialog$this->min_prefix.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/kagg-dialog$this->min_prefix.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/integrations$this->min_prefix.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		$themes = array_map(
			static function ( $theme ) {
				return $theme->get( 'Name' );
			},
			wp_get_themes()
		);

		unset( $themes[ wp_get_theme()->get_stylesheet() ] );

		$core_default_theme_obj = WP_Theme::get_core_default_theme();
		$default_theme          = $core_default_theme_obj ? $core_default_theme_obj->get_stylesheet() : '';

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'action'             => self::ACTIVATE_ACTION,
				'nonce'              => wp_create_nonce( self::ACTIVATE_ACTION ),
				/* translators: 1: Plugin name. */
				'activateMsg'        => __( 'Activate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Plugin name. */
				'deactivateMsg'      => __( 'Deactivate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'activateThemeMsg'   => __( 'Activate %s theme?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'deactivateThemeMsg' => __( 'Deactivate %s theme?', 'hcaptcha-for-forms-and-more' ),
				'selectThemeMsg'     => __( 'Select theme to activate:', 'hcaptcha-for-forms-and-more' ),
				'onlyOneThemeMsg'    => __( 'Cannot deactivate the only theme on the site.', 'hcaptcha-for-forms-and-more' ),
				'unexpectedErrorMsg' => __( 'Unexpected error.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText'          => __( 'OK', 'hcaptcha-for-forms-and-more' ),
				'CancelBtnText'      => __( 'Cancel', 'hcaptcha-for-forms-and-more' ),
				'themes'             => $themes,
				'defaultTheme'       => $default_theme,
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/integrations$this->min_prefix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE, self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Ajax action to activate/deactivate plugin.
	 *
	 * @return void
	 */
	public function activate() {
		// Run a security check.
		if ( ! check_ajax_referer( self::ACTIVATE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}

		$activate       = filter_input( INPUT_POST, 'activate', FILTER_VALIDATE_BOOLEAN );
		$entity         = filter_input( INPUT_POST, 'entity', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$new_theme_name = filter_input( INPUT_POST, 'newThemeName', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status         = filter_input( INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status         = str_replace( '-', '_', $status );
		$entity_name    = $this->form_fields[ $status ]['label'];
		$entities       = [];

		foreach ( hcaptcha()->modules as $module ) {
			if ( $module[0][0] === $status ) {
				$entities[] = (array) $module[1];
			}
		}

		$entities = array_merge( [], ...$entities );

		header_remove( 'Location' );
		http_response_code( 200 );

		if ( 'plugin' === $entity ) {
			$this->process_plugins( $activate, $entities, $entity_name );
		} else {
			$this->process_themes( $activate, $entities, $entity_name, $new_theme_name );
		}
	}

	/**
	 * Activate/deactivate plugins.
	 *
	 * @param bool   $activate    Activate or deactivate.
	 * @param array  $plugins     Plugins to process.
	 * @param string $plugin_name Main plugin name to process.
	 *
	 * @return void
	 */
	private function process_plugins( bool $activate, array $plugins, string $plugin_name ) {
		if ( $activate ) {
			if ( ! $this->activate_plugins( $plugins ) ) {
				$message = sprintf(
				/* translators: 1: Plugin name. */
					__( 'Error activating %s plugin.', 'hcaptcha-for-forms-and-more' ),
					$plugin_name
				);

				wp_send_json_error( esc_html( $message ) );
			}

			$message = sprintf(
			/* translators: 1: Plugin name. */
				__( '%s plugin is activated.', 'hcaptcha-for-forms-and-more' ),
				$plugin_name
			);

			wp_send_json_success( esc_html( $message ) );
		}

		deactivate_plugins( $plugins );

		$message = sprintf(
		/* translators: 1: Plugin name. */
			__( '%s plugin is deactivated.', 'hcaptcha-for-forms-and-more' ),
			$plugin_name
		);

		wp_send_json_success( esc_html( $message ) );
	}

	/**
	 * Activate/deactivate themes.
	 *
	 * @param bool   $activate       Activate or deactivate.
	 * @param array  $themes         Themes to process.
	 * @param string $theme_name     Main theme name to process.
	 * @param string $new_theme_name New theme name to activate.
	 *
	 * @return void
	 */
	private function process_themes( bool $activate, array $themes, string $theme_name, string $new_theme_name ) {
		if ( $activate ) {
			if ( ! $this->activate_themes( $themes ) ) {
				$message = sprintf(
				/* translators: 1: Theme name. */
					__( 'Error activating %s theme.', 'hcaptcha-for-forms-and-more' ),
					$theme_name
				);

				wp_send_json_error( esc_html( $message ) );
			}

			$message = sprintf(
			/* translators: 1: Theme name. */
				__( '%s theme is activated.', 'hcaptcha-for-forms-and-more' ),
				$theme_name
			);

			wp_send_json_success( esc_html( $message ) );
		}

		ob_start();

		switch_theme( $new_theme_name );

		ob_end_clean();

		$message = sprintf(
		/* translators: 1: Activated theme name. 2: Deactivated theme name. */
			__( '%1$s theme is activated. %2$s theme is deactivated.', 'hcaptcha-for-forms-and-more' ),
			wp_get_theme( $new_theme_name )->get( 'Name' ),
			$theme_name
		);

		wp_send_json_success( esc_html( $message ) );
	}

	/**
	 * Activate plugins.
	 *
	 * We activate the first available plugin in the list only,
	 * assuming that Pro plugins are placed earlier in the list.
	 *
	 * @param array $plugins Plugins to activate.
	 *
	 * @return bool
	 */
	private function activate_plugins( array $plugins ): bool {
		foreach ( $plugins as $plugin ) {
			ob_start();

			$result = activate_plugin( $plugin );

			ob_end_clean();

			if ( null === $result ) {
				// Activate the first available plugin only.
				return true;
			}
		}

		return false;
	}

	/**
	 * Activate themes.
	 *
	 * We activate the first available theme in the list only,
	 * assuming that parent theme is placed earlier in the list.
	 *
	 * @param array $themes Themes to activate.
	 *
	 * @return bool
	 */
	private function activate_themes( array $themes ): bool {
		$theme = $themes[0];

		if ( ! wp_get_theme( $theme )->exists() ) {
			return false;
		}

		ob_start();

		switch_theme( $theme );

		ob_end_clean();

		// Activate the first available theme only.
		return true;
	}
}
