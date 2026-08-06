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

namespace HCaptcha\MetForm;

use Elementor\Plugin;
use Elementor\Widget_Base;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use MetForm\Core\Entries\Action as EntriesAction;
use WP_REST_Request;

/**
 * Class Form.
 */
class Form {

	/**
	 * MetForm submit button widget name.
	 */
	private const WIDGET_NAME = 'mf-button';

	/**
	 * MetForm REST route prefix.
	 */
	private const REST_ROUTE = '/metform/v1/entries/insert/';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_metform';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_metform_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-metform';

	/**
	 * Current MetForm form ID.
	 *
	 * @var int
	 */
	private int $form_id = 0;

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
		add_filter( 'elementor/widget/render_content', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'capture_form_id' ], 10, 3 );
		add_filter( 'mf_after_validation_check', [ $this, 'verify' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add hCaptcha before the MetForm submit button.
	 *
	 * @param string|mixed $content Widget content.
	 * @param Widget_Base  $widget  Elementor widget.
	 *
	 * @return string
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function add_hcaptcha( $content, Widget_Base $widget ): string {
		$content = (string) $content;

		if ( self::WIDGET_NAME !== $widget->get_name() ) {
			return $content;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->get_form_id(),
			],
		];

		$hcaptcha = rawurlencode( HCaptcha::form( $args ) );

		return sprintf(
			'<div class="hcaptcha-metform-placeholder" data-hcaptcha-html="%1$s"></div>' . "\n" . '%2$s',
			esc_attr( $hcaptcha ),
			$content
		);
	}

	/**
	 * Capture the form ID from the current MetForm REST request.
	 *
	 * @param mixed           $response Result to send to the client.
	 * @param array           $handler  Route handler used for the request.
	 * @param WP_REST_Request $request  Request used to generate the response.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function capture_form_id( $response, array $handler, WP_REST_Request $request ) {
		$route = $request->get_route();

		if ( preg_match( '#^' . preg_quote( self::REST_ROUTE, '#' ) . '(\d+)/?$#', $route, $matches ) ) {
			$this->form_id = (int) $matches[1];
		}

		return $response;
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param array|mixed $validation MetForm validation data.
	 *
	 * @return array
	 */
	public function verify( $validation ): array {
		$validation = (array) $validation;

		if ( empty( $validation['is_valid'] ) ) {
			return $validation;
		}

		$form_data = (array) ( $validation['form_data'] ?? [] );
		$error     = API::verify( $this->get_entry( $form_data ) );

		if ( null !== $error ) {
			$validation['is_valid'] = false;
			$validation['message']  = $error;
		}

		return $validation;
	}

	/**
	 * Get entry.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_entry( array $form_data ): array {
		$form = get_post( $this->form_id );

		return [
			'nonce_name'         => self::NONCE,
			'nonce_action'       => self::ACTION,
			'h-captcha-response' => $form_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $form->post_modified_gmt ?? null,
			'post_data'          => $form_data,
			'data'               => $this->get_data( $form_data ),
			'expected_id'        => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];
	}

	/**
	 * Get data for anti-spam checks.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_data( array $form_data ): array {
		$data = [];
		$name = [];

		foreach ( $this->get_fields() as $field_name => $field ) {
			$field_name = (string) $field_name;
			$field      = (array) $field;
			$value      = $this->get_field_value( $form_data, $field_name );

			if ( null === $value ) {
				continue;
			}

			$label      = (string) ( $field['mf_input_label'] ?? '' );
			$input_name = (string) ( $field['mf_input_name'] ?? $field_name );
			$type       = (string) ( $field['widgetType'] ?? '' );
			$key        = $label ?: $input_name;

			if ( 'mf-email' === $type ) {
				$data['email'] = $value;
			}

			if ( $this->is_name_field( $type, $input_name, $label ) ) {
				$name[] = $value;
			}

			$data[ $key ] = $value;
		}

		$data['name'] = implode( ' ', $name ) ?: null;

		return $data;
	}

	/**
	 * Get a normalized form field value.
	 *
	 * @param array  $form_data Form data.
	 * @param string $field_name Field name.
	 *
	 * @return string|null
	 */
	private function get_field_value( array $form_data, string $field_name ): ?string {
		if ( ! array_key_exists( $field_name, $form_data ) ) {
			return null;
		}

		$value = $form_data[ $field_name ];

		if ( is_array( $value ) ) {
			$value = implode( ' ', $value );
		}

		$value = (string) $value;

		return '' === $value ? null : $value;
	}

	/**
	 * Determine whether the field contains a name or a part of a name.
	 *
	 * @param string $type       MetForm widget type.
	 * @param string $input_name Input name.
	 * @param string $label      Input label.
	 *
	 * @return bool
	 */
	private function is_name_field( string $type, string $input_name, string $label ): bool {
		return in_array( $type, [ 'mf-listing-fname', 'mf-listing-lname' ], true ) ||
			'name' === $input_name ||
			'name' === strtolower( $label );
	}

	/**
	 * Get MetForm fields.
	 *
	 * @return array
	 * @noinspection PhpUndefinedMethodInspection
	 */
	protected function get_fields(): array {
		return (array) EntriesAction::instance()->get_fields( $this->form_id );
	}

	/**
	 * Enqueue MetForm integration script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . '/assets/js/hcaptcha-metform.min.js',
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Get the current MetForm document ID.
	 *
	 * @return int
	 */
	protected function get_form_id(): int {
		$documents = Plugin::$instance->documents ?? null;

		if ( ! $documents || ! method_exists( $documents, 'get_current' ) ) {
			return 0;
		}

		$document = $documents->get_current();

		return $document && method_exists( $document, 'get_main_id' )
			? (int) $document->get_main_id()
			: 0;
	}
}
