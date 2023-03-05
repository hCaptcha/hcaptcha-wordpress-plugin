<?php
/**
 * Integrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Settings\Abstracts\SettingsBase;

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
	 * Activate plugin ajax action.
	 */
	const ACTIVATE_ACTION = 'hcaptcha-integrations-activate';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'HCaptchaIntegrationsObject';

	/**
	 * Disabled section id.
	 */
	const SECTION_DISABLED = 'disabled';

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
		return 'integrations';
	}

	/**
	 * Init class hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'wp_ajax_' . self::ACTIVATE_ACTION, [ $this, 'activate' ] );
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'wp_status'                    => [
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
			'acfe_status'                  => [
				'label'   => 'ACF Extended',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'ACF Extended Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'asgaros_status'               => [
				'label'   => 'Asgaros',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'avada_status'                 => [
				'label'   => 'Avada',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Avada Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bbp_status'                   => [
				'label'   => 'bbPress',
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'beaver_builder_status'        => [
				'label'   => 'Beaver Builder',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'   => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'brizy_status'                 => [
				'label'   => 'Brizy',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bp_status'                    => [
				'label'   => 'BuddyPress',
				'type'    => 'checkbox',
				'options' => [
					'create_group' => __( 'Create Group Form', 'hcaptcha-for-forms-and-more' ),
					'registration' => __( 'Registration Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'cf7_status'                   => [
				'label'   => 'Contact Form 7',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'divi_status'                  => [
				'label'   => 'Divi',
				'type'    => 'checkbox',
				'options' => [
					'comment' => __( 'Divi Comment Form', 'hcaptcha-for-forms-and-more' ),
					'contact' => __( 'Divi Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'   => __( 'Divi Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'download_manager_status'      => [
				'label'   => 'Download Manager',
				'type'    => 'checkbox',
				'options' => [
					'button' => __( 'Button', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'elementor_pro_status'         => [
				'label'   => 'Elementor Pro',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'fluent_status'                => [
				'label'   => 'Fluent Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'forminator_status'            => [
				'label'   => 'Forminator',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'give_wp_status'               => [
				'label'   => 'GiveWP',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'gravity_status'               => [
				'label'   => 'Gravity Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'jetpack_status'               => [
				'label'   => 'Jetpack',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'kadence_status'               => [
				'label'   => 'Kadence',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Kadence Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailchimp_status'             => [
				'label'   => 'Mailchimp for WP',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'memberpress_status'           => [
				'label'   => 'MemberPress',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Registration Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ninja_status'                 => [
				'label'   => 'Ninja Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'otter_status'                 => [
				'label'   => 'Otter Blocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'quform_status'                => [
				'label'   => 'Quform',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'sendinblue_status'            => [
				'label'   => 'Sendinblue',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'subscriber_status'            => [
				'label'   => 'Subscriber',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'supportcandy_status'          => [
				'label'   => 'Support Candy',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ultimate_member_status'       => [
				'label'   => 'Ultimate Member',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_status'           => [
				'label'   => 'WooCommerce',
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
				'label'   => 'WooCommerce Wishlists',
				'type'    => 'checkbox',
				'options' => [
					'create_list' => __( 'Create List Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforms_status'               => [
				'label'   => 'WPForms',
				'type'    => 'checkbox',
				'options' => [
					'lite' => __( 'Lite', 'hcaptcha-for-forms-and-more' ),
					'pro'  => __( 'Pro', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpdiscuz_status'              => [
				'label'   => 'WPDiscuz',
				'type'    => 'checkbox',
				'options' => [
					'comment_form' => __( 'Comment Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforo_status'                => [
				'label'   => 'WPForo',
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
		];
	}

	/**
	 * Get logo image.
	 *
	 * @param string $label Label.
	 *
	 * @return string
	 */
	private function logo( $label ) {
		$logo_file = sanitize_file_name( strtolower( $label ) . '-logo.png' );

		return sprintf(
			'<img src="%1$s" alt="%2$s Logo">',
			esc_url( HCAPTCHA_URL . "/assets/images/$logo_file" ),
			$label
		);
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		uasort(
			$this->form_fields,
			static function ( $a, $b ) {
				$a_disabled = isset( $a['disabled'] ) ? $a['disabled'] : false;
				$b_disabled = isset( $b['disabled'] ) ? $b['disabled'] : false;

				$a_label = isset( $a['label'] ) ? strtolower( $a['label'] ) : '';
				$b_label = isset( $b['label'] ) ? strtolower( $b['label'] ) : '';

				if ( $a_disabled === $b_disabled ) {
					return strcmp( $a_label, $b_label );
				}

				if ( ! $a_disabled && $b_disabled ) {
					return - 1;
				}

				return 1;
			}
		);

		foreach ( $this->form_fields as &$form_field ) {
			if ( isset( $form_field['label'] ) ) {
				$form_field['label'] = $this->logo( $form_field['label'] );
			}

			if ( $form_field['disabled'] ) {
				$form_field['section'] = self::SECTION_DISABLED;
			}
		}

		unset( $form_field );

		parent::setup_fields();
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( $arguments ) {
		if ( self::SECTION_DISABLED === $arguments['id'] ) {
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
		<div id="hcaptcha-integrations-message"></div>
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
		if ( ! $this->is_options_screen() ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/integrations$this->min_prefix.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'action'        => self::ACTIVATE_ACTION,
				'nonce'         => wp_create_nonce( self::ACTIVATE_ACTION ),
				/* translators: 1: Plugin name. */
				'activateMsg'   => __( 'Activate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Plugin name. */
				'deactivateMsg' => __( 'Deactivate %s plugin?', 'hcaptcha-for-forms-and-more' ),
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/integrations$this->min_prefix.css",
			[ SettingsBase::HANDLE ],
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

		$activate    = filter_input( INPUT_POST, 'activate', FILTER_VALIDATE_BOOLEAN );
		$status      = filter_input( INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status      = str_replace( '-', '_', $status );
		$plugin_name = $this->form_fields[ $status ]['label'];
		$plugins     = [];

		foreach ( hcaptcha()->modules as $module ) {
			if ( $module[0][0] === $status ) {
				$plugins[] = (array) $module[1];
			}
		}

		$plugins = array_merge( [], ...$plugins );

		ob_start();

		if ( $activate ) {
			activate_plugins( $plugins );

			$message = sprintf(
			/* translators: 1: Plugin(s) name(s). */
				__( '%s plugin is activated.', 'hcaptcha-for-forms-and-more' ),
				$plugin_name
			);
		} else {
			deactivate_plugins( $plugins );

			$message = sprintf(
			/* translators: 1: Plugin(s) name(s). */
				__( '%s plugin is deactivated.', 'hcaptcha-for-forms-and-more' ),
				$plugin_name
			);
		}

		ob_get_clean();

		header_remove( 'Location' );
		http_response_code( 200 );
		wp_send_json_success( esc_html( $message ) );
	}
}
