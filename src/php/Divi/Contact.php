<?php
/**
 * Contact class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Divi;

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\ModuleLibrary\ContactField\ContactFieldModule;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use WP_Block;

/**
 * Class Contact
 */
class Contact {
	use Component;

	/**
	 * Contact form's shortcode tag.
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
	 * Trusted Divi 4 shortcode fields for the current module.
	 *
	 * @var string|null
	 */
	private ?string $shortcode_content = null;

	/**
	 * Trusted Divi 5 parser store context for the current module.
	 *
	 * @var array|null
	 */
	private ?array $block_context = null;

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
			'id'     => $this->get_expected_id(),
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
			'id'     => $this->get_expected_id(),
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

		$this->shortcode_content = (string) ( $m[5] ?? '' );
		$this->block_context     = null;
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

		$this->shortcode_content = null;
		$this->block_context     = [
			'id'             => $module_id,
			'store_instance' => $store_instance,
		];
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$this->render_count = (int) ( $module->orderIndex ?? 0 );
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
	 * Filter submitted field metadata.
	 *
	 * @param array $fields_data_array Submitted field metadata.
	 *
	 * @return array
	 */
	private function filter_fields_data( array $fields_data_array ): array {
		return array_filter(
			$fields_data_array,
			static function ( $item ): bool {
				if ( ! is_array( $item ) ) {
					return false;
				}

				foreach ( [ 'field_id', 'field_type', 'original_id', 'field_label' ] as $key ) {
					if ( isset( $item[ $key ] ) && ! is_string( $item[ $key ] ) ) {
						return false;
					}
				}

				$field_id = $item['field_id'] ?? '';

				return '' !== $field_id && ! preg_match( '/captcha|hcap_hp_|hcap_fst_token/', $field_id );
			}
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
			'expected_id'   => $this->get_expected_id(),
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

			$id          = $field['field_id'];
			$original_id = $field['original_id'];
			$label       = $field['field_label'];
			$label       = $label ?: $type;

			if ( ! EntryData::is_content_field_type( (string) $type ) ||
				EntryData::has_sensitive_field( (string) $id, (string) $original_id, (string) $label ) ) {
				continue;
			}

			$value = Request::filter_input( INPUT_POST, $id );

			EntryData::add_field( $entry['data'], (string) $label, $value, (string) $id );

			if ( 'name' === $original_id ) {
				$entry['name'] = $value;
			}

			if ( 'email' === $type ) {
				$entry['email'] = $value;
			}
		}

		return $this->with_native_emails( $entry );
	}

	/**
	 * Project authoritative email fields after generic submitted field metadata.
	 *
	 * @param array $entry Entry data.
	 *
	 * @return array
	 */
	private function with_native_emails( array $entry ): array {
		if ( null === $this->shortcode_content && null === $this->block_context ) {
			return $entry;
		}

		$emails = null === $this->block_context ? $this->get_shortcode_emails() : $this->get_block_emails();
		$entry  = EntryData::with_emails( $entry, $emails );
		unset( $entry['email'] );

		if ( isset( $entry['data']['email'] ) ) {
			$entry['email'] = $entry['data']['email'];
		}

		return $entry;
	}

	/**
	 * Get native email values from server-rendered Divi 4 email field shortcodes.
	 *
	 * @return string[]
	 */
	private function get_shortcode_emails(): array {
		$emails  = [];
		$hidden  = $this->get_hidden_shortcode_fields();
		$pattern = get_shortcode_regex( [ 'et_pb_contact_field' ] );
		preg_match_all( "/$pattern/", (string) $this->shortcode_content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$attrs = shortcode_parse_atts( $match[3] );

			if ( 'email' !== ( $attrs['field_type'] ?? 'input' ) ) {
				continue;
			}

			$field_id = strtolower( (string) ( $attrs['field_id'] ?? '' ) );
			// Native get_form_map() compares decoded exclusions without strict typing.
			// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			if ( 'on' === ( $attrs['conditional_logic'] ?? 'off' ) && in_array( $field_id, $hidden, true ) ) {
				continue;
			}

			$name = 'et_pb_contact_' . $field_id . '_' . $this->render_count;
			// Native Divi verifies its nonce; API::verify() also verifies the hCaptcha nonce.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = wp_unslash( $_POST[ $name ] ?? '' );

			if ( is_string( $value ) ) {
				$emails[] = sanitize_email( $value );
			}
		}

