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

namespace HCaptcha\Otter;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request;
use WP_Block;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Form.
 */
class Form {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-otter';

	/**
	 * Form ID prefix.
	 */
	private const FORM_ID_PREFIX = 'wp-block-themeisle-blocks-form-';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_otter';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_otter_nonce';

	/**
	 * Signed binding between the rendered form and its Otter options.
	 */
	private const FORM_NONCE = 'hcaptcha_otter_form_nonce';

	/**
	 * Error code.
	 *
	 * @var string|null
	 */
	private ?string $error_code = null;

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	private ?string $error_message = null;

	/**
	 * Otter Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_filter( 'render_block', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_filter( 'otter_form_anti_spam_validation', array( $this, 'verify' ) );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );

		if ( ! Request::is_frontend() ) {
			return;
		}

		add_filter( 'option_themeisle_google_captcha_api_site_key', [ $this, 'replace_site_key' ], 10, 2 );
		add_filter( 'default_option_themeisle_google_captcha_api_site_key', [ $this, 'replace_site_key' ], 99, 3 );
	}

	/**
	 * Block enqueuing a Google reCaptcha script by replacing Site Key.
	 *
	 * @return string
	 */
	public function replace_site_key(): string {
		return '';
	}

	/**
	 * Add hcaptcha to an Otter form.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $block_content, array $block, WP_Block $instance ): string {
		$block_content = (string) $block_content;

		if ( 'themeisle-blocks/form' !== $block['blockName'] ) {
			return $block_content;
		}

		$form_id     = 0;
		$opening_tag = '';

		if ( preg_match( '/<div\b[^>]*\sid="' . self::FORM_ID_PREFIX . '([^"]+)"[^>]*>/', $block_content, $m ) ) {
			$form_id     = sanitize_text_field( wp_specialchars_decode( $m[1], ENT_QUOTES ) );
			$opening_tag = $m[0];
		}

		$form_option = $this->get_form_option( $block, $opening_tag );
		$form_nonce  = '';

		if ( 0 !== $form_id && '' !== $form_id && '' !== $form_option ) {
			$form_nonce = wp_create_nonce( $this->get_form_nonce_action( (string) $form_id, $form_option ) );
		}

		$args    = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];
		$button  = '<div class="wp-block-button">';
		$search  = [
			'/class="(.*?)has-captcha(.*?)"/',
			"/$button/",
		];
		$replace = [
			'class="$1$2"',
			HCaptcha::form( $args ) . "\n" . '<input type="hidden" name="' . esc_attr( self::FORM_NONCE ) . '" value="' . esc_attr( $form_nonce ) . '">' . "\n" . $button,
		];

		return preg_replace( $search, $replace, $block_content );
	}

	/**
	 * Get the option name used by the rendered Otter form.
	 *
	 * @param array  $block       Block data.
	 * @param string $opening_tag Rendered outer form tag.
	 *
	 * @return string
	 */
	private function get_form_option( array $block, string $opening_tag ): string {
		if ( preg_match( '/\sdata-option-name="([^"]+)"/', $opening_tag, $option_matches ) ) {
			return sanitize_text_field( wp_specialchars_decode( $option_matches[1], ENT_QUOTES ) );
		}

		$option = $block['attrs']['optionName'] ?? '';

		return is_string( $option ) ? sanitize_text_field( $option ) : '';
	}

	/**
	 * Get the nonce action for a form and its native Otter options.
	 *
	 * @param string $form_id     Form id without the Otter prefix.
	 * @param string $form_option Native Otter option name.
	 *
	 * @return string
	 */
	private function get_form_nonce_action( string $form_id, string $form_option ): string {
		return self::ACTION . '|' . wp_json_encode( [ $form_id, $form_option ] );
	}

	/**
	 * Verify the hCaptcha.
	 *
	 * @param Form_Data_Request|null|mixed $form_data Data from the request.
	 *
	 * @return Form_Data_Request|null
	 */
	public function verify( $form_data ): ?Form_Data_Request {
		if ( ! isset( $form_data ) ) {
			return $form_data;
		}

		if ( $form_data->has_error() ) {
			return $form_data;
		}

		$post_data = $form_data->dump_data()['form_data'];

		$this->error_message = $this->has_valid_form_nonce( $post_data )
			? API::verify( $this->get_entry( $post_data ) )
			: hcap_get_error_messages()['bad-signature'];

		if ( null !== $this->error_message ) {
			$this->error_code = array_search( $this->error_message, hcap_get_error_messages(), true ) ?: 'fail';

			// Error processing in Otter is not very helpful.
			$form_data->set_error( $this->error_code, $this->error_message );

			add_filter( 'rest_request_after_callbacks', [ $this, 'filter_response' ], 10, 3 );
		}

		return $form_data;
	}

