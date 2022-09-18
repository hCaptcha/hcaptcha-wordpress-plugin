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
	 * Menu pages and tabs in one flat array.
	 *
	 * @var array
	 */
	protected $tabs = [];

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
			$menu_page = new $page_class( $tabs );

			$this->tabs[] = [ $menu_page ];
			$this->tabs[] = $tabs;
		}

		$this->tabs = array_merge( [], ...$this->tabs );
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
	 * Set field.
	 *
	 * @param string $key       Setting name.
	 * @param string $field_key Field key.
	 * @param mixed  $value     Value.
	 *
	 * @return void
	 */
	public function set_field( $key, $field_key, $value ) {
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
