<?php
/**
 * SettingsBase class file.
 *
 * @package kagg-settings
 */

namespace KAGG\Settings\Abstracts;

/**
 * Class SettingsBase
 *
 * This is an abstract class to create the settings page in any plugin.
 * It uses WordPress Settings API and general output any type of fields.
 * Similar approach is used in many plugins, including WooCommerce.
 */
abstract class SettingsBase {

	/**
	 * Admin script handle.
	 */
	public const HANDLE = 'settings-base';

	/**
	 * Plugin prefix.
	 */
	public const PREFIX = 'kagg';

	/**
	 * Network-wide option suffix.
	 */
	public const NETWORK_WIDE = '_network_wide';

	/**
	 * Pages mode.
	 */
	public const MODE_PAGES = 'pages';

	/**
	 * Tabs mode.
	 */
	public const MODE_TABS = 'tabs';

	/**
	 * Menu position.
	 *
	 * A number after 58.9 (WPForms),
	 * but before 59 (Separator) and 60 (Appearance) to avoid conflicts with other plugins.
	 */
	private const POSITION = 58.99;

	/**
	 * Network-wide menu position.
	 *
	 * A number before 25 (Settings) to avoid conflicts with other plugins.
	 */
	private const NETWORK_WIDE_POSITION = 24.99;

	/**
	 * Form fields.
	 *
	 * @var array
	 */
	protected $form_fields = [];

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Tabs of this settings page.
	 *
	 * @var array
	 */
	protected $tabs;

	/**
	 * Suffix for minified files.
	 *
	 * @var string
	 */
	protected $min_suffix;

	/**
	 * Fields and their print methods.
	 *
	 * @var array
	 */
	protected $fields;

	/**
	 * Parent slug.
	 * By default, add menu pages to Options menu.
	 *
	 * @var string
	 */
	protected $parent_slug;

	/**
	 * Mode of the settings page, e.g. 'pages' or 'tabs'.
	 *
	 * @var string
	 */
	protected $admin_mode = self::MODE_PAGES;

	/**
	 * Position of the menu.
	 *
	 * @var float
	 */
	protected $position;

	/**
	 * Get an option group.
	 *
	 * @return string
	 */
	abstract protected function option_group(): string;

	/**
	 * Get option page.
	 *
	 * @return string
	 */
	abstract protected function option_page(): string;

	/**
	 * Get option name.
	 *
	 * @return string
	 */
	abstract protected function option_name(): string;

	/**
	 * Get plugin base name.
	 *
	 * @return string
	 */
	abstract protected function plugin_basename(): string;

	/**
	 * Get plugin url.
	 *
	 * @return string
	 */
	abstract protected function plugin_url(): string;

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	abstract protected function plugin_version(): string;

	/**
	 * Get settings link label.
	 *
	 * @return string
	 */
	abstract protected function settings_link_label(): string;

	/**
	 * Get settings link text.
	 *
	 * @return string
	 */
	abstract protected function settings_link_text(): string;

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	abstract protected function page_title(): string;

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	abstract protected function menu_title(): string;

	/**
	 * Show setting page.
	 */
	abstract public function settings_page();

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	abstract protected function section_title(): string;

	/**
	 * Show section.
	 *
	 * @param array $arguments Arguments.
	 */
	abstract public function section_callback( array $arguments );

	/**
	 * Get text domain.
	 *
	 * @return string
	 */
	abstract protected function text_domain(): string;

