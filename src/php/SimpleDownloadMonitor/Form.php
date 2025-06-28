<?php
/**
 * 'Form' class file.
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
		add_action( 'init', [ $this, 'verify' ], 0 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
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
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $atts['id'],
			],
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$smd_process_download = isset( $_REQUEST['smd_process_download'] ) ?
			sanitize_text_field( wp_unslash( $_REQUEST['smd_process_download'] ) ) :
			'';
		$sdm_process_download = isset( $_REQUEST['sdm_process_download'] ) ?
			sanitize_text_field( wp_unslash( $_REQUEST['sdm_process_download'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '1' !== $smd_process_download && '1' !== $sdm_process_download ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$_POST['h-captcha-response'] = $_GET['h-captcha-response'] ?? '';
		$_POST[ self::NONCE ]        = $_GET[ self::NONCE ] ?? '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		unset( $_POST['h-captcha-response'], $_POST[ self::NONCE ] );

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
	 * Enqueue MailPoet script.
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
}
