<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CF7;

/**
 * Base class.
 */
class Base {

	/**
	 * Whether hCaptcha should be auto-added to any form.
	 *
	 * @var bool
	 */
	protected $mode_auto = false;

	/**
	 * Whether hCaptcha can be embedded into form in the form editor.
	 *
	 * @var bool
	 */
	protected $mode_embed = false;

	/**
	 * Base constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		$this->mode_auto  = hcaptcha()->settings()->is( 'cf7_status', 'form' );
		$this->mode_embed = hcaptcha()->settings()->is( 'cf7_status', 'embed' );
	}
}