	/**
	 * SettingsBase constructor.
	 *
	 * @param array|null $tabs Tabs of this settings page.
	 * @param array      $args Arguments.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function __construct( $tabs = [], $args = [] ) {
		$this->tabs = $tabs;

		$this->process_args( $args );

		$this->fields = [
			'text'     => [ $this, 'print_text_field' ],
			'password' => [ $this, 'print_text_field' ],
			'hidden'   => [ $this, 'print_text_field' ],
			'number'   => [ $this, 'print_number_field' ],
			'textarea' => [ $this, 'print_textarea_field' ],
			'checkbox' => [ $this, 'print_checkbox_field' ],
			'radio'    => [ $this, 'print_radio_field' ],
			'select'   => [ $this, 'print_select_field' ],
			'multiple' => [ $this, 'print_multiple_select_field' ],
			'file'     => [ $this, 'print_file_field' ],
			'table'    => [ $this, 'print_table_field' ],
			'button'   => [ $this, 'print_button_field' ],
		];

		if ( self::MODE_PAGES === $this->admin_mode || ! $this->is_tab() ) {
			add_action( 'current_screen', [ $this, 'setup_tabs_section' ], 9 );

			$tag = $this->is_network_wide() ? 'network_admin_menu' : 'admin_menu';

			add_action( $tag, [ $this, 'add_settings_page' ] );
		}

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->min_suffix = defined( 'SCRIPT_DEBUG' ) && constant( 'SCRIPT_DEBUG' ) ? '' : '.min';

		$this->form_fields();
		$this->init_settings();

		if ( is_admin() && ( $this->is_main_menu_page() || $this->is_tab_active( $this ) ) ) {
			$this->init_hooks();
		}
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'base_admin_enqueue_scripts' ] );
		add_action( 'admin_page_access_denied', [ $this, 'base_admin_page_access_denied' ] );

		if ( $this->is_main_menu_page() ) {
			add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
			add_filter( 'plugin_action_links_' . $this->plugin_basename(), [ $this, 'add_settings_link' ] );
			add_filter( 'network_admin_plugin_action_links_' . $this->plugin_basename(), [ $this, 'add_settings_link' ] );
		}

		if ( $this->is_tab_active( $this ) ) {
			add_filter( 'pre_update_option_' . $this->option_name(), [ $this, 'pre_update_option_filter' ], 10, 2 );
			add_filter(
				'pre_update_site_option_option_' . $this->option_name(),
				[ $this, 'pre_update_option_filter' ],
				10,
				2
			);

			add_action( 'current_screen', [ $this, 'setup_fields' ] );
			add_action( 'current_screen', [ $this, 'setup_sections' ], 11 );
		}
	}

	/**
	 * Init form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = [];
	}

	/**
	 * Process arguments.
	 *
	 * @param array $args Arguments.
	 *
	 * @return void
	 */
	protected function process_args( array $args ): void {
		$args = wp_parse_args(
			$args,
			[
				'mode'     => $this->get_menu_position(),
				'parent'   => null,
				'position' => null,
			]
		);

		$this->admin_mode = in_array( $args['mode'], [ self::MODE_PAGES, self::MODE_TABS ], true ) ?
			$args['mode'] :
			self::MODE_PAGES;

		if ( null === $args['parent'] ) {
			$wp_settings_slug  = is_multisite() && $this->is_network_wide() ? 'settings.php' : 'options-general.php';
			$this->parent_slug = self::MODE_PAGES === $this->admin_mode ? '' : $wp_settings_slug;
		} else {
			$this->parent_slug = $args['parent'];
		}

		if ( null === $args['position'] ) {
			$hash           = hexdec( sha1( self::PREFIX ) );
			$pow            = floor( log10( $hash ) );
			$position       = is_multisite() && $this->is_network_wide() ? self::NETWORK_WIDE_POSITION : self::POSITION;
			$this->position = round( $position + $hash / 10 ** ( $pow + 4 ), 6 );
		} else {
			$this->position = (float) $args['position'];
		}
	}

	/**
	 * Is this the main menu page?
	 *
	 * @return bool
	 */
	protected function is_main_menu_page(): bool {
		// The main menu page has tabs as an array.
		return null !== $this->tabs;
	}

	/**
	 * Get tab name.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function tab_name(): string {
		return $this->get_class_name();
	}

	/**
	 * Get class name without a namespace.
	 *
	 * @return string
	 */
	protected function get_class_name(): string {
		$path = explode( '\\', get_class( $this ) );

		return array_pop( $path );
	}

	/**
	 * Is this a tab?
	 *
	 * @return bool
	 */
	protected function is_tab(): bool {
		// Tab has null in tabs property.
		return null === $this->tabs;
	}

	/**
	 * Add a link to the plugin setting page on the plugins' page.
	 *
	 * @param array|mixed $actions An array of plugin action links.
	 *                             By default, this can include 'activate', 'deactivate', and 'delete'.
	 *                             With Multisite active, this can also include 'network_active' and 'network_only'
	 *                             items.
	 *
	 * @return array|string[] Plugin links
	 */
	public function add_settings_link( $actions ): array {
		$new_actions = [
			'settings' =>
				'<a href="' . admin_url( $this->parent_slug . '?page=' . $this->option_page() ) .
				'" aria-label="' . esc_attr( $this->settings_link_label() ) . '">' .
				esc_html( $this->settings_link_text() ) . '</a>',
		];

		return array_merge( $new_actions, (array) $actions );
	}

	/**
	 * Initialise Settings.
	 *
	 * Store all settings in a single database entry
	 * and make sure the $settings array is either the default
	 * or the settings stored in the database.
	 *
	 * @return void
	 */
	protected function init_settings(): void {
		if ( $this->is_network_wide() ) {
			$this->settings = get_site_option( $this->option_name(), null );
		} else {
			$this->settings = get_option( $this->option_name(), null );
		}

		$settings_exist                       = is_array( $this->settings );
		$this->settings                       = (array) $this->settings;
		$form_fields                          = $this->form_fields();
		$network_wide_setting                 = array_key_exists( self::NETWORK_WIDE, $this->settings ) ?
			$this->settings[ self::NETWORK_WIDE ] :
			$this->get_network_wide();
		$this->settings[ self::NETWORK_WIDE ] = $network_wide_setting;

		if ( $settings_exist ) {
			$this->settings = array_merge(
				wp_list_pluck( $form_fields, 'default' ),
				$this->settings
			);

			return;
		}

		// If there are no settings defined, use defaults.
		$this->settings = array_merge(
			array_fill_keys( array_keys( $form_fields ), '' ),
			wp_list_pluck( $form_fields, 'default' )
		);
	}

