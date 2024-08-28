<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\MailPoet;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use MailPoet\API\JSON\API;
use MailPoet\API\JSON\Response;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_mailpoet';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_mailpoet_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-mailpoet';

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
		add_filter( 'the_content', [ $this, 'the_content_filter' ], 20 );
		add_action( 'mailpoet_api_setup', [ $this, 'verify' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * The content filter.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	public function the_content_filter( $content ): string {
		$content = (string) $content;

		if ( ! preg_match_all(
			'~<form[\s\S]+?"data\[form_id]" value="(\d+?)"[\s\S]+?(<input type="submit")[\s\S]+?</form>~',
			$content,
			$matches
		) ) {
			return $content;
		}

		foreach ( $matches[0] as $key => $form ) {
			if ( false !== strpos( $form, 'h-captcha' ) ) {
				continue;
			}

			$form_id = (int) $matches[1][ $key ];

			$args     = [
				'action' => self::ACTION,
				'name'   => self::NONCE,
				'id'     => [
					'source'  => HCaptcha::get_class_source( __CLASS__ ),
					'form_id' => $form_id,
				],
			];
			$hcaptcha = HCaptcha::form( $args );

			$submit  = $matches[2][ $key ];
			$replace = str_replace( $submit, $hcaptcha . $submit, $form );
			$content = str_replace( $form, $replace, $content );
		}

		return $content;
	}

	/**
	 * Verify MailPoet captcha.
	 *
	 * @param API $api MailPoet API instance.
	 *
	 * @return void
	 */
	public function verify( API $api ): void {
		if (
			Request::filter_input( INPUT_POST, 'action' ) !== 'mailpoet' ||
			Request::filter_input( INPUT_POST, 'endpoint' ) !== 'subscribers' ||
			Request::filter_input( INPUT_POST, 'method' ) !== 'subscribe'
		) {
			// Process frontend subscription ajax request only.
			return;
		}

		$error_message = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return;
		}

		$code           = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';
		$error_response = $api->createErrorResponse( $code, $error_message, Response::STATUS_UNAUTHORIZED );

		$error_response->send();
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
			HCAPTCHA_URL . "/assets/js/hcaptcha-mailpoet$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
