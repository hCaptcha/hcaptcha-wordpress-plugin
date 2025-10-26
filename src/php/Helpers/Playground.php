<?php
/**
 * Playground class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use HCaptcha\Admin\Events\Events;
use WP_Admin_Bar;

/**
 * Class Playground.
 */
class Playground {
	/**
	 * Transient key for storing Playground data.
	 */
	private const PLAYGROUND_DATA = 'playground_data';

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
//			return;
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
		if ( $this->data ) {
			return;
		}

		Events::create_table();

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

		$form      = reset( $forms );
		$shortcode = '[contact-form-7 id="' . (int) $form->ID . '"]';

		// Create a new page with the Contact Form 7 shortcode.
		$cf7_page_id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_title'   => 'Contact Form 7 Test Page',
				'post_status'  => 'publish',
				'post_content' => $shortcode,
				'post_name'    => 'contact-form-7-test',
			]
		);

		$this->data = [
			'cf7_page_id' => $cf7_page_id,
		];

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
				'id'    => 'hcaptcha-menu',
				'title' =>
					'<span class="ab-icon hcaptcha-icon"></span><span class="ab-label">' .
					__( 'hCaptcha Samples', 'hcaptcha-for-forms-and-more' ) .
					'</span>',
				'meta'  => [ 'class' => 'hcaptcha-menu' ],
			]
		);

		// Subitem - hCaptcha settings.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-hcaptcha-general',
				'parent' => 'hcaptcha-menu',
				'title'  => __( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-admin/admin.php?page=hcaptcha' ),
			]
		);

		// Subitem - WP Login page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-login',
				'parent' => 'hcaptcha-menu',
				'title'  => __( 'WP Login', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-login.php?action=logout' ),
			]
		);

		// Subitem - WP Comments.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-comments',
				'parent' => 'hcaptcha-menu',
				'title'  => __( 'WP Comments', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '?p=1' ),
			]
		);

		// Subitem - CF7 test page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-comments',
				'parent' => 'hcaptcha-menu',
				'title'  => __( 'Contact Form 7', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '?p=' . $this->data['cf7_page_id'] ),
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
	 * Detect if the current site is a WP Playground site.
	 *
	 * @return bool
	 */
	private function is_wp_playground(): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return strpos( $host, 'playground.wordpress.net' ) !== false;
	}
}
