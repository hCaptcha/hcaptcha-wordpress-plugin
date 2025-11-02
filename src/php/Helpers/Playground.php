<?php
/**
 * Playground class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Settings\Integrations;
use WP_Admin_Bar;
use WP_Error;
use WP_Theme;

/**
 * Class Playground.
 */
class Playground {
	/**
	 * Transient key for storing Playground data.
	 */
	private const PLAYGROUND_DATA = 'playground_data';

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
		if ( ! $this->is_wp_playground() ) {
			return;
		}

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	private function init(): void {
		$this->data = get_transient( self::PLAYGROUND_DATA ) ?: [];

		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_init', [ $this, 'setup_playground' ] );
		add_action( 'hcaptcha_activated_plugin', [ $this, 'setup_plugin' ], 10, 2 );
		add_action( 'switch_theme', [ $this, 'setup_theme' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'head_styles' ] );
		add_action( 'admin_head', [ $this, 'head_styles' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 100 );
	}

	/**
	 * Set up the WP Playground.
	 *
	 * @return void
	 */
	public function setup_playground(): void {
		if ( $this->data['table'] ?? false ) {
			return;
		}

		Events::create_table();

		$this->data['table'] = true;

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
		if ( $this->data['plugins'][ $plugin ] ?? false ) {
			return;
		}

		switch ( $plugin ) {
			case 'contact-form-7/wp-contact-form-7.php':
				// Create a new Contact Form 7 form.
				$form_id = $this->insert_post(
					[
						'title'   => 'Contact Form 7 Test Form',
						'name'    => 'contact-form-7-test-form',
						'content' => "<label> Your name\n    [text* your-name autocomplete:name] </label>\n\n<label> Your email\n    [email* your-email autocomplete:email] </label>\n\n[submit \"Submit\"]\n1\n[_site_title] \"[your-subject]\"\n[_site_title] <wordpress@test.test>\n[_site_admin_email]\nFrom: [your-name] [your-email]\nSubject: [your-subject]\n\nMessage Body:\n[your-message]\n\n--\nThis is a notification that a contact form was submitted on your website ([_site_title] [_site_url]).\nReply-To: [your-email]\n\n1\n1\n\n[_site_title] \"[your-subject]\"\n[_site_title] <wordpress@test.test>\n[your-email]\nMessage Body:\n[your-message]\n\n--\nThis email is a receipt for your contact form submission on our website ([_site_title] [_site_url]) in which your email address was used. If that was not you, please ignore this message.\nReply-To: [_site_admin_email]\n\n1\n1\nThank you for your message. It has been sent.\nThere was an error trying to send your message. Please try again later.\nOne or more fields have an error. Please check and try again.\nThere was an error trying to send your message. Please try again later.\nYou must accept the terms and conditions before sending your message.\nPlease fill out this field.\nThis field has a too long input.\nThis field has a too short input.\nThere was an unknown error uploading the file.\nYou are not allowed to upload files of this type.\nThe uploaded file is too large.\nThere was an error uploading the file.\nPlease enter a date in YYYY-MM-DD format.\nThis field has a too early date.\nThis field has a too late date.\nPlease enter a number.\nThis field has a too small number.\nThis field has a too large number.\nThe answer to the quiz is incorrect.\nPlease enter an email address.\nPlease enter a URL.\nPlease enter a telephone number.\n",
					]
				);

				// Create a new page with the Contact Form 7 shortcode.
				$this->insert_post(
					[
						'title'   => 'Contact Form 7 Test Page',
						'name'    => 'contact-form-7-test',
						'content' => '[contact-form-7 id="' . $form_id . '"]',
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
				// Create a new page with the Dive Form shortcode.
				$this->insert_post(
					[
						'title'   => 'Divi Test Page',
						'name'    => 'divi-test',
						'content' => '[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"][et_pb_contact_form captcha="off" email="" _module_preset="default"][et_pb_contact_field field_id="Name" field_title="Name"][/et_pb_contact_field][et_pb_contact_field field_id="Email" field_title="Email Address" field_type="email"][/et_pb_contact_field][et_pb_contact_field field_id="Message" field_title="Message" field_type="text" fullwidth_field="on"][/et_pb_contact_field][/et_pb_contact_form][/et_pb_column][/et_pb_row][/et_pb_section]',
					]
				);

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
		// Parent item without href — just opens subitems. For it, no href.
		$bar->add_node(
			[
				'id'    => self::HCAPTCHA_MENU_ID,
				'title' =>
					'<span class="ab-icon hcaptcha-icon"></span><span class="ab-label">' .
					__( 'hCaptcha Samples', 'hcaptcha-for-forms-and-more' ) .
					'</span>',
				'meta'  => [ 'class' => self::HCAPTCHA_MENU_ID ],
			]
		);

		// hCaptcha settings.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-hcaptcha-general',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-admin/admin.php?page=hcaptcha' ),
			]
		);

		// WordPress group.
		$bar->add_node(
			[
				'id'     => self::HCAPTCHA_MENU_WORDPRESS_ID,
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'WordPress Core', 'hcaptcha-for-forms-and-more' ),
			]
		);

		// WordPress Login page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-login',
				'parent' => self::HCAPTCHA_MENU_WORDPRESS_ID,
				'title'  => __( 'Login', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-login.php?action=logout' ),
			]
		);

		// WordPress Comments.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-comments',
				'parent' => self::HCAPTCHA_MENU_WORDPRESS_ID,
				'title'  => __( 'Comments', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '?p=1' ),
			]
		);

		// Avada test page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-avada',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Avada', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'avada_status', home_url( 'avada-test' ) ),
			]
		);

		// CF7 test page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-cf7',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Contact Form 7', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'cf7_status', home_url( 'contact-form-7-test' ) ),
			]
		);

		// Divi test page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-divi',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Divi', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'divi_status', home_url( 'divi-test' ) ),
			]
		);

		// Elementor Pro test page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-elementor',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Elementor Pro', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'elementor_pro_status', home_url( 'elementor-pro-test' ) ),
			]
		);

		// Extra test page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-extra',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'Extra', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'extra_status', home_url( 'divi-test' ) ),
			]
		);

		// WooCommerce group.
		$bar->add_node(
			[
				'id'     => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'WooCommerce', 'hcaptcha-for-forms-and-more' ),
			]
		);

		// WC Checkout page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wc-checkout',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'Checkout', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( '/checkout/' ) ),
			]
		);

		// WC Login/Register page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wc-login-register',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'Login/Register', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( '/my-account/' ) ),
			]
		);

		// WC Order Tracking page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wc-order-tracking',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'Order Tracking', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( '/wc-order-tracking/' ) ),
			]
		);
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
				$entity_names[] = $module[1];
			}
		}

		if ( hcaptcha()->plugin_or_theme_active( array_unique( $entity_names ) ) ) {
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
}
