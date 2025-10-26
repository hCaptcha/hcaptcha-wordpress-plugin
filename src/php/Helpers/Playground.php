<?php
/**
 * Playground class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use WP_Admin_Bar;

/**
 * Class Playground.
 */
class Playground {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		if ( ! $this->is_wp_playground() ) {
			return;
		}

		add_action( 'admin_head', [ $this, 'admin_head' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 100 );
	}

	/**
	 * Add styles to the admin head.
	 *
	 * @return void
	 */
	public function admin_head(): void {
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

		// Subitem - WP Login page.
		$bar->add_node(
			[
				'id'     => 'hcaptcha-menu-wp-login',
				'parent' => 'hcaptcha-menu',
				'title'  => __( 'WP Login', 'hcaptcha-for-forms-and-more' ),
				'href'   => home_url( '/wp-login.php' ),
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
