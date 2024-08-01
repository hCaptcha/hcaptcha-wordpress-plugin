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
use MailPoet\API\JSON\API;
use MailPoet\API\JSON\Response;
use WP_Block;

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
		add_filter( 'render_block', [ $this, 'add_captcha' ], 10, 3 );
		add_action( 'mailpoet_api_setup', [ $this, 'verify' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add hcaptcha to MailPoet form.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $block_content, array $block, WP_Block $instance ): string {
		if ( 'mailpoet/subscription-form-block' !== $block['blockName'] ) {
			return (string) $block_content;
		}

		$form_id = $block['attrs']['formId'] ?? 0;
		$search  = '<input type="submit" class="mailpoet_submit"';
		$args    = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];

		return str_replace(
			$search,
			HCaptcha::form( $args ) . $search,
			(string) $block_content
		);
	}

	/**
	 * Verify MailPoet captcha.
	 *
	 * @param API $api MailPoet API instance.
	 */
	public function verify( API $api ): void {
		if ( ! wp_doing_ajax() ) {
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