	/**
	 * Get all form fields.
	 *
	 * @return mixed
	 */
	protected function all_form_fields() {
		$form_fields[] = $this->form_fields();
		$tabs          = $this->tabs ?: [];

		/**
		 * Tab.
		 *
		 * @var SettingsBase $tab
		 */
		foreach ( $tabs as $tab ) {
			$form_fields[] = $tab->form_fields();
		}

		return array_merge( [], ...$form_fields );
	}

	/**
	 * Get the form fields after initialization.
	 *
	 * @return array of options
	 */
	protected function form_fields(): array {
		if ( empty( $this->form_fields ) ) {
			$this->init_form_fields();
			array_walk( $this->form_fields, [ $this, 'set_defaults' ] );
		}

		return $this->form_fields;
	}

	/**
	 * Set default required properties for each field.
	 *
	 * @param array  $field Settings field.
	 * @param string $id    Settings field id.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function set_defaults( array &$field, string $id ): void {
		$field = wp_parse_args(
			$field,
			[
				'default'  => '',
				'disabled' => false,
				'field_id' => '',
				'label'    => '',
				'section'  => '',
				'title'    => '',
			]
		);
	}

	/**
	 * Add settings' page to the menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		if ( $this->parent_slug ) {
			add_submenu_page(
				$this->parent_slug,
				$this->get_active_tab()->page_title(),
				$this->menu_title(),
				'manage_options',
				$this->option_page(),
				[ $this, 'settings_base_page' ]
			);
		} elseif ( $this->is_main_menu_page() ) {
			$this->position += 1e-6;

			add_menu_page(
				$this->page_title(),
				$this->menu_title(),
				'manage_options',
				$this->option_page(),
				[ $this, 'settings_base_page' ],
				$this->icon_url(),
				$this->position
			);

			add_submenu_page(
				$this->option_page(),
				$this->page_title(),
				$this->page_title(),
				'manage_options',
				$this->option_page(),
				[ $this, 'settings_base_page' ]
			);

			foreach ( $this->tabs as $tab ) {
				add_submenu_page(
					$this->option_page(),
					$tab->page_title(),
					$tab->page_title(),
					'manage_options',
					$tab->option_page(),
					[ $tab, 'settings_base_page' ]
				);
			}
		}
	}

	/**
	 * Invoke relevant settings_page() basing on tabs.
	 *
	 * @return void
	 */
	public function settings_base_page(): void {
		echo '<div class="wrap">';

		$this->get_active_tab()->settings_page();

		echo '</div>';
	}

	/**
	 * Enqueue scripts in admin.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
	}

	/**
	 * Enqueue relevant admin_enqueue_scripts() basing on tabs.
	 * Enqueue admin style.
	 *
	 * @return void
	 */
	public function base_admin_enqueue_scripts(): void {
		wp_enqueue_style(
			static::PREFIX . '-settings-admin',
			$this->plugin_url() . "/assets/css/settings-admin$this->min_suffix.css",
			[],
			$this->plugin_version()
		);

		if ( ! $this->is_options_screen() ) {
			return;
		}

		wp_enqueue_script(
			static::PREFIX . '-' . self::HANDLE,
			$this->plugin_url() . "/assets/js/settings-base$this->min_suffix.js",
			[],
			$this->plugin_version(),
			true
		);

		wp_enqueue_style(
			static::PREFIX . '-' . self::HANDLE,
			$this->plugin_url() . "/assets/css/settings-base$this->min_suffix.css",
			[],
			$this->plugin_version()
		);

		$this->get_active_tab()->admin_enqueue_scripts();
	}

	/**
	 * Filter denied access to the settings page.
	 * It is necessary when switching network_wide option.
	 *
	 * @return void
	 */
	public function base_admin_page_access_denied(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( static::PREFIX !== $page ) {
			return;
		}

		$url = is_multisite() && $this->is_network_wide() ?
			network_admin_url( 'admin.php?page=' . $this->option_page() ) :
			admin_url( 'admin.php?page=' . $this->option_page() );

		if ( wp_get_raw_referer() === $url ) {
			// Prevent infinite loop.
			return;
		}

		wp_safe_redirect( $url );
		$this->exit();
	}

