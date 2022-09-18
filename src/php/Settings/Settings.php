<?php
/**
 * Settings class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Settings\Abstracts\SettingsBase;
use HCaptcha\Settings\Abstracts\SettingsInterface;

/**
 * Class Settings
 *
 * The central point to get settings from.
 */
class Settings implements SettingsInterface {

	/**
	 * Menu pages class names.
	 *
	 * @var array
	 */
	protected $menu_pages_classes;

	/**
	 * Menu pages class instances.
	 *
	 * @var array
	 */
	protected $menu_pages = [];

	/**
	 * Settings constructor.
	 *
	 * @param array $menu_pages_classes Menu pages.
	 */
	public function __construct( $menu_pages_classes = [] ) {
		// Allow to specify $menu_pages_classes item as one class, not an array.
		$this->menu_pages_classes = (array) $menu_pages_classes;

		$this->init();
	}

	/**
	 * Init class.
	 */
	protected function init() {
		foreach ( $this->menu_pages_classes as $menu_page_classes ) {
			$tab_classes = (array) $menu_page_classes;

			// Allow to specify menu page as one class, without tabs.
			$page_class  = $tab_classes[0];
			$tab_classes = array_slice( $tab_classes, 1 );

			$tabs = [];
			foreach ( $tab_classes as $tab_class ) {
				/**
				 * Tab.
				 *
				 * @var PluginSettingsBase $tab
				 */
				$tab    = new $tab_class( null );
				$tabs[] = $tab;
			}

			/**
			 * Page.
			 *
			 * @var PluginSettingsBase $page_class
			 */
			$this->menu_pages[] = new $page_class( $tabs );
		}
	}

	/**
	 * Get plugin option.
	 *
	 * @param string $key         Setting name.
	 * @param mixed  $empty_value Empty value for this setting.
	 *
	 * @return string|array The value specified for the option or a default value for the option.
	 */
	public function get( $key, $empty_value = null ) {
		$value = '';

		foreach ( $this->menu_pages as $menu_page ) {
			/**
			 * Menu page.
			 *
			 * @var SettingsBase $menu_page
			 */
			$value = $menu_page->get( $key, $empty_value );
			if ( ! empty( $value ) ) {
				break;
			}

			$tabs = $menu_page->get_tabs();

			foreach ( $tabs as $tab ) {
				/**
				 * Tab.
				 *
				 * @var SettingsBase $tab
				 */
				$value = $tab->get( $key, $empty_value );
				if ( ! empty( $value ) ) {
					break 2;
				}
			}
		}

		if ( '' === $value && ! is_null( $empty_value ) ) {
			$value = $empty_value;
		}

		return $value;
	}

	/**
	 * Check whether option value equals to the compared.
	 *
	 * @param string $key     Setting name.
	 * @param string $compare Compared value.
	 *
	 * @return bool
	 */
	public function is( $key, $compare ) {
		$value = $this->get( $key );

		if ( is_array( $value ) ) {
			return in_array( $compare, $value, true );
		}

		return $value === $compare;
	}

	/**
	 * Check whether option value is 'on' or just non-empty.
	 *
	 * @param string $key Setting name.
	 *
	 * @return bool
	 */
	public function is_on( $key ) {
		return ! empty( $this->get( $key ) );
	}

	/**
	 * Check whether option value is 'on' or just non-empty.
	 *
	 * @param string $key Setting name.
	 *
	 * @return void
	 */
	public function disable_field( $key ) {
		foreach ( $this->menu_pages as $menu_page ) {
			/**
			 * Menu page.
			 *
			 * @var SettingsBase $menu_page
			 */
			if ( $menu_page->disable_field( $key ) ) {
				break;
			}

			$tabs = $menu_page->get_tabs();

			foreach ( $tabs as $tab ) {
				/**
				 * Tab.
				 *
				 * @var SettingsBase $tab
				 */
				if ( $tab->disable_field( $key ) ) {
					break 2;
				}
			}
		}
	}
}
