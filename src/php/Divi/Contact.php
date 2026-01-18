<?php
/**
 * Contact class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use JsonException;
use WP_Block;

/**
 * Class Contact
 */
class Contact {

	/**
	 * Contact form shortcode tag.
	 */
	private const TAG = 'et_pb_contact_form';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_divi_cf';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_divi_cf_nonce';

	/**
	 * Render counter.
	 *
	 * @var int
	 */
	protected int $render_count = 0;

	/**
	 * Captcha status.
	 *
	 * @var string
	 */
	protected string $captcha = 'off';

	/**
	 * Constructor.
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
		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );

		add_filter( self::TAG . '_shortcode_output', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'render_block', [ $this, 'add_hcaptcha_to_block' ], 10, 3 );
		add_filter( 'pre_do_shortcode_tag', [ $this, 'verify_4' ], 10, 4 );
		add_filter( 'divi_module_library_register_module_attrs', [ $this, 'verify_5' ], 10, 2 );

		add_filter( 'et_pb_module_shortcode_attributes', [ $this, 'shortcode_attributes' ], 10, 5 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add hCaptcha to the Contact form.
	 *
	 * @param string|string[] $output      Module output.
	 * @param string          $module_slug Module slug.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function add_hcaptcha( $output, string $module_slug ) {
		if ( ! is_string( $output ) || et_core_is_fb_enabled() ) {
			// Do not add captcha in the frontend builder.

			return $output;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'contact',
			],
		];

		$search  = '<div class="et_contact_bottom_container">';
		$replace =
			'<div class="hcaptcha-divi-wrapper">' .
			HCaptcha::form( $args ) .
			'</div>' .
			"\n" .
			'<div style="clear: both;"></div>' .
			"\n" .
			$search;

		// Insert hcaptcha.
		$output = str_replace( $search, $replace, $output );

		// Remove captcha.
		$output = preg_replace(
			'~<div class="et_pb_contact_right">[\s\S]*?</div>[\s\S]*?<!-- \.et_pb_contact_right -->~',
			'',
			$output
		);

		++$this->render_count;

		return $output;
	}

	/**
	 * Add hcaptcha to a Divi Contact Form block.
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

		if ( 'divi/contact-form' !== $block['blockName'] ) {
			return $block_content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'contact',
			],
		];

		$search  = '<div class="et_contact_bottom_container">';
		$replace =
			'<div class="hcaptcha-divi-5-wrapper">' .
			HCaptcha::form( $args ) .
			'</div>' .
			"\n" .
			'<div style="clear: both;"></div>' .
			"\n" .
			$search;

		// Insert hcaptcha.
		return str_replace( $search, $replace, $block_content );
	}

	/**
	 * Verify hCaptcha for Divi 4.
	 * We use a shortcode tag filter to make verification.
	 *
	 * @param string|false $value Short-circuit return value. Either false or the value to replace the shortcode with.
	 * @param string       $tag   Shortcode name.
	 * @param array|string $attr  Shortcode attribute array or empty string.
	 * @param array        $m     Regular expression match array.
	 *
	 * @return string|false
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_4( $value, string $tag, $attr, array $m ) {
		if ( self::TAG !== $tag ) {
			return $value;
		}

		$this->verify();

		return $value;
	}

	/**
	 * Verify hCaptcha for Divi 5.
	 *
	 * @param array|mixed $module_attrs Module attributes.
	 * @param array       $filter_args  Filter arguments.
	 *
	 * @return array
	 */
	public function verify_5( $module_attrs, array $filter_args ): array {
		$module_attrs = (array) $module_attrs;

		$request_method = strtoupper( Request::filter_input( INPUT_SERVER, 'REQUEST_METHOD' ) );

		// If the request method is not `POST`, return.
		if ( 'POST' !== $request_method ) {
			return $module_attrs;
		}

		if ( 'divi/contact-form' !== $filter_args['name'] ) {
			return $module_attrs;
		}

		$module_id      = $filter_args['id'];
		$store_instance = $filter_args['storeInstance'];

		$module = BlockParserStore::get( $module_id, $store_instance );

		if ( ! $module ) {
			return $module_attrs;
		}

		$this->verify();

		if ( 'on' === $this->captcha ) {
			$module->attrs['module']['advanced']['spamProtection']['desktop']['value']['enabled'] = 'on';
		}

		return $module_attrs;
	}

