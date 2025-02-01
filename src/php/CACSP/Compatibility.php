<?php
/**
 * CACSP compatibility class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CACSP;

use HCaptcha\Helpers\Request;

/**
 * Cookies and Content Security Policy plugin compatibility.
 */
class Compatibility {

	/**
	 * Class constructor.
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
		$cacsp_options = [
			'cacsp_option_always_scripts',
			'cacsp_option_always_frames',
			'cacsp_option_disable_content_not_allowed_message',
		];

		$cacsp_option_use = false;

		if ( is_multisite() ) {
			$cacsp_site_id    = get_main_site_id();
			$cacsp_option_use = get_blog_option( $cacsp_site_id, 'cacsp_option_use' );
		}

		$cacsp_option_use = $cacsp_option_use ?: 'default';
		$use_blog_option  = 'default' !== $cacsp_option_use && is_multisite();

		foreach ( $cacsp_options as $option ) {
			if ( $use_blog_option ) {
				add_filter( "blog_option_{$option}", [ $this, 'cacsp_option' ] );
			} else {
				add_filter( "option_{$option}", [ $this, 'cacsp_option' ] );
			}
		}
	}

	/**
	 * Filter cacsp option.
	 *
	 * @param mixed $value Option value.
	 *
	 * @return mixed
	 */
	public function cacsp_option( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( is_admin() ) {
			$page = Request::filter_input( INPUT_GET, 'page' );

			if ( 'cacsp_settings' === $page ) {
				return $value;
			}
		}

		if ( false !== strpos( current_filter(), 'cacsp_option_disable_content_not_allowed_message' ) ) {
			return '1';
		}

		$value     = str_replace( [ "\r", "\r\n" ], "\n", $value );
		$value_arr = explode( "\n", $value );
		$value_arr = array_unique( array_merge( $value_arr, [ 'https://hcaptcha.com/', 'https://*.hcaptcha.com/' ] ) );

		return implode( "\n", $value_arr );
	}
}
