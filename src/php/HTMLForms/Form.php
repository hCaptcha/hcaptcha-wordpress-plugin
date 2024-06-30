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

namespace HCaptcha\HTMLForms;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'html_forms_form';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'html_forms_form_nonce';

	/**
	 * The hCaptcha general error code.
	 */
	private const HCAPTCHA_ERROR = 'hcaptcha_error';

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	private $error_message;

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
		add_filter( 'hf_form_html', [ $this, 'add_captcha' ], 10, 2 );
		add_action( 'hf_admin_output_form_tab_fields', [ $this, 'add_to_fields' ] );
		add_filter( 'hf_validate_form_request_size', '__return_false' );
		add_filter( 'hf_validate_form', [ $this, 'verify' ], 10, 3 );
		add_filter( 'wp_insert_post_data', [ $this, 'insert_post_data' ], 10, 4 );
		add_filter( 'hf_form_message_' . self::HCAPTCHA_ERROR, [ $this, 'get_message' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Filter the submit button element HTML.
	 *
	 * @param string|mixed     $html Button HTML.
	 * @param \HTML_Forms\Form $form Form data and settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $html, \HTML_Forms\Form $form ): string {
		$form_id = (int) ( $form->ID ?? 0 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$preview_id = isset( $_GET['hf_preview_form'] ) ?
			(int) sanitize_text_field( wp_unslash( $_GET['hf_preview_form'] ) ) :
			0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $preview_id === $form_id ) {
			ob_start();
			$this->print_inline_styles();
			$html = ob_get_clean() . $html;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];

		return (string) preg_replace(
			'/(<p.*?>\s*?<input\s*?type="submit")/',
			HCaptcha::form( $args ) . "\n$1",
			$html
		);
	}

	/**
	 * Add hCaptcha to fields.
	 *
	 * @param \HTML_Forms\Form $form Form.
	 *
	 * @return void
	 */
	public function add_to_fields( \HTML_Forms\Form $form ): void {
		if ( false !== strpos( $form->markup, 'class="h-captcha"' ) ) {
			return;
		}

		$form->markup = $this->add_captcha( $form->markup, $form );
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param string|mixed     $error_code Error code.
	 * @param \HTML_Forms\Form $form       Form.
	 * @param array            $data       Form data.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $error_code, \HTML_Forms\Form $form, array $data ): string {
		$error_code = (string) $error_code;

		$this->error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null !== $this->error_message ) {
			return self::HCAPTCHA_ERROR;
		}

		return $error_code;
	}

	/**
	 * Filter inserted post data.
	 * Remove <div class="h-captcha"> form the content.
	 *
	 * @param array|mixed $data                An array of slashed, sanitized, and processed post data.
	 * @param array       $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @param array       $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as
	 *                                         originally passed to wp_insert_post().
	 * @param bool        $update              Whether this is an existing post being updated.
	 *
	 * @return array
	 * @noinspection RegExpRedundantEscape
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function insert_post_data( $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		$data = (array) $data;

		if ( 'html-form' !== $postarr['post_type'] ) {
			return $data;
		}

		$data['post_content'] = preg_replace(
			[
				'#\s*<div\s*?class=\\\"h-captcha\\\"[\s\S]*?</div>#',
				'#<input\s*?type=\\\"hidden\\\"\s*?id=\\\"html_forms_form_nonce\\\"[\s\S]*?/>#',
				'#<input\s*?type=\\\"hidden\\\"\s*?name=\\\"_wp_http_referer\\\"[\s\S]*?/>#',
			],
			[ '', '', '' ],
			$data['post_content']
		);

		return $data;
	}

	/**
	 * Get the error message.
	 *
	 * @param string $error_code Error code.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_message( string $error_code ): ?string {

		return $this->error_message;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	#form-preview .h-captcha {
		margin-bottom: 2rem;
	}

	.hf-fields-wrap .h-captcha {
		margin-top: 2rem;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
