<?php
/**
 * PluginSettingsBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Settings\Abstracts\SettingsBase;

/**
 * Class PluginSettingsBase
 *
 * Extends general SettingsBase suitable for any plugin with current plugin related methods.
 */
abstract class PluginSettingsBase extends SettingsBase {

	/**
	 * Constructor.
	 *
	 * @param array|null $tabs Tabs of this settings page.
	 */
	public function __construct( $tabs = [] ) {
		add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ] );
		add_filter( 'update_footer', [ $this, 'update_footer' ], PHP_INT_MAX );

		parent::__construct( $tabs );
	}

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	protected function menu_title(): string {
		return __( 'hCaptcha', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get screen id.
	 *
	 * @return string
	 */
	public function screen_id(): string {
		return 'settings_page_hcaptcha';
	}

	/**
	 * Get option group.
	 *
	 * @return string
	 */
	protected function option_group(): string {
		return 'hcaptcha_group';
	}

	/**
	 * Get option page.
	 *
	 * @return string
	 */
	protected function option_page(): string {
		return 'hcaptcha';
	}

	/**
	 * Get option name.
	 *
	 * @return string
	 */
	protected function option_name(): string {
		return 'hcaptcha_settings';
	}

	/**
	 * Get plugin base name.
	 *
	 * @return string
	 */
	protected function plugin_basename(): string {
		return plugin_basename( constant( 'HCAPTCHA_FILE' ) );
	}

	/**
	 * Get plugin url.
	 *
	 * @return string
	 */
	protected function plugin_url(): string {
		return constant( 'HCAPTCHA_URL' );
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	protected function plugin_version(): string {
		return constant( 'HCAPTCHA_VERSION' );
	}

	/**
	 * Get settings link label.
	 *
	 * @return string
	 */
	protected function settings_link_label(): string {
		return __( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get settings link text.
	 *
	 * @return string
	 */
	protected function settings_link_text(): string {
		return __( 'Settings', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get text domain.
	 *
	 * @return string
	 */
	protected function text_domain(): string {
		return 'hcaptcha-for-forms-and-more';
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields() {
		$prefix = $this->option_page() . '-' . static::section_title() . '-';

		foreach ( $this->form_fields as $key => $form_field ) {
			if ( ! isset( $form_field['class'] ) ) {
				$this->form_fields[ $key ]['class'] = str_replace( '_', '-', $prefix . $key );
			}
		}

		parent::setup_fields();
	}

	/**
	 * Show settings page.
	 */
	public function settings_page() {
		?>
		<img
				src="<?php echo esc_url( HCAPTCHA_URL . '/assets/images/hcaptcha-logo.svg' ); ?>"
				alt="hCaptcha Logo"
				class="hcaptcha-logo"
		/>

		<form
				id="hcaptcha-options"
				class="hcaptcha-<?php echo esc_attr( $this->section_title() ); ?>"
				action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>"
				method="post">
			<?php
			do_settings_sections( $this->option_page() ); // Sections with options.
			settings_fields( $this->option_group() ); // Hidden protection fields.

			if ( ! empty( $this->form_fields ) ) {
				submit_button();
			}
			?>
		</form>
		<?php
	}

	/**
	 * When user is on the plugin admin page, display footer text that graciously asks them to rate us.
	 *
	 * @param string|mixed $text Footer text.
	 *
	 * @return string|mixed
	 * @noinspection HtmlUnknownTarget
	 */
	public function admin_footer_text( $text ) {
		if ( ! $this->is_options_screen() ) {
			return $text;
		}

		$url = 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post';

		return wp_kses(
			sprintf(
			/* translators: 1: plugin name, 2: wp.org review link with stars, 3: wp.org review link with text. */
				__( 'Please rate %1$s %2$s on %3$s. Thank you!', 'hcaptcha-for-forms-and-more' ),
				'<strong>hCaptcha for WordPress</strong>',
				sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">★★★★★</a>',
					$url
				),
				sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">WordPress.org</a>',
					$url
				)
			),
			[
				'a' => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
			]
		);
	}

	/**
	 * Show plugin version in the update footer.
	 *
	 * @param string|mixed $content The content that will be printed.
	 *
	 * @return string|mixed
	 */
	public function update_footer( $content ) {
		if ( ! $this->is_options_screen() ) {
			return $content;
		}

		return sprintf(
		/* translators: 1: plugin version. */
			__( 'Version %s', 'hcaptcha-for-forms-and-more' ),
			HCAPTCHA_VERSION
		);
	}
}
