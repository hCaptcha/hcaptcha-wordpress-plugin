<?php
/**
 * CommandPalette class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Settings\PluginSettingsBase;

/**
 * Class CommandPalette.
 *
 * Adds hCaptcha setting shortcuts to the WordPress Command Palette.
 */
class CommandPalette {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-command-palette';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaCommandPaletteObject';

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
	private function init_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if (
			! $this->is_options_screen() ||
			! current_user_can( 'manage_options' ) ||
			! wp_script_is( 'wp-commands', 'registered' )
		) {
			return;
		}

		$commands = $this->get_commands();

		if ( ! $commands ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/command-palette$min.js",
			[ 'wp-commands', 'wp-data' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'commands' => $commands,
			]
		);
	}

	/**
	 * Whether the current admin screen is an hCaptcha options screen.
	 *
	 * @return bool
	 */
	private function is_options_screen(): bool {
		$settings = hcaptcha()->settings();

		if ( ! $settings ) {
			return false;
		}

		foreach ( $settings->get_tabs() as $tab ) {
			if ( $tab instanceof PluginSettingsBase && $tab->is_options_screen( [] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get command palette commands.
	 *
	 * @return array
	 */
	private function get_commands(): array {
		$settings = hcaptcha()->settings();

		if ( ! $settings ) {
			return [];
		}

		$commands = [];

		foreach ( $settings->get_tabs() as $tab ) {
			if ( ! $tab instanceof PluginSettingsBase ) {
				continue;
			}

			$page_url   = $settings->tab_url( get_class( $tab ) );
			$page_title = $tab->command_palette_page_title();
			$tab_slug   = $tab->tab_name();

			foreach ( $tab->command_palette_form_fields() as $field_id => $field ) {
				$commands[] = $this->get_field_commands( (string) $field_id, (array) $field, $page_url, $page_title, $tab_slug );
			}
		}

		$commands = array_merge( ...$commands );

		/**
		 * Filters hCaptcha command palette commands.
		 *
		 * @param array $commands Command palette commands.
		 */
		return (array) apply_filters( 'hcap_command_palette_commands', $commands );
	}

	/**
	 * Get commands for a settings field.
	 *
	 * @param string $field_id   Field id.
	 * @param array  $field      Field data.
	 * @param string $page_url   Page URL.
	 * @param string $page_title Page title.
	 * @param string $tab_slug   Tab slug.
	 *
	 * @return array
	 */
	private function get_field_commands( string $field_id, array $field, string $page_url, string $page_title, string $tab_slug ): array {
		$type = (string) ( $field['type'] ?? '' );

		if ( 'hcaptcha' === $type ) {
			return [];
		}

		if ( in_array( $type, [ 'checkbox', 'radio' ], true ) ) {
			return $this->get_option_commands( $field_id, $field, $page_url, $page_title, $tab_slug );
		}

		$label = $this->get_label( $field['label'] ?? '' );

		if ( '' === $label ) {
			return [];
		}

		return [
			$this->build_command( $tab_slug, $field_id, $label, $page_url, $page_title, $field_id, [ $field_id ] ),
		];
	}

	/**
	 * Get commands for checkbox or radio options.
	 *
	 * @param string $field_id   Field id.
	 * @param array  $field      Field data.
	 * @param string $page_url   Page URL.
	 * @param string $page_title Page title.
	 * @param string $tab_slug   Tab slug.
	 *
	 * @return array
	 */
	private function get_option_commands( string $field_id, array $field, string $page_url, string $page_title, string $tab_slug ): array {
		$options = (array) ( $field['options'] ?? [] );

		if ( ! $options ) {
			return [];
		}

		$field_label = $this->get_label( $field['label'] ?? '' );
		$commands    = [];
		$iterator    = 0;

		foreach ( $options as $option_value => $option_label ) {
			++$iterator;

			$option_label = $this->get_label( $option_label );

			if ( '' === $option_label ) {
				continue;
			}

			$label  = $field_label && count( $options ) > 1 ? sprintf( '%1$s: %2$s', $field_label, $option_label ) : $option_label;
			$anchor = $field_id . '_' . $iterator;

			$commands[] = $this->build_command(
				$tab_slug,
				$anchor,
				$label,
				$page_url,
				$page_title,
				$anchor,
				[ $field_id, $field_label, (string) $option_value ]
			);
		}

		return $commands;
	}

	/**
	 * Build a command palette command.
	 *
	 * @param string $tab_slug   Tab slug.
	 * @param string $command_id Command id.
	 * @param string $label      Command label.
	 * @param string $page_url   Page URL.
	 * @param string $page_title Page title.
	 * @param string $anchor     Target anchor.
	 * @param array  $keywords   Keywords.
	 *
	 * @return array
	 */
	private function build_command( string $tab_slug, string $command_id, string $label, string $page_url, string $page_title, string $anchor, array $keywords ): array {
		$plugin_name   = $this->get_plugin_name();
		$command_label = sprintf( '%1$s: %2$s', $plugin_name, $label );
		$keywords      = array_merge(
			[
				__( 'hCaptcha', 'hcaptcha-for-forms-and-more' ),
				$plugin_name,
				$page_title,
			],
			$keywords
		);

		$keywords = array_values( array_unique( array_filter( array_map( [ $this, 'get_label' ], $keywords ) ) ) );

		return [
			'name'        => 'hcaptcha/settings-' . sanitize_key( str_replace( '_', '-', $tab_slug . '-' . $command_id ) ),
			'label'       => $command_label,
			'searchLabel' => $command_label,
			'url'         => esc_url_raw( $page_url . '#' . $anchor ),
			'category'    => 'view',
			'keywords'    => $keywords,
		];
	}

	/**
	 * Get the plugin name.
	 *
	 * @return string
	 */
	private function get_plugin_name(): string {
		$settings = hcaptcha()->settings();

		return $settings ? $settings->get_plugin_name() : 'hCaptcha for WP';
	}

	/**
	 * Get a plain text label.
	 *
	 * @param mixed $label Label.
	 *
	 * @return string
	 */
	private function get_label( $label ): string {
		return trim( wp_strip_all_tags( (string) $label ) );
	}
}
