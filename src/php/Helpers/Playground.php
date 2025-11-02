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
				$content = '[contact-form-7 id="' . (int) $form->ID . '"]';

				$this->insert_post( 'Contact Form 7 Test Page', 'contact-form-7-test', $content );

				break;
			case 'woocommerce/woocommerce.php':
				// Create a new page with the WooCommerce Order Tracking shortcode.
				$content = '[woocommerce_order_tracking]';

				$this->insert_post( 'WooCommerce Order Tracking Test Page', 'wc-order-tracking-test', $content );

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
				$content       = '[fusion_builder_container type="flex"][fusion_builder_row][fusion_builder_column type="1_1" layout="1_1"][fusion_form_text label="Text" name="text" required="yes" /][fusion_form_email label="EMail" name="email" /][fusion_form_textarea label="Message" name="textarea" /][fusion_form_submit]Submit[/fusion_form_submit][fusion_form_notice success="VGhhbmsgeW91IGZvciB5b3VyIG1lc3NhZ2UuIEl0IGhhcyBiZWVuIHNlbnQu" error="VGhlcmUgd2FzIGFuIGVycm9yIHRyeWluZyB0byBzZW5kIHlvdXIgbWVzc2FnZS4gUGxlYXNlIHRyeSBhZ2FpbiBsYXRlci4=" /][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]';
				$avada_form_id = $this->insert_post( 'Avada Test Form', 'avada-test-form', $content, 'fusion_form' );

				// Create a new page with the Avada Form shortcode.
				$content = '[fusion_builder_container type="flex"][fusion_builder_row][fusion_builder_column type="1_1" layout="1_1"][fusion_form form_post_id="' . $avada_form_id . '" /][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]';

				$this->insert_post( 'Avada Test Page', 'avada-test', $content );

				break;
			case 'Divi':
				// Create a new page with the Dive Form shortcode.
				$content = '[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"][et_pb_contact_form captcha="off" email="" _module_preset="default"][et_pb_contact_field field_id="Name" field_title="Name"][/et_pb_contact_field][et_pb_contact_field field_id="Email" field_title="Email Address" field_type="email"][/et_pb_contact_field][et_pb_contact_field field_id="Message" field_title="Message" field_type="text" fullwidth_field="on"][/et_pb_contact_field][/et_pb_contact_form][/et_pb_column][/et_pb_row][/et_pb_section]';

				$this->insert_post( 'Divi Test Page', 'divi-test', $content );

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

		// WP Login page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-login',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'WP Login', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-login.php?action=logout' ),
			]
		);

		// WP Comments.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-comments',
				'parent' => self::HCAPTCHA_MENU_ID,
				'title'  => __( 'WP Comments', 'hcaptcha-for-forms-and-more' ),
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
				'title'  => __( 'WooCommerce Checkout', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( '/checkout/' ) ),
			]
		);

		// WC Login/Register page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wc-login-register',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'WooCommerce Login / Register', 'hcaptcha-for-forms-and-more' ),
				'href'   => $this->get_href( 'woocommerce_status', home_url( '/my-account/' ) ),
			]
		);

		// WC Order Tracking page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wc-order-tracking',
				'parent' => self::HCAPTCHA_MENU_WOOCOMMERCE_ID,
				'title'  => __( 'WooCommerce Order Tracking', 'hcaptcha-for-forms-and-more' ),
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
	 * @param string $title     Post title.
	 * @param string $name      Post name.
	 * @param string $content   Post content.
	 * @param string $post_type Post type.
	 *
	 * @return int|WP_Error
	 */
	private function insert_post( string $title, string $name, string $content, string $post_type = 'page' ) {
		$post    = get_page_by_path( $name, OBJECT, 'fusion_form' );
		$post_id = $post->ID ?? 0;

		if ( $post_id ) {
			return $post_id;
		}

		return wp_insert_post(
			[
				'post_type'    => $post_type,
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => $content,
				'post_name'    => $name,
			]
		);
	}
}
