<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\MailPoet;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use MailPoet\API\JSON\API as MailPoetAPI;
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
	 * @param MailPoetAPI $api MailPoet API instance.
	 *
	 * @return void
	 */
	public function verify( MailPoetAPI $api ): void {
		if (
			Request::filter_input( INPUT_POST, 'action' ) !== 'mailpoet' ||
			Request::filter_input( INPUT_POST, 'endpoint' ) !== 'subscribers' ||
			Request::filter_input( INPUT_POST, 'method' ) !== 'subscribe'
		) {
			// Process frontend subscription ajax request only.
			return;
		}

		$error_message = API::verify( $this->get_entry() );

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

	/**
	 * Get entry.
	 *
	 * @return array
	 */
	private function get_entry(): array {
		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$data = isset( $_POST['data'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) )
			: [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$form_id = (int) $data['form_id'];
		$fields  = [];

		foreach ( $data as $key => $value ) {
			if ( strpos( $key, 'form_field_' ) !== 0 ) {
				continue;
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$hash_name       = (string) base64_decode( str_replace( 'form_field_', '', $key ) );
			$hash_name_arr   = explode( '_', $hash_name );
			$name            = (string) end( $hash_name_arr );
			$fields[ $name ] = $value;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated_at = $wpdb->get_var(
			$wpdb->prepare( "SELECT updated_at FROM {$wpdb->prefix}mailpoet_forms WHERE id = %d", $form_id )
		);

		$entry = [
			'nonce_name'         => self::NONCE,
			'nonce_action'       => self::ACTION,
			'h-captcha-response' => Request::filter_input( INPUT_POST, 'h-captcha-response' ) ?? '',
			'form_date_gmt'      => $updated_at,
			'data'               => [],
		];

		$name = [];

		foreach ( $fields as $type => $value ) {
			if ( 'email' === $type ) {
				$entry['data']['email'] = $value;
			}

			$entry['data'][ $type ] = $value;
		}

		$entry['data']['name'] = implode( ' ', $name ) ?: null;

		return $entry;
	}
}
