<?php
/**
 * Contact class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

/**
 * Class Contact
 */
class Contact {

	/**
	 * Contact form shortcode tag.
	 */
	const TAG = 'et_pb_contact_form';

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_divi_cf';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_divi_cf_nonce';

	/**
	 * Render counter.
	 *
	 * @var int
	 */
	protected $render_count = 0;

	/**
	 * Captcha status.
	 *
	 * @var string
	 */
	protected $captcha = 'off';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( self::TAG . '_shortcode_output', [ $this, 'add_captcha' ], 10, 2 );
		add_filter( 'pre_do_shortcode_tag', [ $this, 'verify' ], 10, 4 );

		add_filter( 'et_pb_module_shortcode_attributes', [ $this, 'shortcode_attributes' ], 10, 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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
	public function add_captcha( $output, $module_slug ) {
		if ( et_core_is_fb_enabled() ) {
			// Do not add captcha in frontend builder.

			return $output;
		}

		$search  = '<div class="et_contact_bottom_container">';
		$replace =
			'<div style="float:right;">' .
			hcap_form( self::ACTION, self::NONCE ) .
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

		$this->render_count ++;

		return $output;
	}

	/**
	 * Verify hcaptcha.
	 * We use shortcode tag filter to make verification.
	 *
	 * @param false|string $return Short-circuit return value. Either false or the value to replace the shortcode with.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $return, $tag, $attr, $m ) {
		if ( self::TAG !== $tag ) {
			return $return;
		}

		$cf_nonce_field = '_wpnonce-et-pb-contact-form-submitted-' . $this->render_count;
		$cf_nonce       = filter_input( INPUT_POST, $cf_nonce_field, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$nonce_result   = isset( $cf_nonce ) && wp_verify_nonce( $cf_nonce, 'et-pb-contact-form-submit' );

		$submit_field = 'et_pb_contactform_submit_' . $this->render_count;
		$number_field = 'et_pb_contact_et_number_' . $this->render_count;

		// Check that the form was submitted and et_pb_contact_et_number field is empty to protect from spam.
		if ( $nonce_result && isset( $_POST[ $submit_field ] ) && empty( $_POST[ $number_field ] ) ) {
			// Remove hcaptcha from current form fields, because Divi compares current and submitted fields.
			$current_form_field  = 'et_pb_contact_email_fields_' . $this->render_count;
			$current_form_fields = filter_input( INPUT_POST, $current_form_field, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( $current_form_fields ) {
				$fields_data_json             = htmlspecialchars_decode( str_replace( '\\', '', $current_form_fields ) );
				$fields_data_array            = json_decode( $fields_data_json, true );
				$fields_data_array            = null === $fields_data_array ? [] : $fields_data_array;
				$fields_data_array            = array_filter(
					$fields_data_array,
					static function ( $item ) {
						return false === strpos( $item['field_id'], 'captcha' );
					}
				);
				$fields_data_json             = wp_json_encode( $fields_data_array );
				$_POST[ $current_form_field ] = $fields_data_json;
			}

			$error_message = hcaptcha_get_verify_message( self::NONCE, self::ACTION );

			if ( null !== $error_message ) {
				// Simulate captcha error.
				$this->captcha = 'on';
			}
		}

		return $return;
	}

	/**
	 * Filters Module Props.
	 *
	 * @param array  $props    Array of processed props.
	 * @param array  $attrs    Array of original shortcode attrs.
	 * @param string $slug     Module slug.
	 * @param string $_address Module Address.
	 * @param string $content  Module content.
	 *
	 * @return array
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function shortcode_attributes( $props, $attrs, $slug, $_address, $content ) {
		if ( self::TAG !== $slug ) {
			return $props;
		}

		$props['captcha']          = $this->captcha;
		$props['use_spam_service'] = $this->captcha;
		$this->captcha             = 'off';

		return $props;
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-divi',
			HCAPTCHA_URL . "/assets/js/hcaptcha-divi$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
