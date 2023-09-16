<?php
/**
 * SettingsBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings\Abstracts;

/**
 * Class SettingsBase
 *
 * This is an abstract class to create the settings page in any plugin.
 * It uses WordPress Settings API and general output of fields of any type.
 * Similar approach is used in many plugins, including WooCommerce.
 */
abstract class SettingsBase {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'hcaptcha-settings-base';

	/**
	 * Network-wide option suffix.
	 */
	const NETWORK_WIDE = '_network_wide';

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
	 * Prefix for minified files.
	 *
	 * @var string
	 */
	protected $min_prefix;

	/**
	 * Fields and their print methods.
	 *
	 * @var array
	 */
	protected $fields;

	/**
	 * Get screen id.
	 *
	 * @return string
	 */
	abstract public function screen_id(): string;

	/**
	 * Get option group.
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
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function __construct( $tabs = [] ) {
		$this->tabs = $tabs;

		$this->fields = [
			'text'     => [ $this, 'print_text_field' ],
			'password' => [ $this, 'print_text_field' ],
			'number'   => [ $this, 'print_number_field' ],
			'textarea' => [ $this, 'print_textarea_field' ],
			'checkbox' => [ $this, 'print_checkbox_field' ],
			'radio'    => [ $this, 'print_radio_field' ],
			'select'   => [ $this, 'print_select_field' ],
			'multiple' => [ $this, 'print_multiple_select_field' ],
			'table'    => [ $this, 'print_table_field' ],
			'button'   => [ $this, 'print_button_field' ],
		];

		if ( ! $this->is_tab() ) {
			add_action( 'current_screen', [ $this, 'setup_tabs_section' ], 9 );
			add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		}

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function init() {
		$this->min_prefix = defined( 'SCRIPT_DEBUG' ) && constant( 'SCRIPT_DEBUG' ) ? '' : '.min';

		$this->form_fields();
		$this->init_settings();

		if ( $this->is_tab_active( $this ) ) {
			$this->init_hooks();
		}
	}

	/**
	 * Init class hooks.
	 */
	protected function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );

		add_filter(
			'plugin_action_links_' . $this->plugin_basename(),
			[ $this, 'add_settings_link' ]
		);

		add_action( 'current_screen', [ $this, 'setup_fields' ] );
		add_action( 'current_screen', [ $this, 'setup_sections' ], 11 );

		add_filter( 'pre_update_option_' . $this->option_name(), [ $this, 'pre_update_option_filter' ], 10, 2 );
		add_filter( 'pre_update_site_option_option_' . $this->option_name(), [ $this, 'pre_update_option_filter' ], 10, 2 );

		add_action( 'admin_enqueue_scripts', [ $this, 'base_admin_enqueue_scripts' ] );
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [];
	}

	/**
	 * Get parent slug.
	 *
	 * @return string
	 */
	protected function parent_slug(): string {
		// By default, add menu pages to Options menu.
		return 'options-general.php';
	}

	/**
	 * Is this the main menu page.
	 *
	 * @return bool
	 */
	protected function is_main_menu_page(): bool {
		// Main menu page should have empty string as parent slug.
		return ! $this->parent_slug();
	}

	/**
	 * Get tab name.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	protected function tab_name(): string {
		return $this->get_class_name();
	}

	/**
	 * Get class name without namespace.
	 *
	 * @return string
	 */
	protected function get_class_name(): string {
		$path = explode( '\\', get_class( $this ) );

		return array_pop( $path );
	}

	/**
	 * Is this a tab.
	 *
	 * @return bool
	 */
	protected function is_tab(): bool {
		// Tab has null in tabs property.
		return null === $this->tabs;
	}

	/**
	 * Add link to plugin setting page on plugins page.
	 *
	 * @param array|mixed $actions An array of plugin action links.
	 *                             By default, this can include 'activate', 'deactivate', and 'delete'.
	 *                             With Multisite active this can also include 'network_active' and 'network_only'
	 *                             items.
	 *
	 * @return array|string[] Plugin links
	 */
	public function add_settings_link( $actions ): array {
		$new_actions = [
			'settings' =>
				'<a href="' . admin_url( $this->parent_slug() . '?page=' . $this->option_page() ) .
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
	 */
	protected function init_settings() {
		$network_wide = get_site_option( $this->option_name() . self::NETWORK_WIDE, [] );

		if ( empty( $network_wide ) ) {
			$this->settings = get_option( $this->option_name(), null );
		} else {
			$this->settings = get_site_option( $this->option_name(), null );
		}

		$settings_exist                       = is_array( $this->settings );
		$this->settings                       = (array) $this->settings;
		$form_fields                          = $this->form_fields();
		$network_wide_setting                 = array_key_exists( self::NETWORK_WIDE, $this->settings ) ?
			$this->settings[ self::NETWORK_WIDE ] :
			$network_wide;
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
	protected function set_defaults( array &$field, string $id ) {
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
	 * Add settings page to the menu.
	 *
	 * @return void
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function add_settings_page() {
		if ( $this->is_main_menu_page() ) {
			add_menu_page(
				$this->page_title(),
				$this->menu_title(),
				'manage_options',
				$this->option_page(),
				[ $this, 'settings_base_page' ]
			);

			return;
		}

		add_submenu_page(
			$this->parent_slug(),
			$this->get_active_tab()->page_title(),
			$this->menu_title(),
			'manage_options',
			$this->option_page(),
			[ $this, 'settings_base_page' ]
		);
	}

	/**
	 * Invoke relevant settings_page() basing on tabs.
	 */
	public function settings_base_page() {
		echo '<div class="wrap">';

		$this->get_active_tab()->settings_page();

		echo '</div>';
	}

	/**
	 * Enqueue scripts in admin.
	 */
	public function admin_enqueue_scripts() {
	}

	/**
	 * Enqueue relevant admin_enqueue_scripts() basing on tabs.
	 * Enqueue admin style.
	 *
	 * @return void
	 */
	public function base_admin_enqueue_scripts() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$this->get_active_tab()->admin_enqueue_scripts();

		wp_enqueue_style(
			self::HANDLE,
			$this->plugin_url() . "/assets/css/settings-base$this->min_prefix.css",
			[],
			$this->plugin_version()
		);
	}

	/**
	 * Setup settings sections.
	 *
	 * @return void
	 */
	public function setup_sections() {
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
	public function setup_tabs_section() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$tab = $this->get_active_tab();

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
	public function tabs_callback() {
		?>
		<div class="hcaptcha-settings-tabs">
			<?php
			$this->tab_link( $this );

			foreach ( $this->tabs as $tab ) {
				$this->tab_link( $tab );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Show tab link.
	 *
	 * @param SettingsBase $tab Tabs of the current settings page.
	 */
	private function tab_link( SettingsBase $tab ) {
		$url    = menu_page_url( $this->option_page(), false );
		$url    = add_query_arg( 'tab', strtolower( $tab->get_class_name() ), $url );
		$active = $this->is_tab_active( $tab ) ? ' active' : '';

		?>
		<a class="hcaptcha-settings-tab<?php echo esc_attr( $active ); ?>" href="<?php echo esc_url( $url ); ?>">
			<?php echo esc_html( $tab->page_title() ); ?>
		</a>
		<?php
	}

	/**
	 * Check if tab is active.
	 *
	 * @param SettingsBase $tab Tab of the current settings page.
	 *
	 * @return bool
	 */
	protected function is_tab_active( SettingsBase $tab ): bool {
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

		return strtolower( $tab->get_class_name() ) === $current_tab_name;
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
	protected function get_active_tab(): SettingsBase {
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
	public function setup_fields() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		register_setting( $this->option_group(), $this->option_name() );

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
	 * Print text/password field.
	 *
	 * @param array $arguments Field arguments.
	 */
	protected function print_text_field( array $arguments ) {
		$value        = $this->get( $arguments['field_id'] );
		$autocomplete = '';
		$lp_ignore    = 'false';

		if ( 'password' === $arguments['type'] ) {
			$autocomplete = 'new-password';
			$lp_ignore    = 'true';
		}

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
	 */
	protected function print_number_field( array $arguments ) {
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
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_textarea_field( array $arguments ) {
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
	 * Print checkbox field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @noinspection HtmlUnknownAttribute
	 * @noinspection HtmlWrongAttributeValue
	 */
	protected function print_checkbox_field( array $arguments ) {
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
				' %6$s' .
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
	 * @noinspection HtmlUnknownAttribute
	 * @noinspection HtmlWrongAttributeValue
	 */
	protected function print_radio_field( array $arguments ) {
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
				' %6$s' .
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
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_select_field( array $arguments ) {
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
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_multiple_select_field( array $arguments ) {
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
	 * Print table field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_table_field( array $arguments ) {
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

			echo '<div class="hcaptcha-table-cell">';
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
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function print_button_field( array $arguments ) {
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
	 */
	public function field_callback( array $arguments ) {
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
	 */
	public function update_option( string $key, $value ) {
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
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			$this->text_domain(),
			false,
			dirname( $this->plugin_basename() ) . '/languages/'
		);
	}

	/**
	 * Is current admin screen the plugin options screen.
	 *
	 * @return bool
	 */
	protected function is_options_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return false;
		}

		$screen_id = $this->screen_id();

		if ( $this->is_main_menu_page() ) {
			$screen_id = str_replace( 'settings_page', 'toplevel_page', $screen_id );
		}

		return 'options' === $current_screen->id || $screen_id === $current_screen->id;
	}

	/**
	 * Print help text if it exists.
	 *
	 * @param string $helper Helper.
	 *
	 * @return void
	 */
	protected function print_helper( string $helper ) {
		if ( ! $helper ) {
			return;
		}

		printf(
			'<span class="helper"><span class="helper-content">%s</span></span>',
			wp_kses_post( $helper )
		);
	}

	/**
	 * Print supplemental id it exists.
	 *
	 * @param string $supplemental Supplemental.
	 *
	 * @return void
	 */
	protected function print_supplemental( string $supplemental ) {
		if ( ! $supplemental ) {
			return;
		}

		printf(
			'<p class="description">%s</p>',
			wp_kses_post( $supplemental )
		);
	}
}
