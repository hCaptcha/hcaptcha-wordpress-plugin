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
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use MailPoet\API\JSON\API as MailPoetAPI;
use MailPoet\API\JSON\Response;
use MailPoet\DI\ContainerWrapper;
use MailPoet\Form\Util\FieldNameObfuscator;
use MailPoet\Util\Helpers as MailPoetHelpers;

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
		add_action( 'admin_post_mailpoet_subscription_form', [ $this, 'verify_admin_post' ], 0 );
		add_action( 'admin_post_nopriv_mailpoet_subscription_form', [ $this, 'verify_admin_post' ], 0 );
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
		// Match the action selected by WordPress admin-ajax.php, including a query-string action.
		// Keep the raw selector: admin-ajax.php dispatches from this exact value.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$action = $_REQUEST['action'] ?? null;
		// MailPoet resolves this normalized name to its Subscribers endpoint class.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$endpoint = $_POST['endpoint'] ?? null;

		// MailPoet prefers mailpoet_method whenever it is set, even if its value is invalid.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$method_name = isset( $_POST['mailpoet_method'] ) ? 'mailpoet_method' : 'method';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$method = $_POST[ $method_name ] ?? null;

		if (
			'mailpoet' !== $action ||
			! is_string( $endpoint ) ||
			! is_string( $method ) ||
			'subscribe' !== trim( $method ) ||
			'Subscribers' !== ucfirst( MailPoetHelpers::underscoreToCamelCase( trim( $endpoint ) ) )
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
	 * Verify MailPoet's native admin-post subscription before its submission handler runs.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 * @noinspection PhpUnreachableStatementInspection
	 */
	public function verify_admin_post(): void {
		// MailPoet accepts GET form data as a POST API action; retain its bare-GET redirect only.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = $_REQUEST['data'] ?? null;

		if ( ! Request::is_post() ) {
			if ( null !== $data ) {
				wp_die(
					esc_html__( 'Invalid request method.', 'hcaptcha-for-forms-and-more' ),
					esc_html__( 'hCaptcha error', 'hcaptcha-for-forms-and-more' ),
					[ 'response' => 405 ]
				);
			}

			return;
		}

		if ( ! $this->is_native_admin_post_subscription( $data ) ) {
			return;
		}

		// WordPress gives POST precedence in $_REQUEST. Reject GET-only data on a POST.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
			wp_die(
				esc_html__( 'Invalid request.', 'hcaptcha-for-forms-and-more' ),
				esc_html__( 'hCaptcha error', 'hcaptcha-for-forms-and-more' ),
				[ 'response' => 403 ]
			);

			return; // For testing purposes.
		}

		$error_message = API::verify( $this->get_entry() );

		if ( null === $error_message ) {
			return;
		}

		wp_die(
			esc_html( $error_message ),
			esc_html__( 'hCaptcha error', 'hcaptcha-for-forms-and-more' ),
			[ 'response' => 403 ]
		);
	}

	/**
	 * Check the request shape that MailPoet's admin-post handler sends to subscribe().
	 *
	 * @param mixed $data Submitted form data.
	 * @return bool
	 */
	private function is_native_admin_post_subscription( $data ): bool {
		if ( ! is_array( $data ) || ! $data || ( isset( $data['form_id'] ) && ! is_scalar( $data['form_id'] ) ) ) {
			return false;
		}

		// MailPoet prefers the alias whenever it is present.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$method_name = isset( $_REQUEST['mailpoet_method'] ) ? 'mailpoet_method' : 'method';
		$selectors   = [
			'endpoint'   => 'subscribers',
			$method_name => 'subscribe',
		];

		foreach ( $selectors as $name => $expected ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = $_REQUEST[ $name ] ?? null;

			if ( ! is_string( $value ) || trim( $value ) !== $expected ) {
				return false;
			}
		}

		foreach ( [ 'api_version', 'token' ] as $name ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = $_REQUEST[ $name ] ?? null;

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				return false;
			}
		}

		return true;
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
	 * Get expected hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array
	 */
	private function get_expected_id( int $form_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $form_id,
		];
	}

	/**
	 * Get entry.
	 *
	 * @return array
	 */
	private function get_entry(): array {
		global $wpdb;

		$data = (array) Request::filter_input( INPUT_POST, 'data' );

		$form_id    = (int) ( $data['form_id'] ?? 0 );
		$fields     = [];
		$obfuscator = ContainerWrapper::getInstance()->get( FieldNameObfuscator::class );

		foreach ( $data as $key => $value ) {
			if ( strpos( $key, 'form_field_' ) !== 0 ) {
				continue;
			}

			$name     = $obfuscator->deobfuscate( $key );
			$fields[] = [
				'name'  => $name,
				'value' => $value,
				'id'    => $key,
			];
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
			'expected_id'        => $this->get_expected_id( $form_id ),
		];

		foreach ( $fields as $field ) {
			$type  = $field['name'];
			$value = $field['value'];

			if ( EntryData::is_sensitive_field( (string) $type ) ) {
				continue;
			}

			if ( 'email' === $type ) {
				$entry['data']['email'] = $value;
			}

			EntryData::add_field( $entry['data'], (string) $type, $value, (string) $field['id'] );
		}

		return $entry;
	}
}