	/**
	 * Exit wrapper for test purposes.
	 *
	 * @return void
	 */
	protected function exit(): void {
		// @codeCoverageIgnoreStart
		exit();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Setup settings sections.
	 *
	 * @return void
	 */
	public function setup_sections(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$tab = $this->get_active_tab();

		if ( empty( $this->form_fields ) ) {
			add_settings_section(
				$this->section_title(),
				'',
				[ $tab, 'section_callback' ],
				$tab->option_page()
			);

			return;
		}

		foreach ( $this->form_fields as $form_field ) {
			add_settings_section(
				$form_field['section'],
				$form_field['title'],
				[ $tab, 'section_callback' ],
				$tab->option_page()
			);
		}
	}

	/**
	 * Setup tabs section.
	 *
	 * @return void
	 */
	public function setup_tabs_section(): void {
		if ( ! $this->is_main_menu_page() ) {
			return;
		}

		$tab = $this->get_active_tab();

		if ( ! $this->is_options_screen( [ 'options', $tab->option_page() ] ) ) {
			return;
		}

		add_settings_section(
			'tabs_section',
			'',
			[ $this, 'tabs_callback' ],
			$tab->option_page()
		);
	}

	/**
	 * Show tabs.
	 */
	public function tabs_callback(): void {
		if ( ! count( $this->tabs ?? [] ) ) {
			return;
		}

		?>
		<div class="<?php echo esc_attr( static::PREFIX . '-settings-tabs' ); ?>">
			<span class="<?php echo esc_attr( static::PREFIX . '-settings-links' ); ?>">
			<?php

			$this->tab_link( $this );

			foreach ( $this->tabs as $tab ) {
				$this->tab_link( $tab );
			}

			?>
			</span>
			<?php

			/**
			 * Fires before settings tab closing tag.
			 */
			do_action( 'kagg_settings_tab' );

			?>
		</div>
		<?php
	}

	/**
	 * Get tab url.
	 *
	 * @param SettingsBase $tab Tabs of the current settings page.
	 *
	 * @return string
	 */
	public function tab_url( SettingsBase $tab ): string {
		$url = is_multisite() && $this->is_network_wide() ?
			network_admin_url( 'admin.php?page=' . $tab->option_page() ) :
			menu_page_url( $tab->option_page(), false );

		if ( self::MODE_TABS === $this->admin_mode ) {
			$url = add_query_arg( 'tab', strtolower( $tab->tab_name() ), $url );
		}

		return $url;
	}

	/**
	 * Show a tab link.
	 *
	 * @param SettingsBase $tab Tabs of the current settings page.
	 *
	 * @return void
	 */
	private function tab_link( SettingsBase $tab ): void {
		$url    = $this->tab_url( $tab );
		$active = $tab->is_tab_active( $tab ) ? ' active' : '';
		$class  = static::PREFIX . '-settings-tab' . $active;

		?>
		<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $url ); ?>">
			<?php echo esc_html( $tab->page_title() ); ?>
		</a>
		<?php
	}

	/**
	 * Check if the tab is active.
	 *
	 * @param SettingsBase $tab Tab of the current settings page.
	 *
	 * @return bool
	 */
	protected function is_tab_active( SettingsBase $tab ): bool {
		switch ( $this->admin_mode ) {
			case self::MODE_PAGES:
				$current_page_name = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( null === $current_page_name ) {
					$names             = $this->get_names_from_referer();
					$current_page_name = $names['page'];
				}

				return $tab->option_page() === $current_page_name;
			case self::MODE_TABS:
				$current_page_name = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$current_tab_name  = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( null === $current_page_name || null === $current_tab_name ) {
					$names             = $this->get_names_from_referer();
					$current_page_name = $names['page'];
					$current_tab_name  = $names['tab'];
				}

				if (
					( $current_page_name !== $this->option_page() || null === $current_tab_name ) &&
					! $tab->is_tab()
				) {
					return true;
				}

				return strtolower( $tab->tab_name() ) === $current_tab_name;
			default:
				return false;
		}
	}

	/**
	 * Get page and tab names from referer.
	 *
	 * @return array
	 */
	protected function get_names_from_referer(): array {
		if ( wp_doing_ajax() ) {
			$query = wp_get_referer();
		} else {
			$query = filter_input( INPUT_POST, '_wp_http_referer', FILTER_SANITIZE_URL );
		}

		$query = wp_parse_url( (string) $query, PHP_URL_QUERY ) ?: '';
		$args  = $this->wp_parse_str( $query );

		return [
			'page' => $args['page'] ?? null,
			'tab'  => $args['tab'] ?? null,
		];
	}

	// @codeCoverageIgnoreStart

	/**
	 * Polyfill of the wp_parse_str().
	 * Added for test reasons.
	 *
	 * @param string $input_string Input string.
	 *
	 * @return array
	 */
	protected function wp_parse_str( string $input_string ): array {
		wp_parse_str( $input_string, $result );

		return $result;
	}

	// @codeCoverageIgnoreEnd

	/**
	 * Get tabs.
	 *
	 * @return array
	 */
	public function get_tabs(): array {
		return $this->tabs;
	}

	/**
	 * Get active tab.
	 *
	 * @return SettingsBase
	 */
	public function get_active_tab(): SettingsBase {
		if ( ! empty( $this->tabs ) ) {
			foreach ( $this->tabs as $tab ) {
				if ( $this->is_tab_active( $tab ) ) {
					return $tab;
				}
			}
		}

		return $this;
	}