		return $emails;
	}

	/**
	 * Read native Divi 4 conditional field exclusions for the submitted form.
	 *
	 * @return array
	 */
	private function get_hidden_shortcode_fields(): array {
		$name = 'et_pb_contact_email_hidden_fields_' . $this->render_count;
		// Native Divi permits these exclusions only for stored conditional fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$value = $_POST[ $name ] ?? '';

		return is_string( $value ) ? Utils::json_decode_arr( str_replace( '\\', '', $value ) ) : [];
	}

	/**
	 * Collect emails using the native Divi 5 child fields and unique input names.
	 *
	 * @return string[]
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function get_block_emails(): array {
		$store_instance = $this->block_context['store_instance'];
		$fields         = [];
		$emails         = [];

		foreach ( BlockParserStore::get_children( $this->block_context['id'], $store_instance ) as $field ) {
			$field_id = strtolower( $field->attrs['fieldItem']['advanced']['id']['desktop']['value'] ?? '' );
			$type     = $field->attrs['fieldItem']['advanced']['type']['desktop']['value'] ?? 'input';
			$name     = ContactFieldModule::get_field_unique_id( $field->id, $store_instance );
			// The native handler overwrites earlier definitions with the same lowercase field ID.
			$fields[ $field_id ] = [
				'type'        => $type,
				'value'       => $this->get_block_field_value( $name, $type ),
				'conditional' => $field->attrs['conditionalLogic'] ?? [],
			];
		}

		foreach ( $fields as $field ) {
			if ( 'email' !== $field['type'] || ! $this->is_block_field_visible( $field, $fields ) ) {
				continue;
			}

			// ContactFormHandler::validate_fields() accepts only native-valid email values.
			if ( is_email( $field['value'] ) ) {
				$emails[] = $field['value'];
			}
		}

		return $emails;
	}

	/**
	 * Normalize a Divi 5 value exactly as ContactFormHandler::process().
	 *
	 * @param string $name Native unique input name.
	 * @param string $type Stored field type.
	 *
	 * @return string|string[]
	 */
	private function get_block_field_value( string $name, string $type ) {
		// Native Divi verifies its nonce; API::verify() also verifies the hCaptcha nonce.
		// Its handler sanitizes non-checkbox values without an additional unslash.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$value = $_POST[ $name ] ?? '';

		if ( 'text' === $type ) {
			return sanitize_textarea_field( $value );
		}

		if ( 'checkbox' !== $type ) {
			return sanitize_text_field( $value );
		}

		$value = wp_unslash( $value );
		if ( '' === $value ) {
			return [];
		}

		$parts = array_map( 'urldecode', explode( ',', $value ) );
		$parts = array_map( 'sanitize_text_field', $parts );

		return array_map( 'trim', $parts );
	}

	/**
	 * Mirror native private conditional visibility before its mail-processing constructor.
	 *
	 * @param array $field  Stored field and native-normalized value.
	 * @param array $fields Native lowercase-ID last-wins field map.
	 *
	 * @return bool
	 */
	private function is_block_field_visible( array $field, array $fields ): bool {
		$conditional = $field['conditional'];
		if ( 'on' !== ( $conditional['advanced']['enable']['desktop']['value'] ?? 'off' ) ) {
			return true;
		}

		$rules   = $conditional['innerContent']['desktop']['value'] ?? [];
		$matches = [];
		foreach ( $rules as $rule ) {
			$value     = $this->get_block_rule_value( $rule, $fields );
			$matches[] = (int) $this->matches_block_rule( $value, $rule['condition'], $rule['value'] );
		}

		return 'on' === ( $conditional['advanced']['relation']['desktop']['value'] ?? 'off' )
			? count( $rules ) === array_sum( $matches )
			: 0 !== array_sum( $matches );
	}

	/**
	 * Read a native normalized conditional source, including checkbox joining.
	 *
	 * @param array $rule   Stored native rule.
	 * @param array $fields Native lowercase-ID last-wins field map.
	 *
	 * @return string
	 */
	private function get_block_rule_value( array $rule, array $fields ): string {
		$source = $fields[ strtolower( $rule['field'] ) ] ?? [];
		$value  = $source['value'] ?? '';

		return is_array( $value ) && 'checkbox' === ( $source['type'] ?? '' ) ? implode( ', ', $value ) : $value;
	}

	/**
	 * Match native Divi 5 equality and numeric conditional rules.
	 *
	 * @param string $value      Native-normalized source value.
	 * @param mixed  $condition  Native rule selector.
	 * @param mixed  $rule_value Stored rule value.
	 *
	 * @return bool
	 */
	private function matches_block_rule( string $value, $condition, $rule_value ): bool {
		switch ( $condition ) {
			case 'is':
				return $rule_value === $value;
			case 'is not':
				return $rule_value !== $value;
			case 'is greater':
				return (int) $value > (int) $rule_value;
			case 'is less':
				return (int) $value < (int) $rule_value;
			default:
				return $this->matches_block_text_rule( $value, $condition, $rule_value );
		}
	}

	/**
	 * Match native Divi 5 text, existence and unknown conditional rules.
	 *
	 * @param string $value      Native-normalized source value.
	 * @param mixed  $condition  Native rule selector.
	 * @param mixed  $rule_value Stored rule value.
	 *
	 * @return bool
	 */
	private function matches_block_text_rule( string $value, $condition, $rule_value ): bool {
		switch ( $condition ) {
			case 'is empty':
				return '' === $value;
			case 'is not empty':
				return '' !== $value;
			case 'contains':
			case 'does not contain':
				$pattern = preg_replace( '/[\\\\^$*+?.()|[\]{}]/', '\\\\$0', $rule_value );
				$matches = (bool) preg_match( "/$pattern/", $value );

				return 'contains' === $condition ? $matches : ! $matches;
			default:
				return true;
		}
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => $this->get_active_divi_source( __CLASS__ ),
			'form_id' => 'contact',
		];
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @return void
	 */
	private function verify(): void {
		$submit_field = 'et_pb_contactform_submit_' . $this->render_count;
		$number_field = 'et_pb_contact_et_number_' . $this->render_count;
		$nonce_field  = '_wpnonce-et-pb-contact-form-submitted-' . $this->render_count;

		// Divi 4 checks the current submit marker and honeypot. Divi 5 uses the current module nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$submitted = null === $this->block_context
			? isset( $_POST[ $submit_field ] ) && empty( $_POST[ $number_field ] )
			: isset( $_POST[ $nonce_field ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $submitted ) {
			// Remove hCaptcha fields from current form fields, because Divi compares current and submitted fields.
			$current_form_field  = 'et_pb_contact_email_fields_' . $this->render_count;
			$current_form_fields = filter_input( INPUT_POST, $current_form_field, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$fields_data_array   = [];

			if ( $current_form_fields ) {
				$fields_data_json = html_entity_decode( str_replace( '\\', '', $current_form_fields ) );

					$fields_data_array = Utils::json_decode_arr( $fields_data_json );

				$fields_data_array            = $this->filter_fields_data( $fields_data_array );
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
