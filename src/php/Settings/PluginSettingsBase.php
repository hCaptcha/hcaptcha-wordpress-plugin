<?php
/**
 * PluginSettingsBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class PluginSettingsBase
 *
 * Extends general SettingsBase suitable for any plugin with current plugin-related methods.
 */
abstract class PluginSettingsBase extends SettingsBase {

	/**
	 * Plugin prefix.
	 */
	public const PREFIX = 'hcaptcha';

	/**
	 * Settings option name.
	 */
	public const OPTION_NAME = 'hcaptcha_settings';

	/**
	 * The 'submit' button was shown.
	 *
	 * @var bool
	 */
	protected $submit_shown = false;

	/**
	 * Constructor.
	 *
	 * @param array|null $tabs Tabs of this settings page.
	 * @param array      $args Arguments.
	 */
	public function __construct( $tabs = [], $args = [] ) {
		add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ] );
		add_filter( 'update_footer', [ $this, 'update_footer' ], 1000 );

		parent::__construct( $tabs, $args );
	}

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	protected function menu_title(): string {
		$menu_title = __( 'hCaptcha', 'hcaptcha-for-forms-and-more' );

		if ( self::MODE_PAGES === $this->admin_mode ) {
			return $menu_title;
		}

		$icon = '<img class="kagg-settings-menu-image" src="' . $this->icon_url() . '" alt="hCaptcha icon">';

		return $icon . '<span class="kagg-settings-menu-title">' . $menu_title . '</span>';
	}

	/**
	 * Get icon url.
	 *
	 * @return string
	 */
	protected function icon_url(): string {
		return constant( 'HCAPTCHA_URL' ) . '/assets/images/hcaptcha-icon.svg';
	}

	/**
	 * Get an option group.
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
		$option_page = self::PREFIX;

		if ( self::MODE_TABS === $this->admin_mode || $this->is_main_menu_page() ) {
			return $option_page;
		}

		return $option_page . '-' . strtolower( $this->tab_name() );
	}

	/**
	 * Get option name.
	 *
	 * @return string
	 */
	protected function option_name(): string {
		return self::OPTION_NAME;
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
	public function setup_fields(): void {
		$prefix = self::PREFIX . '-' . static::section_title() . '-';

		foreach ( $this->form_fields as $key => $form_field ) {
			if ( ! isset( $form_field['class'] ) ) {
				$this->form_fields[ $key ]['class'] = str_replace( '_', '-', $prefix . $key );
			}
		}

		parent::setup_fields();
	}

	/**
	 * Show settings page.
	 *
	 * @return void
	 */
	public function settings_page(): void {
		?>
		<img
				src="<?php echo esc_url( constant( 'HCAPTCHA_URL' ) . '/assets/images/hcaptcha-logo.svg' ); ?>"
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

			if ( ! empty( $this->get_savable_form_fields() ) ) {
				$this->submit_button();
			}
			?>
		</form>
		<?php
	}

	/**
	 * Show submit button in any place of the form.
	 *
	 * @return void
	 */
	public function submit_button(): void {
		if ( $this->submit_shown ) {
			return;
		}

		$this->submit_shown = true;

		submit_button();
	}

	/**
	 * When a user is on the plugin admin page, display footer text that graciously asks them to rate us.
	 *
	 * @param string|mixed $text Footer text.
	 *
	 * @return string|mixed
	 * @noinspection HtmlUnknownTarget
	 */
	public function admin_footer_text( $text ) {
		if ( ! $this->is_options_screen( [] ) ) {
			return $text;
		}

		$settings = hcaptcha()->settings();
		$url      = 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post';

		return wp_kses(
			sprintf(
			/* translators: 1: plugin name, 2: wp.org review link with stars, 3: wp.org review link with text. */
				__( 'Please rate %1$s %2$s on %3$s. Thank you!', 'hcaptcha-for-forms-and-more' ),
				'<strong>' . $settings->get_plugin_name() . '</strong>',
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
				'a'      => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
				'strong' => [],
			]
		);
	}

	/**
	 * Show a plugin version in the update footer.
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
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Check ajax call.
	 *
	 * @param string $action Action.
	 *
	 * @return void
	 */
	protected function run_checks( string $action ): void {
		// Run a security check.
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}
	}
}
