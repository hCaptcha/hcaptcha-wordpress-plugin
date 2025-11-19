<?php
/**
 * Playground class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Helpers;

use Elementor\Plugin;
use HCaptcha\Admin\Events\Events;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\Integrations;
use WP_Admin_Bar;
use WP_Error;
use WP_Theme;
use WPCF7_ContactForm;

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
	private $data;

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

		$this->data = get_transient( self::PLAYGROUND_DATA ) ?: [];

		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'setup_playground' ], self::LOAD_PRIORITY );
		add_action( 'activated_plugin', [ $this, 'setup_plugin' ], 10, 2 );
		add_action( 'switch_theme', [ $this, 'setup_theme' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'head_styles' ] );
		add_action( 'admin_head', [ $this, 'head_styles' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 100 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::UPDATE_MENU_ACTION, [ $this, 'update_menu' ] );

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
		if ( $this->data ) {
			return;
		}

		$this->setup_permalinks();
		$this->setup_settings();
		Events::create_table();

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
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function setup_plugin( string $plugin, bool $network_wide ): void {
		if ( $this->data['plugins'][ $plugin ] ?? false ) {
			return;
		}

		switch ( $plugin ) {
			case 'contact-form-7/wp-contact-form-7.php':
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

				break;
			case 'elementor-pro/elementor-pro.php':
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

				add_action(
					'elementor/init',
					static function () {
						Plugin::$instance->files_manager->clear_cache();
					}
				);

				break;
			case 'woocommerce/woocommerce.php':
				// Create a new page with the WooCommerce Order Tracking shortcode.
				$this->insert_post(
					[
						'title'   => 'WooCommerce Order Tracking Test Page',
						'name'    => 'wc-order-tracking-test',
						'content' => '[woocommerce_order_tracking]',
					]
				);

				break;
			case 'wpforms/wpforms.php':
			case 'wpforms-lite/wpforms.php':
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

				break;
			default:
				return;
		}

		$this->data['plugins'][ $plugin ] = true;

		set_transient( self::PLAYGROUND_DATA, $this->data );
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
		if ( $this->data['themes'][ $new_name ] ?? false ) {
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
<!-- wp:divi/placeholder --><!-- wp:divi/section {"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/row {"module":{"advanced":{"flexColumnStructure":{"desktop":{"value":"equal-columns_1"}}},"decoration":{"layout":{"desktop":{"value":{"flexWrap":"nowrap"}}}}},"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/column {"module":{"decoration":{"sizing":{"desktop":{"value":{"flexType":"24_24"}}}}},"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/contact-form {"module":{"advanced":{"uniqueId":{"desktop":{"value":"a5de6c48-f800-45b5-bf2b-8ebb58b73d59"}},"spamProtection":{"desktop":{"value":{"useBasicCaptcha":"off"}}}}},"builderVersion":"5.0.0-public-beta.2"} -->
<!-- wp:divi/contact-field {"module":{"decoration":{"sizing":{"desktop":{"value":{"flexType":"12_24"}}}}},"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"id":{"desktop":{"value":"Name"}},"type":{"desktop":{"value":"input"}}},"innerContent":{"desktop":{"value":"Name"}}},"builderVersion":"5.0.0-public-beta.2"} /-->

<!-- wp:divi/contact-field {"module":{"decoration":{"sizing":{"desktop":{"value":{"flexType":"12_24"}}}}},"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"id":{"desktop":{"value":"Email"}},"type":{"desktop":{"value":"email"}}},"innerContent":{"desktop":{"value":"Email Address"}}},"builderVersion":"5.0.0-public-beta.2"} /-->

<!-- wp:divi/contact-field {"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"id":{"desktop":{"value":"Message"}},"type":{"desktop":{"value":"text"}}},"innerContent":{"desktop":{"value":"Message"}}},"builderVersion":"5.0.0-public-beta.2"} /-->
<!-- /wp:divi/contact-form -->
<!-- /wp:divi/column -->
<!-- /wp:divi/row -->
<!-- /wp:divi/section --><!-- /wp:divi/placeholder -->
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
			body.is-embedded #wpadminbar {
				margin-top: 4px;
			}

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
				width: 24px;
				height: 24px;
				background-image: url('<?php echo esc_url( $this->icon_url() ); ?>') !important;
				background-repeat: no-repeat;
				background-position: center;
				background-size: 24px 24px;
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
	public function admin_enqueue_scripts(): void {
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
			return $post_id;
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

		return wp_insert_post( $postarr );
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

		// Do not overwrite options if they already exist.
		if ( $settings ) {
			return;
		}

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
		$settings['extra_status']                 = [ 'comment', 'contact', 'email_optin', 'login' ];
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
				'title' =>
					'<span class="ab-icon hcaptcha-icon"></span><span class="ab-label">' .
					__( 'hCaptcha Samples', 'hcaptcha-for-forms-and-more' ) .
					'</span>',
				'meta'  => [ 'class' => self::HCAPTCHA_MENU_ID ],
			],

			// hCaptcha settings.
			[
				'id'     => 'hcaptcha-menu-hcaptcha-general',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-admin/admin.php?page=hcaptcha' ),
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

			// Extra test page.
			[
				'id'     => 'hcaptcha-menu-extra',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Extra', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'extra_status', home_url( 'divi-test' ) ),
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
