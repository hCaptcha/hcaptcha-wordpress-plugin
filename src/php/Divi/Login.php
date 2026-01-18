<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use HCaptcha\Abstracts\LoginBase;
use WP_Block;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Login form shortcode tag.
	 */
	public const TAG = 'et_pb_login';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( self::TAG . '_shortcode_output', [ $this, 'add_hcaptcha_to_shortcode' ], 10, 2 );
		add_filter( 'render_block', [ $this, 'add_hcaptcha_to_block' ], 10, 3 );
	}

	/**
	 * Add hCaptcha to the login form.
	 *
	 * @param string|mixed $output      Module output.
	 * @param string       $module_slug Module slug.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function add_hcaptcha_to_shortcode( $output, string $module_slug ) {
		if ( ! is_string( $output ) || et_core_is_fb_enabled() ) {
			// Do not add captcha in the frontend builder.

			return $output;
		}

		return $this->add_divi_login_hcaptcha( $output );
	}

	/**
	 * Add hcaptcha to a Divi Login From block.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha_to_block( $block_content, array $block, WP_Block $instance ): string {
		$block_content = (string) $block_content;

		if ( 'divi/login' !== $block['blockName'] ) {
			return $block_content;
		}

		return $this->add_divi_login_hcaptcha( $block_content );
	}

	/**
	 * Get active Divi component.
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
	 * Add hCaptcha to the Divi login form.
	 *
	 * @param string $output Output.
	 *
	 * @return string
	 */
	private function add_divi_login_hcaptcha( string $output ): string {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $output;
		}

		$hcaptcha = '';
		$theme    = $this->get_active_divi_component();

		// Check the login status, because the class is always loading when a Divi component is active.
		if ( hcaptcha()->settings()->is( $theme . '_status', 'login' ) ) {
			ob_start();

			$this->add_captcha();
			$hcaptcha = (string) ob_get_clean();
		}

		ob_start();

		/**
		 * Display hCaptcha signature.
		 */
		do_action( 'hcap_signature' );

		$signatures = (string) ob_get_clean();

		$pattern     = '/(<p>[\s]*?<button)/';
		$replacement = $hcaptcha . $signatures . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}
}
