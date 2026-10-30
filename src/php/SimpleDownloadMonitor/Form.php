<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SimpleDownloadMonitor;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_simple_download_monitor';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_simple_download_monitor_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-simple-download-monitor';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_filter( 'sdm_download_shortcode_output', [ $this, 'add_captcha' ], 10, 2 );
		add_action( 'sdm_download_via_direct_post', [ $this, 'verify' ], 0 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}


	/**
	 * Add hcaptcha to a Simple Download Monitor form.
	 *
	 * @param string|mixed $output The shortcode output.
	 * @param array        $atts   The attributes.
	 *
	 * @return string
	 */
	public function add_captcha( $output, array $atts ): string {
		$search = '<div class="sdm_download_link">';
		$args   = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_id( absint( $atts['id'] ) ),
		];

		return str_replace(
			$search,
			HCaptcha::form( $args ) . $search,
			(string) $output
		);
	}

	/**
	 * Verify Simple Download Monitor captcha.
	 *
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify(): void {
		// The native action fires after Simple Download Monitor selects a download request,
		// and before it validates the item or dispatches the file.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$download_id = isset( $_REQUEST['download_id'] ) ? absint( $_REQUEST['download_id'] ) : 0;
		$expected_id = $this->get_id( $download_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_message = API::verify_post_data( self::NONCE, self::ACTION, $_GET, $expected_id );

		if ( null === $error_message ) {
			return;
		}

		wp_die(
			esc_html( $error_message ),
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 403,
			]
		);
	}

	/**
	 * Get the widget identity for a download.
	 *
	 * @param int $download_id Download ID.
	 *
	 * @return array
	 */
	private function get_id( int $download_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $download_id,
		];
	}

	/**
	 * Enqueue Simple Download Monitor script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-simple-download-monitor$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Load the unbundled source as a module so its imports work with SCRIPT_DEBUG.
	 *
	 * @param string|mixed $tag    Script tag.
	 * @param string       $handle Script handle.
	 * @param string       $src    Script source.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_type_module( $tag, string $handle, string $src ): string {
		$tag = (string) $tag;

		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