	/**
	 * Setup settings fields.
	 *
	 * @return void
	 */
	public function setup_fields(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$args = [
			'sanitize_callback' => [ $this, 'sanitize_option_callback' ],
		];

		register_setting( $this->option_group(), $this->option_name(), $args );

		/**
		 * Filters fields and their print methods to allow custom fields.
		 *
		 * @param array $fields Fields.
		 */
		$this->fields = apply_filters( 'kagg_settings_fields', $this->fields );

		foreach ( $this->form_fields as $key => $field ) {
			$field['field_id'] = $key;

			add_settings_field(
				$key,
				$field['label'],
				[ $this, 'field_callback' ],
				$this->option_page(),
				$field['section'],
				$field
			);
		}
	}

	/**
	 * Filters an option value following sanitization.
	 *
	 * @param array|mixed $value The sanitized option value.
	 *
	 * @return array
	 */
	public function sanitize_option_callback( $value ): array {
		// Remove unexpected settings.
		$settings = array_intersect_key( (array) $value, $this->form_fields() );

		foreach ( $settings as $key => $setting ) {
			$type = $this->form_fields[ $key ]['type'];

			switch ( $type ) {
				case 'checkbox':
					$settings[ $key ] = array_map( 'sanitize_text_field', $setting );
					break;
				case 'textarea':
					$settings[ $key ] = wp_kses_post( $setting );
					break;
				default:
					$settings[ $key ] = sanitize_text_field( $setting );
			}
		}

		return $settings;
	}

	/**
	 * Print text/password field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 */
	protected function print_text_field( array $arguments ): void {
		$value        = $this->get( $arguments['field_id'] );
		$autocomplete = '';
		$lp_ignore    = '';

		if ( 'password' === $arguments['type'] ) {
			$autocomplete = 'new-password';
			$lp_ignore    = 'true';
		}

		$autocomplete = $arguments['autocomplete'] ?? $autocomplete;
		$lp_ignore    = $arguments['lp_ignore'] ?? $lp_ignore;

		printf(
			'<input %1$s name="%2$s[%3$s]" id="%3$s" type="%4$s"' .
			' placeholder="%5$s" value="%6$s" autocomplete="%7$s" data-lpignore="%8$s" class="regular-text" />',
			disabled( $arguments['disabled'], true, false ),
			esc_html( $this->option_name() ),
			esc_attr( $arguments['field_id'] ),
			esc_attr( $arguments['type'] ),
			esc_attr( $arguments['placeholder'] ),
			esc_html( $value ),
			esc_attr( $autocomplete ),
			esc_attr( $lp_ignore )
		);
	}

	/**
	 * Print number field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 */
	protected function print_number_field( array $arguments ): void {
		$value = $this->get( $arguments['field_id'] );
		$min   = $arguments['min'];
		$max   = $arguments['max'];
		$step  = $arguments['step'];

		printf(
			'<input %1$s name="%2$s[%3$s]" id="%3$s" type="%4$s"' .
			' placeholder="%5$s" value="%6$s" class="regular-text" min="%7$s" max="%8$s" step="%9$s" />',
			disabled( $arguments['disabled'], true, false ),
			esc_html( $this->option_name() ),
			esc_attr( $arguments['field_id'] ),
			esc_attr( $arguments['type'] ),
			esc_attr( $arguments['placeholder'] ),
			esc_html( $value ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step )
		);
	}

	/**
	 * Print textarea field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_textarea_field( array $arguments ): void {
		$value = $this->get( $arguments['field_id'] );

		printf(
			'<textarea %1$s name="%2$s[%3$s]" id="%3$s" placeholder="%4$s" rows="5" cols="50">%5$s</textarea>',
			disabled( $arguments['disabled'], true, false ),
			esc_html( $this->option_name() ),
			esc_attr( $arguments['field_id'] ),
			esc_attr( $arguments['placeholder'] ),
			wp_kses_post( $value )
		);
	}

	/**
	 * Print the checkbox field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 * @noinspection HtmlWrongAttributeValue
	 */
	protected function print_checkbox_field( array $arguments ): void {
		$value = (array) $this->get( $arguments['field_id'] );

		if ( empty( $arguments['options'] ) || ! is_array( $arguments['options'] ) ) {
			$arguments['options'] = [ 'on' => '' ];
		}

		$options_markup = '';
		$iterator       = 0;

		if ( is_bool( $arguments['disabled'] ) ) {
			$arguments['disabled'] = $arguments['disabled'] ? $arguments['options'] : [];
		}

		foreach ( $arguments['options'] as $key => $label ) {
			++$iterator;
			$options_markup .= sprintf(
				'<label for="%2$s_%7$s">' .
				'<input id="%2$s_%7$s" name="%1$s[%2$s][]" type="%3$s" value="%4$s" %5$s %8$s />' .
				'%6$s' .
				'</label>' .
				'<br/>',
				esc_html( $this->option_name() ),
				$arguments['field_id'],
				$arguments['type'],
				$key,
				checked( in_array( $key, $value, true ), true, false ),
				$label,
				$iterator,
				disabled( in_array( $label, $arguments['disabled'], true ), true, false )
			);
		}

		$element_disabled = empty( array_diff( $arguments['options'], $arguments['disabled'] ) );

		printf(
			'<fieldset %1$s>%2$s</fieldset>',
			disabled( $element_disabled, true, false ),
			wp_kses(
				$options_markup,
				[
					'label' => [
						'for' => [],
					],
					'input' => [
						'id'       => [],
						'name'     => [],
						'type'     => [],
						'value'    => [],
						'checked'  => [],
						'disabled' => [],
					],
					'br'    => [],
				]
			)
		);
	}

