<?php
/**
 * Contact class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\BeaverBuilder;

use stdClass;

/**
 * Class Contact.
 */
class Contact {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_beaver_builder';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_beaver_builder_nonce';

	/**
	 * Script handle.
	 */
	const HANDLE = 'hcaptcha-beaver-builder';

	/**
	 * Contact constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'fl_builder_render_module_html_content', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_action( 'fl_module_contact_form_before_send', [ $this, 'verify' ], 10, 5 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Filters the Beaver Builder Contact Form submit button html and adds hcaptcha.
	 *
	 * @param string         $content  Button html.
	 * @param string         $type     Button type.
	 * @param stdClass       $settings Button settings.
	 * @param FLButtonModule $module   Button module.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $content, $type, $settings, $module ) {
		$hcaptcha =
			'<div class="fl-input-group fl-hcaptcha">' .
			hcap_form( self::ACTION, self::NONCE ) .
			'</div>';

		return $hcaptcha . $content;
	}

	/**
	 * Verify request.
	 *
	 * @param string   $mailto   Email address.
	 * @param string   $subject  Email subject.
	 * @param string   $template Email template.
	 * @param string[] $headers  Email headers.
	 * @param stdClass $settings Settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $mailto, $subject, $template, $headers, $settings ) {

		$result = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( ! $result ) {
			return;
		}

		$response = [
			'error'   => true,
			'message' => $result,
		];

		wp_send_json( $response );
	}

	/**
	 * Enqueue Beaver Builder script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-beaver-builder$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
