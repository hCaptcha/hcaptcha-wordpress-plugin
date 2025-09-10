<?php
/**
 * Settings class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Settings;

use KAGG\Settings\Abstracts\SettingsBase;
use KAGG\Settings\Abstracts\SettingsInterface;

/**
 * Class Settings
 *
 * The central point is to get settings from.
 */
class Settings implements SettingsInterface {

	/**
	 * Existing licenses.
	 */
	private const EXISTING_LICENSES = [ 'free', 'pro', 'enterprise' ];

	/**
	 * Menu pages class names.
	 *
	 * @var array
	 */
	protected $menu_groups;

	/**
	 * Menu pages and tabs in one flat array.
	 *
	 * @var array
	 */
	protected $tabs = [];

	/**
	 * Settings constructor.
	 *
	 * @param array $menu_groups Menu items.
	 */
	public function __construct( array $menu_groups = [] ) {
		$this->menu_groups = $menu_groups;

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	protected function init(): void {
		foreach ( $this->menu_groups as $menu_group ) {
			$classes = (array) ( $menu_group['classes'] ?? [] );
			$args    = $menu_group['args'] ?? [];

			$page_class  = $classes[0];
			$tab_classes = array_slice( $classes, 1 );
			$tabs        = [];

			foreach ( $tab_classes as $tab_class ) {
				/**
				 * Tab.
				 *
				 * @var PluginSettingsBase $tab
				 */
				$tab    = new $tab_class( null, $args );
				$tabs[] = $tab;
			}

			/**
			 * Page.
			 *
			 * @var PluginSettingsBase $page_class
			 */
			$menu_page = new $page_class( $tabs, $args );

			$this->tabs[] = [ $menu_page ];
			$this->tabs[] = $tabs;
		}

		$this->tabs = array_merge( [], ...array_filter( $this->tabs ) );
	}

	/**
	 * Get tabs.
	 *
	 * @return array
	 */
	public function get_tabs(): array {
		return $this->tabs;
	}

	/**
	 * Get tab.
	 *
	 * @param string $classname Class name.
	 *
	 * @return PluginSettingsBase|null
	 */
	public function get_tab( string $classname ): ?PluginSettingsBase {
		foreach ( $this->tabs as $tab ) {
			if ( is_a( $tab, $classname ) ) {
				return $tab;
			}
		}

		return null;
	}

	/**
	 * Get an active tab name.
	 *
	 * @return string
	 */
	public function get_active_tab_name(): string {
		$first_tab = $this->tabs[0] ?? null;

		return $first_tab ? $first_tab->get_active_tab()->tab_name() : '';
	}

	/**
	 * Get the plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return 'hCaptcha for WP';
	}

	/**
	 * Check if it is a Pro account.
	 *
	 * @return false
	 */
	public function is_pro(): bool {
		return 'pro' === $this->get_license();
	}

	/**
	 * Check if it is a Pro account or General admin page.
	 *
	 * @return bool
	 */
	public function is_pro_or_general(): bool {
		return $this->is_pro() || ( is_admin() && 'General' === $this->get_active_tab_name() );
	}

	/**
	 * Get config params.
	 *
	 * @return array
	 */
	public function get_config_params(): array {
		return (array) ( json_decode( $this->get( 'config_params' ), true ) ?: [] );
	}

	/**
	 * Get custom background.
	 *
	 * @return string
	 */
	public function get_custom_theme_background(): string {
		$bg = '';

		if (
			$this->is_on( 'custom_themes' ) &&
			$this->is_pro_or_general() &&
			$this->is( 'mode', 'live' )
		) {
			$bg = $this->get_config_params()['theme']['component']['checkbox']['main']['fill'] ?? $bg;
		}

		return $bg;
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
		$value = '';

		foreach ( $this->tabs as $tab ) {
			/**
			 * Page / Tab.
			 *
			 * @var SettingsBase $tab
			 */
			$value = $tab->get( $key, $empty_value );

			if ( ! empty( $value ) ) {
				break;
			}
		}

		if ( '' === $value && ! is_null( $empty_value ) ) {
			$value = $empty_value;
		}

		return $value;
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
		foreach ( $this->tabs as $tab ) {
			/**
			 * Page / Tab.
			 *
			 * @var SettingsBase $tab
			 */
			if ( $tab->set( $key, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the option value equals to the compared one.
	 *
	 * @param string $key     Setting name.
	 * @param string $compare Compared value.
	 *
	 * @return bool
	 */
	public function is( string $key, string $compare ): bool {
		$value = $this->get( $key );

		if ( is_array( $value ) ) {
			return in_array( $compare, $value, true );
		}

		return $value === $compare;
	}

	/**
	 * Check whether the option value is 'on' or just non-empty.
	 *
	 * @param string $key Setting name.
	 *
	 * @return bool
	 */
	public function is_on( string $key ): bool {
		$value = $this->get( $key );

		if ( is_array( $value ) ) {
			return [ 'on' ] === $value;
		}

		return ! empty( $value );
	}

	/**
	 * Get keys.
	 *
	 * @return array
	 */
	private function get_keys(): array {

		// String concat is used for the PHP 5.6 compatibility.
		// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
		switch ( $this->get_mode() ) {
			case General::MODE_LIVE:
				$site_key   = $this->get( 'site_key' );
				$secret_key = $this->get( 'secret_key' );
				break;
			case General::MODE_TEST_PUBLISHER:
				$site_key   = General::MODE_TEST_PUBLISHER_SITE_KEY;
				$secret_key = '0' . 'x' . '0000000000000000000000000000000000000000';
				break;
			case General::MODE_TEST_ENTERPRISE_SAFE_END_USER:
				$site_key   = General::MODE_TEST_ENTERPRISE_SAFE_END_USER_SITE_KEY;
				$secret_key = '0' . 'x' . '0000000000000000000000000000000000000000';
				break;
			case General::MODE_TEST_ENTERPRISE_BOT_DETECTED:
				$site_key   = General::MODE_TEST_ENTERPRISE_BOT_DETECTED_SITE_KEY;
				$secret_key = '0' . 'x' . '0000000000000000000000000000000000000000';
				break;
			default:
				$site_key   = '';
				$secret_key = '';
		}

		// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found

		return [
			'site_key'   => $site_key,
			'secret_key' => $secret_key,
		];
	}

	/**
	 * Get a site key.
	 *
	 * @return string
	 */
	public function get_site_key(): string {

		/**
		 * Filters the current site key.
		 *
		 * @param string $mode Current site key.
		 */
		return (string) apply_filters( 'hcap_site_key', $this->get_keys()['site_key'] );
	}

	/**
	 * Get a secret key.
	 *
	 * @return string
	 */
	public function get_secret_key(): string {

		/**
		 * Filters the current secret key.
		 *
		 * @param string $mode Current secret key.
		 */
		return (string) apply_filters( 'hcap_secret_key', $this->get_keys()['secret_key'] );
	}

	/**
	 * Get a theme.
	 *
	 * @return string
	 */
	public function get_theme(): string {
		$theme = $this->get( 'theme' );

		if ( $this->is_on( 'custom_themes' ) && $this->is_pro_or_general() ) {
			$theme = $this->get_config_params()['theme']['palette']['mode'] ?? $theme;
		}

		/**
		 * Filters the current theme to get a relevant key pair.
		 *
		 * @param string $mode Current theme.
		 */
		return (string) apply_filters( 'hcap_theme', $theme );
	}

	/**
	 * Get language.
	 *
	 * @return string
	 */
	public function get_language(): string {

		/**
		 * Filters hCaptcha language.
		 *
		 * @param string $language Language.
		 */
		return (string) apply_filters( 'hcap_language', $this->get( 'language' ) );
	}

	/**
	 * Get mode.
	 *
	 * @return string
	 */
	public function get_mode(): string {

		/**
		 * Filters the current operating mode to get a relevant key pair.
		 *
		 * @param string $mode Current operating mode.
		 */
		return (string) apply_filters( 'hcap_mode', $this->get( 'mode' ) );
	}

	/**
	 * Get license level.
	 *
	 * @return string
	 */
	public function get_license(): string {
		$license = (string) $this->get( 'license' );

		return in_array( $license, self::EXISTING_LICENSES, true ) ? $license : 'free';
	}

	/**
	 * Get the default hCaptcha theme.
	 *
	 * @return array
	 */
	public function get_default_theme(): array {
		return [
			'palette'   => [
				'mode'    => 'light',
				'grey'    => [
					100  => '#fafafa',
					200  => '#f5f5f5',
					300  => '#e0e0e0',
					400  => '#d7d7d7',
					500  => '#bfbfbf',
					600  => '#919191',
					700  => '#555555',
					800  => '#333333',
					900  => '#222222',
					1000 => '#14191f',
				],
				'primary' => [
					'main' => '#00838f',
				],
				'warn'    => [
					'main' => '#eb5757',
				],
				'text'    => [
					'heading' => '#555555',
					'body'    => '#555555',
				],
			],
			'component' => [
				'checkbox'     => [
					'main'  => [
						'fill'   => '#fafafa',
						'border' => '#e0e0e0',
					],
					'hover' => [
						'fill' => '#f5f5f5',
					],
				],
				'challenge'    => [
					'main'  => [
						'fill'   => '#fafafa',
						'border' => '#e0e0e0',
					],
					'hover' => [
						'fill' => '#fafafa',
					],
				],
				'modal'        => [
					'main'  => [
						'fill'   => '#ffffff',
						'border' => '#e0e0e0',
					],
					'hover' => [
						'fill' => '#f5f5f5',
					],
					'focus' => [
						'border' => '#0074bf',
					],
				],
				'breadcrumb'   => [
					'main'   => [
						'fill' => '#f5f5f5',
					],
					'active' => [
						'fill' => '#00838f',
					],
				],
				'button'       => [
					'main'   => [
						'fill' => '#ffffff',
						'icon' => '#555555',
						'text' => '#555555',
					],
					'hover'  => [
						'fill' => '#f5f5f5',
					],
					'focus'  => [
						'icon' => '#00838f',
						'text' => '#00838f',
					],
					'active' => [
						'fill' => '#f5f5f5',
						'icon' => '#555555',
						'text' => '#555555',
					],
				],
				'list'         => [
					'main' => [
						'fill'   => '#ffffff',
						'border' => '#d7d7d7',
					],
				],
				'listItem'     => [
					'main'     => [
						'fill' => '#ffffff',
						'line' => '#f5f5f5',
						'text' => '#555555',
					],
					'hover'    => [
						'fill' => '#f5f5f5',
					],
					'selected' => [
						'fill' => '#e0e0e0',
					],
				],
				'input'        => [
					'main'  => [
						'fill'   => '#fafafa',
						'border' => '#919191',
					],
					'focus' => [
						'fill'   => '#f5f5f5',
						'border' => '#333333',
					],
				],
				'radio'        => [
					'main'     => [
						'file'   => '#f5f5f5',
						'border' => '#919191',
						'check'  => '#f5f5f5',
					],
					'selected' => [
						'check' => '#00838f',
					],
				],
				'task'         => [
					'main'     => [
						'fill' => '#f5f5f5',
					],
					'selected' => [
						'border' => '#00838f',
					],
					'report'   => [
						'border' => '#eb5757',
					],
				],
				'prompt'       => [
					'main'   => [
						'fill'   => '#00838f',
						'border' => '#00838f',
						'text'   => '#ffffff',
					],
					'report' => [
						'fill'   => '#eb5757',
						'border' => '#eb5757',
						'text'   => '#ffffff',
					],
				],
				'skipButton'   => [
					'main'  => [
						'fill'   => '#919191',
						'border' => '#919191',
						'text'   => '#ffffff',
					],
					'hover' => [
						'fill'   => '#555555',
						'border' => '#919191',
						'text'   => '#ffffff',
					],
				],
				'verifyButton' => [
					'main'  => [
						'fill'   => '#00838f',
						'border' => '#00838f',
						'text'   => '#ffffff',
					],
					'hover' => [
						'fill'   => '#00838f',
						'border' => '#00838f',
						'text'   => '#ffffff',
					],
				],
				'expandButton' => [
					'main' => [
						'fill' => '#00838f',
					],
				],
				'slider'       => [
					'main'  => [
						'bar'    => '#c4c4c4',
						'handle' => '#0f8390',
					],
					'focus' => [
						'handle' => '#0f8390',
					],
				],
			],
		];
	}

	/**
	 * Set field.
	 *
	 * @param string $key       Setting name.
	 * @param string $field_key Field key.
	 * @param mixed  $value     Value.
	 *
	 * @return void
	 */
	public function set_field( string $key, string $field_key, $value ): void {
		foreach ( $this->tabs as $tab ) {
			/**
			 * Page / Tab.
			 *
			 * @var SettingsBase $tab
			 */
			if ( $tab->set_field( $key, $field_key, $value ) ) {
				break;
			}
		}
	}
}