	/**
	 * Check that the submitted form id and native options were rendered together.
	 *
	 * @param array $post_data Submitted form data.
	 *
	 * @return bool
	 */
	private function has_valid_form_nonce( array $post_data ): bool {
		$payload = $post_data['payload'] ?? [];

		if ( ! is_array( $payload ) ) {
			return false;
		}

		$form_id     = $payload['formId'] ?? null;
		$form_option = $payload['formOption'] ?? null;
		$form_nonce  = $post_data[ self::FORM_NONCE ] ?? null;

		if ( ! is_string( $form_id ) || '' === $form_id ||
			! is_string( $form_option ) || '' === $form_option ||
			! is_string( $form_nonce ) || '' === $form_nonce ) {
			return false;
		}

		$canonical_id = $this->get_expected_id( $form_id )['form_id'];

		return (bool) wp_verify_nonce( $form_nonce, $this->get_form_nonce_action( $canonical_id, $form_option ) );
	}

	/**
	 * Get entry.
	 *
	 * @param array $post_data Post data.
	 *
	 * @return array
	 */
	private function get_entry( array $post_data ): array {
		$payload     = $post_data['payload'] ?? [];
		$form_inputs = $payload['formInputsData'] ?? [];
		$post_id     = $payload['postId'] ?? 0;
		$form        = get_post( $post_id );
		$emails      = [];

		$entry = [
			'nonce_name'         => self::NONCE,
			'nonce_action'       => self::ACTION,
			'h-captcha-response' => $post_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $form->post_modified_gmt ?? null,
			'post_data'          => $post_data,
			'data'               => $this->get_data( $form_inputs, $emails ),
			'expected_id'        => $this->get_expected_id( (string) ( $payload['formId'] ?? '' ) ),
		];

		return EntryData::with_emails( $entry, $emails );
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param string $form_id Form id.
	 *
	 * @return array
	 */
	private function get_expected_id( string $form_id ): array {
		if ( 0 === strpos( $form_id, self::FORM_ID_PREFIX ) ) {
			$form_id = substr( $form_id, strlen( self::FORM_ID_PREFIX ) );
		}

		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $form_id,
		];
	}

	/**
	 * Get data.
	 *
	 * @param array $form_inputs Form inputs data.
	 * @param array $emails      Native email values.
	 *
	 * @return array
	 */
	private function get_data( array $form_inputs, array &$emails ): array {
		$data = [];
		$name = [];

		foreach ( $form_inputs as $input ) {
			$input = wp_parse_args(
				$input,
				[
					'label' => '',
					'value' => '',
					'type'  => '',
					'id'    => '',
				]
			);
			$label = $input['label'];
			$value = $input['value'];
			$type  = $input['type'];
			$id    = $input['id'];

			if ( '' === $value ) {
				continue;
			}

			$value = EntryData::sanitize_value( $value );

			if ( null === $value || [] === $value ) {
				continue;
			}

			if ( 'email' === $type && is_string( $value ) ) {
				$emails[] = $value;
			}

			$key = $label ?: $id;

			if ( '' === $key || EntryData::has_sensitive_field( (string) $type, (string) $id, (string) $label ) ) {
				continue;
			}

			$mapped_name = $input['metadata']['mappedName'] ?? '';
			$label_name  = strtolower( $label );

			if ( ( 'name' === $mapped_name || 'name' === $label_name ) && is_scalar( $value ) ) {
				$name[] = $value;
			}

			EntryData::add_field( $data, (string) $key, $value, (string) $id );
		}

		EntryData::add_name( $data, $name );

		return $data;
	}

	/**
	 * Filters the response immediately after executing any REST API
	 * callbacks.
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
	 *
	 * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function filter_response( $response, array $handler, WP_REST_Request $request ) {
		if ( '/otter/v1/form/frontend' !== $request->get_route() ) {
			return $response;
		}

		$data                 = $response->get_data();
		$data['code']         = $this->error_code;
		$data['displayError'] = $this->error_message;

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Enqueue Otter script.
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
			HCAPTCHA_URL . "/assets/js/hcaptcha-otter$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add the type="module" attribute to the script tag.
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