	/**
	 * Print radio field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 * @noinspection HtmlWrongAttributeValue
	 */
	protected function print_radio_field( array $arguments ): void {
		$value = $this->get( $arguments['field_id'] );

		if ( empty( $arguments['options'] ) || ! is_array( $arguments['options'] ) ) {
			return;
		}

		$options_markup = '';
		$iterator       = 0;

		if ( is_bool( $arguments['disabled'] ) ) {
			$arguments['disabled'] = $arguments['disabled'] ? $arguments['options'] : [];
		}

		foreach ( $arguments['options'] as $key => $label ) {
			++$iterator;
			$options_markup .= sprintf(
				'<label for="%2$s_%7$s">' .
				'<input id="%2$s_%7$s" name="%1$s[%2$s]" type="%3$s" value="%4$s" %5$s %8$s />' .
				'%6$s' .
				'</label>' .
				'<br/>',
				esc_html( $this->option_name() ),
				$arguments['field_id'],
				$arguments['type'],
				$key,
				checked( $value, $key, false ),
				$label,
				$iterator,
				disabled( in_array( $label, $arguments['disabled'], true ), true, false )
			);
		}

		$element_disabled = empty( array_diff( $arguments['options'], $arguments['disabled'] ) );

		printf(
			'<fieldset %1$s>%2$s</fieldset>',
			disabled( $element_disabled, true, false ),
			wp_kses(
				$options_markup,
				[
					'label' => [
						'for' => [],
					],
					'input' => [
						'id'       => [],
						'name'     => [],
						'type'     => [],
						'value'    => [],
						'checked'  => [],
						'disabled' => [],
					],
					'br'    => [],
				]
			)
		);
	}