	/**
	 * Filters Module Props.
	 *
	 * @param array|mixed $props    Array of processed props.
	 * @param array       $attrs    Array of original shortcode attrs.
	 * @param string      $slug     Module slug.
	 * @param string      $_address Module Address.
	 * @param string      $content  Module content.
	 *
	 * @return array|mixed
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function shortcode_attributes( $props, array $attrs, string $slug, string $_address, string $content ) {
		if ( self::TAG !== $slug ) {
			return $props;
		}

		$props['captcha']          = $this->captcha;
		$props['use_spam_service'] = $this->captcha;
		$this->captcha             = 'off';

		return $props;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.hcaptcha-divi-wrapper {
		float: right;
    	width: 100%;
		margin-bottom: 3%;
		padding-left: 3%;
    	display: flex;
    	justify-content: end;
	}

	.hcaptcha-divi-wrapper ~ .et_contact_bottom_container {
		margin-top: 0;
	}

	.hcaptcha-divi-wrapper .h-captcha {
		margin: 0;
	}

	.et_d4_element .hcaptcha-divi-wrapper {
		margin-top: 3%;
		padding-left: 0;
	}

	.et_d4_element .hcaptcha-divi-wrapper .h-captcha {
		margin: 0;
	}

	.hcaptcha-divi-5-wrapper {
		display: flex;
		flex: 0 0 100%;
		justify-content: end;
	}
	
	.hcaptcha-divi-5-wrapper .h-captcha {
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		wp_deregister_script( 'et-recaptcha-v3' );
		wp_deregister_script( 'es6-promise' );

		wp_dequeue_script( 'et-core-api-spam-recaptcha' );

		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-divi',
			HCAPTCHA_URL . "/assets/js/hcaptcha-divi$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Get entry.
	 *
	 * @param array $fields_data_array An array of submitted data.
	 *
	 * @return array
	 */
	private function get_entry( array $fields_data_array ): array {
		global $post;

		$entry = [
			'nonce_name'    => self::NONCE,
			'nonce_action'  => self::ACTION,
			'form_date_gmt' => $post->post_modified_gmt ?? null,
			'data'          => [],
		];

		foreach ( $fields_data_array as $field ) {
			$field = wp_parse_args(
				$field,
				[
					'field_type'  => '',
					'field_id'    => '',
					'original_id' => '',
					'field_label' => '',
				]
			);

			$type = $field['field_type'];

			if ( ! in_array( $type, [ 'input', 'email', 'text' ], true ) ) {
				continue;
			}

			$id          = $field['field_id'];
			$original_id = $field['original_id'];
			$label       = $field['field_label'];
			$label       = $label ?: $type;
			$value       = Request::filter_input( INPUT_POST, $id );

			$entry['data'][ $label ] = $value;

			if ( 'name' === $original_id ) {
				$entry['name'] = $value;
			}

			if ( 'email' === $type ) {
				$entry['email'] = $value;
			}
		}

		return $entry;
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @return void
	 */
	private function verify(): void {
		$submit_field = 'et_pb_contactform_submit_' . $this->render_count;
		$number_field = 'et_pb_contact_et_number_' . $this->render_count;

		// Check that the form was submitted and the et_pb_contact_et_number field is empty to protect from spam.
		// Divi checks its nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $submit_field ] ) && empty( $_POST[ $number_field ] ) ) {
			// Remove hCaptcha fields from current form fields, because Divi compares current and submitted fields.
			$current_form_field  = 'et_pb_contact_email_fields_' . $this->render_count;
			$current_form_fields = filter_input( INPUT_POST, $current_form_field, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$fields_data_array   = [];

			if ( $current_form_fields ) {
				$fields_data_json = html_entity_decode( str_replace( '\\', '', $current_form_fields ) );

					$fields_data_array = Utils::json_decode_arr( $fields_data_json );

				$fields_data_array            = array_filter(
					$fields_data_array,
					static function ( $item ) {
						return ! preg_match( '/captcha|hcap_hp_|hcap_fst_token/', $item['field_id'] );
					}
				);
				$fields_data_json             = wp_json_encode( $fields_data_array, JSON_UNESCAPED_UNICODE );
				$_POST[ $current_form_field ] = wp_slash( $fields_data_json );
			}

			$error_message = API::verify( $this->get_entry( $fields_data_array ) );

			if ( null !== $error_message ) {
				// Simulate captcha error.
				$this->captcha = 'on';
			}
		}
	}
}
