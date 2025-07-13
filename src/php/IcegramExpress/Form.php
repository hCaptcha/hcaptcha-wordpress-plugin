<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\IcegramExpress;

use ES_Form_Widget;
use ES_Forms_Table;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Main;

/**
 * Class Form.
 */
class Form {

	/**
	 * Verify action.
	 */
	private const ACTION = 'hcaptcha_icegram_express';

	/**
	 * Verify nonce.
	 */
	private const NONCE = 'hcaptcha_icegram_express_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-icegram-express';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaIcegramExpressObject';

	/**
	 * There are forms to show in the popup.
	 *
	 * @var array
	 */
	private $show_in_popup = [];

	/**
	 * The Icegram Express widget is processing.
	 *
	 * @var bool
	 */
	private $in_widget = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// In shortcode.
		add_filter( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );

		// In dynamic sidebar.
		add_action( 'dynamic_sidebar', [ $this, 'dynamic_sidebar' ] );
		add_action( 'dynamic_sidebar_after', [ $this, 'dynamic_sidebar_after' ], 10, 2 );

		add_filter( 'ig_es_validate_subscription', [ $this, 'verify' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 9 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hCaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attribute array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, string $tag, $attr, array $m ): string {
		$output = (string) $output;

		if ( 'email-subscribers-form' !== $tag ) {
			return $output;
		}

		$attr    = (array) $attr;
		$form_id = (int) ( $attr['id'] ?? 0 );

		return $this->add_hcaptcha_to_form( $form_id, $attr, $output );
	}

	/**
	 * Start the output buffer if the current widget is Icegram Express.
	 *
	 * @param array $widget Widget.
	 *
	 * @return void
	 */
	public function dynamic_sidebar( array $widget ): void {
		$callback_class  = $widget['callback'][0] ?? null;
		$callback_method = $widget['callback'][1] ?? null;

		if ( ! ( $callback_class instanceof ES_Form_Widget && 'display_callback' === $callback_method ) ) {
			return;
		}

		$this->in_widget = true;

		ob_start();
	}

	/**
	 * Get the output buffer if the current widget is Icegram Express.
	 * Insert hCaptcha into the widget.
	 *
	 * @param int|string $index       Index, name, or ID of the dynamic sidebar.
	 * @param bool       $has_widgets Whether the sidebar is populated with widgets.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function dynamic_sidebar_after( $index, bool $has_widgets ): void {
		if ( ! $this->in_widget ) {
			return;
		}

		$output  = (string) ob_get_clean();
		$form_id = preg_match( '/data-form-id="(\d+)"/', $output, $m ) ? (int) $m[1] : 0;
		$output  = $this->add_hcaptcha_to_form( $form_id, null, $output );

		$this->in_widget = false;

		// Output was escaped in the Icegram Express plugin.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	/**
	 * Verify.
	 *
	 * @param array|mixed $result Result.
	 * @param array       $form   Form.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $result, array $form ): array {
		$result = (array) $result;

		$status = $result['status'] ?? 'ERROR';

		if ( 'ERROR' === $status ) {
			return $result;
		}

		$error_message = API::verify_request();

		if ( null !== $error_message ) {
			$error_code = 'hcaptcha_error';

			add_filter(
				'ig_es_subscription_messages',
				static function ( $messages ) use ( $error_code, $error_message ) {
					$messages[ $error_code ] = $error_message;

					return $messages;
				}
			);

			return [
				'status'  => 'ERROR',
				'message' => $error_code,
			];
		}

		return $result;
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
	.emaillist .h-captcha,
	.es_form_container .h-captcha {
		margin-bottom: 0.6em;
	}

	.emaillist form[data-form-id="3"] .h-captcha,
	.es_form_container form[data-form-id="3"] .h-captcha {
		margin: 0 auto 0.6em;;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Print footer scripts.
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void {
		if ( ! $this->show_in_popup ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-icegram-express$min.js",
			[ Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'hCaptchaWidgets' => wp_json_encode( $this->show_in_popup ),
			]
		);
	}

	/**
	 * Whether the form is to be show in popup.
	 *
	 * @param int        $form_id Form ID.
	 * @param array|null $attr    Shortcode attribute array or null if the form is rendered from the widget.
	 *
	 * @return bool
	 */
	private function show_in_popup( int $form_id, ?array $attr ): bool {
		if ( null === $attr && $this->in_widget ) {
			// In the sidebar widget, the popup form works standardly.
			return false;
		}

		$form = ES()->forms_db->get_form_by_id( $form_id );

		if ( ! $form ) {
			return false;
		}

		$data = ES_Forms_Table::get_form_data_from_body( $form );

		$show_in_popup_attr = isset( $attr['show-in-popup'] ) ? sanitize_text_field( $attr['show-in-popup'] ) : '';
		$show_in_popup_attr = $show_in_popup_attr ?: 'yes';
		$es_form_popup      = $data['show_in_popup'] ?? 'no';

		$show_in_popup = false;

		if ( ( 'yes' === $es_form_popup ) && 'yes' === $show_in_popup_attr ) {
			$show_in_popup = true;
		}

		return $show_in_popup;
	}

	/**
	 * Get hCaptcha arguments.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_args( int $form_id = 0 ): array {
		return [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];
	}

	/**
	 * Add hCaptcha to the form.
	 *
	 * @param int        $form_id Form ID.
	 * @param array|null $attr    Shortcode attribute array or null if the form is rendered from the widget.
	 * @param string     $output  Output.
	 *
	 * @return string
	 */
	private function add_hcaptcha_to_form( int $form_id, ?array $attr, string $output ): string {
		$hcaptcha = HCaptcha::form( $this->get_args( $form_id ) );

		if ( $this->show_in_popup( $form_id, $attr ) ) {
			$this->show_in_popup[ $form_id ] = $hcaptcha;

			// The hCaptcha must be inserted via JS in the popup.
			return $output;
		}

		$search = '<input type="submit"';

		return str_replace( $search, "\n$hcaptcha\n" . $search, $output );
	}
}