	/**
	 * Print select field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_select_field( array $arguments ): void {
		$value = $this->get( $arguments['field_id'] );

		if ( empty( $arguments['options'] ) || ! is_array( $arguments['options'] ) ) {
			return;
		}

		$options_markup = '';

		if ( is_bool( $arguments['disabled'] ) ) {
			$arguments['disabled'] = $arguments['disabled'] ? $arguments['options'] : [];
		}

		foreach ( $arguments['options'] as $key => $label ) {
			$options_markup .= sprintf(
				'<option value="%s" %s %s>%s</option>',
				$key,
				selected( $value, $key, false ),
				disabled( in_array( $label, $arguments['disabled'], true ), true, false ),
				$label
			);
		}

		$element_disabled = empty( array_diff( $arguments['options'], $arguments['disabled'] ) );

		printf(
			'<select %1$s name="%2$s[%3$s]">%4$s</select>',
			disabled( $element_disabled, true, false ),
			esc_html( $this->option_name() ),
			esc_html( $arguments['field_id'] ),
			wp_kses(
				$options_markup,
				[
					'option' => [
						'value'    => [],
						'selected' => [],
						'disabled' => [],
					],
				]
			)
		);
	}

	/**
	 * Print multiple select field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_multiple_select_field( array $arguments ): void {
		$value = $this->get( $arguments['field_id'] );

		if ( empty( $arguments['options'] ) || ! is_array( $arguments['options'] ) ) {
			return;
		}

		$options_markup = '';

		if ( is_bool( $arguments['disabled'] ) ) {
			$arguments['disabled'] = $arguments['disabled'] ? $arguments['options'] : [];
		}

		foreach ( $arguments['options'] as $key => $label ) {
			$selected = '';

			if ( is_array( $value ) && in_array( $key, $value, true ) ) {
				$selected = selected( $key, $key, false );
			}

			$options_markup .= sprintf(
				'<option value="%s" %s %s>%s</option>',
				$key,
				$selected,
				disabled( in_array( $label, $arguments['disabled'], true ), true, false ),
				$label
			);
		}

		$element_disabled = empty( array_diff( $arguments['options'], $arguments['disabled'] ) );

		printf(
			'<select %1$s multiple="multiple" name="%2$s[%3$s][]">%4$s</select>',
			disabled( $element_disabled, true, false ),
			esc_html( $this->option_name() ),
			esc_html( $arguments['field_id'] ),
			wp_kses(
				$options_markup,
				[
					'option' => [
						'value'    => [],
						'selected' => [],
						'disabled' => [],
					],
				]
			)
		);
	}

	/**
	 * Print file field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_file_field( array $arguments ): void {
		$multiple = (bool) ( $arguments['multiple'] ?? '' );
		$accept   = $arguments['accept'] ?? '';

		printf(
			'<input %1$s name="%2$s[%3$s]%4$s" id="%3$s" type="file" %5$s %6$s/>',
			disabled( $arguments['disabled'], true, false ),
			esc_html( $this->option_name() ),
			esc_attr( $arguments['field_id'] ),
			esc_attr( $multiple ? '[]' : '' ),
			esc_attr( $multiple ? 'multiple' : '' ),
			$accept ? 'accept="' . esc_attr( $accept ) . '"' : ''
		);
	}

	/**
	 * Print table field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_table_field( array $arguments ): void {
		$value = $this->get( $arguments['field_id'] );

		if ( ! is_array( $value ) ) {
			return;
		}

		printf(
			'<fieldset %s>',
			disabled( $arguments['disabled'], true, false )
		);

		$iterator = 0;

		foreach ( $value as $key => $cell_value ) {
			$id = $arguments['field_id'] . '-' . $iterator;

			echo '<div class="' . esc_attr( self::PREFIX . '-table-cell' ) . '">';
			printf(
				'<label for="%1$s">%2$s</label>',
				esc_html( $id ),
				esc_html( $key )
			);
			printf(
				'<input name="%1$s[%2$s][%3$s]" id="%4$s" type="%5$s"' .
				' placeholder="%6$s" value="%7$s" class="regular-text" />',
				esc_html( $this->option_name() ),
				esc_attr( $arguments['field_id'] ),
				esc_attr( $key ),
				esc_attr( $id ),
				'text',
				esc_attr( $arguments['placeholder'] ),
				esc_html( $cell_value )
			);
			echo '</div>';

			++$iterator;
		}

		echo '</fieldset>';
	}

	/**
	 * Print button field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_button_field( array $arguments ): void {
		$disabled = $arguments['disabled'] ?? '';
		$field_id = $arguments['field_id'] ?? '';
		$text     = $arguments['text'] ?? '';

		printf(
			'<button %1$s id="%2$s" class="button button-secondary" type="button"/>%3$s</button>',
			disabled( $disabled, true, false ),
			esc_attr( $field_id ),
			esc_attr( $text )
		);
	}

	/**
	 * Output settings field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 */
	public function field_callback( array $arguments ): void {
		if ( empty( $arguments['field_id'] ) ) {
			return;
		}

		$type = $arguments['type'] ?? '';

		if ( ! array_key_exists( $type, $this->fields ) ) {
			return;
		}

		$method = $this->fields[ $type ];

		if ( ! is_callable( $method ) ) {
			return;
		}

		$arguments = wp_parse_args(
			$arguments,
			[
				'field_id'     => '',
				'helper'       => '',
				'label'        => '',
				'max'          => '',
				'min'          => '',
				'step'         => '',
				'options'      => [],
				'placeholder'  => '',
				'supplemental' => '',
				'type'         => '',
				'text'         => '',
			]
		);

		$method( $arguments );

		$this->print_helper( $arguments['helper'] );
		$this->print_supplemental( $arguments['supplemental'] );
	}

	/**
	 * Get plugin option.
	 *
	 * @param string $key         Setting name.
	 * @param mixed  $empty_value Empty value for this setting.
	 *
	 * @return string|array The value specified for the option or a default value for the option.
	 */
	public function get( string $key, $empty_value = null ) {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		// Get option default if unset.
		if ( ! isset( $this->settings[ $key ] ) ) {
			$form_fields            = $this->all_form_fields();
			$this->settings[ $key ] = isset( $form_fields[ $key ] ) ? $this->field_default( $form_fields[ $key ] ) : '';
		}

		if ( '' === $this->settings[ $key ] && ! is_null( $empty_value ) ) {
			$this->settings[ $key ] = $empty_value;
		}

		return $this->settings[ $key ];
	}

	/**
	 * Set plugin option.
	 *
	 * @param string $key   Setting name.
	 * @param mixed  $value Value for this setting.
	 *
	 * @return bool
	 */
	public function set( string $key, $value ): bool {
		if ( ! isset( $this->settings[ $key ] ) ) {
			return false;
		}

		$this->settings[ $key ] = $value;

		return true;
	}

	/**
	 * Get a field default value. Defaults to '' if not set.
	 *
	 * @param array $field Setting field default value.
	 *
	 * @return mixed
	 */
	protected function field_default( array $field ) {
		return empty( $field['default'] ) ? '' : $field['default'];
	}

	/**
	 * Set field.
	 *
	 * @param string $key       Setting name.
	 * @param string $field_key Field key.
	 * @param mixed  $value     Value.
	 *
	 * @return bool True if done.
	 */
	public function set_field( string $key, string $field_key, $value ): bool {
		if ( ! array_key_exists( $key, $this->form_fields ) ) {
			return false;
		}

		$this->form_fields[ $key ][ $field_key ] = $value;

		return true;
	}

