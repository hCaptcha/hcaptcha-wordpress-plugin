<?php
/**
 * Elementor Plugin stub file
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Elementor;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * Holds the plugin instance.
	 *
	 * @var Plugin
	 */
	public static $instance;

	/**
	 * Controls manager.
	 *
	 * Holds the plugin controls manager handler is responsible for registering
	 * and initializing controls.
	 *
	 * @var Controls_Manager
	 */
	public $controls_manager;

	/**
	 * Instance.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @return Plugin An instance of the class.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
