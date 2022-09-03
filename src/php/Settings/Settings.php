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
	 * Menu pages classes.
	 */
	const MENU_PAGES = [
		[ Integrations::class, General::class ],
	];

	/**
	 * Menu pages class instances.
	 *
	 * @var array
	 */
	protected $menu_pages = [];

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @noinspection UnnecessaryCastingInspection
	 */
	protected function init() {
		// Allow to specify MENU_PAGES item as one class, not an array.
		$menu_pages = (array) self::MENU_PAGES;

		foreach ( $menu_pages as $menu_page ) {
			$tab_classes = (array) $menu_page;

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
	 * Check whether option value is on.
	 *
	 * @param string $key Setting name.
	 *
	 * @return bool
	 */
	public function is_on( $key ) {
		return ! empty( $this->get( $key ) );
	}
}