	/**
	 * Update plugin option.
	 *
	 * @param string $key   Setting name.
	 * @param mixed  $value Setting value.
	 *
	 * @return void
	 */
	public function update_option( string $key, $value ): void {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		$this->settings[ $key ] = $value;

		update_option( $this->option_name(), $this->settings );
	}

	/**
	 * Filter plugin option update.
	 *
	 * @param mixed $value     New option value.
	 * @param mixed $old_value Old option value.
	 *
	 * @return mixed
	 */
	public function pre_update_option_filter( $value, $old_value ) {
		if ( $value === $old_value ) {
			return $value;
		}

		$value     = is_array( $value ) ? $value : [];
		$old_value = is_array( $old_value ) ? $old_value : [];

		foreach ( $this->form_fields() as $key => $form_field ) {
			if ( 'file' === $form_field['type'] ) {
				unset( $value[ $key ], $old_value[ $key ] );
				continue;
			}

			if ( 'checkbox' !== $form_field['type'] || isset( $value[ $key ] ) ) {
				continue;
			}

			if ( ! $form_field['disabled'] || ! isset( $old_value[ $key ] ) ) {
				$value[ $key ] = [];
			}
		}

		// We save only one tab, so merge with all existing tabs.
		$value                       = array_merge( $old_value, $value );
		$value[ self::NETWORK_WIDE ] = array_key_exists( self::NETWORK_WIDE, $value ) ? $value[ self::NETWORK_WIDE ] : [];

		update_site_option( $this->option_name() . self::NETWORK_WIDE, $value[ self::NETWORK_WIDE ] );

		if ( empty( $value[ self::NETWORK_WIDE ] ) ) {
			return $value;
		}

		update_site_option( $this->option_name(), $value );

		return $old_value;
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			$this->text_domain(),
			false,
			dirname( $this->plugin_basename() ) . '/languages/'
		);
	}

	/**
	 * Is current admin screen the plugin options screen.
	 *
	 * @param string|array $ids Additional screen id or ids to check.
	 *
	 * @return bool
	 */
	protected function is_options_screen( $ids = 'options' ): bool {
		$ids = (array) $ids;

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return false;
		}

		$current_suffix = preg_replace( '/.+_page_/', '', $current_screen->id );

		if ( is_multisite() && $this->is_network_wide() ) {
			$current_suffix = preg_replace( '/-network$/', '', $current_suffix );
		}

		return $this->option_page() === $current_suffix || in_array( $current_suffix, $ids, true );
	}

	/**
	 * Print help text if it exists.
	 *
	 * @param string $helper Helper.
	 *
	 * @return void
	 */
	protected function print_helper( string $helper ): void {
		if ( ! $helper ) {
			return;
		}

		printf(
			'<span class="helper"><span class="helper-content">%s</span></span>',
			wp_kses_post( $helper )
		);
	}

	/**
	 * Print supplemental id if it exists.
	 *
	 * @param string $supplemental Supplemental.
	 *
	 * @return void
	 */
	protected function print_supplemental( string $supplemental ): void {
		if ( ! $supplemental ) {
			return;
		}

		printf(
			'<p class="description">%s</p>',
			wp_kses_post( $supplemental )
		);
	}

	/**
	 * Get network_wide setting.
	 *
	 * @return array
	 */
	protected function get_network_wide(): array {
		static $network_wide = null;

		if ( null === $network_wide ) {
			$network_wide = (array) get_site_option( $this->option_name() . self::NETWORK_WIDE, [] );
		}

		return $network_wide;
	}

	/**
	 * Whether network_wide setting is on.
	 *
	 * @return bool
	 */
	public function is_network_wide(): bool {
		return ! empty( $this->get_network_wide() );
	}

	/**
	 * Get menu position.
	 *
	 * @return string
	 */
	protected function get_menu_position(): string {
		return [ 'on' ] === $this->get( 'menu_position' ) ? self::MODE_TABS : self::MODE_PAGES;
	}

	/**
	 * Print header.
	 *
	 * @return void
	 */
	protected function print_header(): void {
		?>
		<div class="<?php echo esc_attr( static::PREFIX . '-header-bar' ); ?>">
			<div class="<?php echo esc_attr( static::PREFIX . '-header' ); ?>">
				<h2>
					<?php echo esc_html( $this->page_title() ); ?>
				</h2>
			</div>
			<?php

			/**
			 * Fires before settings tab closing tag.
			 */
			do_action( 'kagg_settings_header' );

			?>
		</div>
		<?php
	}

	/**
	 * Get savable for fields.
	 *
	 * @return array
	 */
	protected function get_savable_form_fields(): array {
		$not_savable_form_fields = [ 'button', 'file' ];

		return array_filter(
			$this->form_fields,
			static function ( $field ) use ( $not_savable_form_fields ) {
				return ! in_array( $field['type'] ?? '', $not_savable_form_fields, true );
			}
		);
	}
}
