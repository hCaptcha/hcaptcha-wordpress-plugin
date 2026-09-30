<?php
/**
 * Component trait file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use HCaptcha\Helpers\HCaptcha;

/**
 * Resolve the active Divi component.
 */
trait Component {

	/**
	 * Get an active Divi component.
	 *
	 * @return string
	 */
	protected function get_active_divi_component(): string {
		if ( defined( 'ET_BUILDER_PLUGIN_VERSION' ) ) {
			return 'divi_builder';
		}

		$theme = get_template();

		if ( in_array( $theme, [ 'Divi', 'Extra' ], true ) ) {
			return strtolower( $theme );
		}

		return '';
	}

	/**
	 * Get the source of the active Divi component.
	 *
	 * @param string $class_name Integration class name used as a fallback.
	 *
	 * @return string[]
	 */
	protected function get_active_divi_source( string $class_name ): array {
		$component = $this->get_active_divi_component();
		$source    = $component ? HCaptcha::get_status_source( $component . '_status' ) : [];

		return $source ?: HCaptcha::get_class_source( $class_name );
	}
}
