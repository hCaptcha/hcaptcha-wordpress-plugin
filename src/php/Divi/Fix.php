<?php
/**
 * Fix class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

/**
 * Class Fix.
 */
class Fix {

	/**
	 * Init.
	 */
	public function init() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		add_action( 'init', [ $this, 'register_autoload' ], - PHP_INT_MAX );
	}

	/**
	 * Register autoload.
	 *
	 * @return void
	 */
	public function register_autoload() {
		if ( ! defined( 'ET_BUILDER_THEME' ) ) {
			return;
		}

		spl_autoload_register( [ $this, 'prevent_loading_of_wp_test_case' ], true, true );
	}

	/**
	 * Prevent loading of WPTestCase class.
	 * Loading of the WPTestCase causes a fatal error if any plugin has Codeception tests in the vendor.
	 *
	 * @param string $classname Class name.
	 *
	 * @return true|null
	 */
	public function prevent_loading_of_wp_test_case( string $classname ) {
		if ( 'Codeception\TestCase\WPTestCase' === $classname ) {
			require 'WPTestCaseStub.php';

			return true;
		}

		return null;
	}
}
