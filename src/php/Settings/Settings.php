<?php
/**
 * Settings class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection ContractViolationInspection */

namespace HCaptcha\Settings;

use KAGG\Settings\Abstracts\SettingsBase;
use KAGG\Settings\Abstracts\SettingsInterface;

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
	 * Screen ids of pages and tabs.
	 *
	 * @var array
	 */
	private $screen_ids = [];

	/**
	 * Settings constructor.
	 *
	 * @param array $menu_pages_classes Menu pages.
	 */
	public function __construct( array $menu_pages_classes = [] ) {
		// Allow to specify $menu_pages_classes item as one class, not an array.
		$this->menu_pages_classes = $menu_pages_classes;

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
				$tab                = new $tab_class( null );
				$tabs[]             = $tab;
				$this->screen_ids[] = $tab->screen_id();
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
	 * Get tabs.
	 *
	 * @return array
	 */
	public function get_tabs(): array {
		return $this->tabs;
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
	 * Check whether option value equals to the compared.
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
	 * Check whether option value is 'on' or just non-empty.
	 *
	 * @param string $key Setting name.
	 *
	 * @return bool
	 */
	public function is_on( string $key ): bool {
		return ! empty( $this->get( $key ) );
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
	 * Get mode.
	 *
	 * @return string
	 */
	public function get_mode(): string {

		/**
		 * Filters the current operating mode to get relevant key pair.
		 *
		 * @param string $mode Current operating mode.
		 */
		return (string) apply_filters( 'hcap_mode', $this->get( 'mode' ) );
	}

	/**
	 * Get site key.
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
	 * Get secret key.
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
	 * Set field.
	 *
	 * @param string $key       Setting name.
	 * @param string $field_key Field key.
	 * @param mixed  $value     Value.
	 *
	 * @return void
	 */
	public function set_field( string $key, string $field_key, $value ) {
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

	/**
	 * Get screen ids of all settings pages and tabs.
	 *
	 * @return array
	 * @noinspection PhpUnused
	 */
	public function screen_ids(): array {
		return $this->screen_ids;
	}
}
