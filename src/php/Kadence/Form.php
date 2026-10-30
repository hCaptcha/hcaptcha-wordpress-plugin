<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Block;

/**
 * Class Form.
 */
class Form extends Base {

	/**
	 * Form constructor.
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
		parent::init_hooks();

		add_action( 'wp_ajax_kb_process_ajax_submit', [ $this, 'process_ajax' ], 9 );
		add_action( 'wp_ajax_nopriv_kb_process_ajax_submit', [ $this, 'process_ajax' ], 9 );

		if ( ! Request::is_frontend() ) {
			return;
		}

		add_filter( 'render_block', [ $this, 'render_block' ], 10, 3 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Render block filter.
	 *
	 * @param string|mixed $block_content Block content.
	 * @param array        $block         Block.
	 * @param WP_Block     $instance      Instance.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection UnnecessaryCastingInspection
	 */
	public function render_block( $block_content, array $block, WP_Block $instance ) {
		if ( 'kadence/form' !== $block['blockName'] ) {
			return $block_content;
		}

		$block_content = (string) $block_content;

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => isset( $block['attrs']['postID'] ) ? (int) $block['attrs']['postID'] : 0,
			],
		];
		$form = HCaptcha::form( $args );

		// // Replace reCaptcha V2 and V3.
		$pattern =
			'#' .
			'<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">.+?</div>' . // V2.
			'<input type="hidden" name="recaptcha_response" class=".+?">' . // V3.
			'#';

		if ( preg_match( $pattern, $block_content, $m ) ) {
			return str_replace( $m[0], $form, $block_content );
		}

		$this->has_captcha = true;

		$search = '<div class="kadence-blocks-form-field kb-submit-field';

		return (string) str_replace(
			$search,
			$form . $search,
			$block_content
		);
	}

	/**
	 * Process ajax.
	 *
	 * @return void
	 */
	public function process_ajax(): void {
		// Nonce is checked by Kadence.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$error = API::verify( $this->get_entry( $_POST ) );

		if ( null === $error ) {
			return;
		}

		$data = [
			'html'         => wp_kses_post(
				'<div class="kadence-blocks-form-message kadence-blocks-form-warning">' . $error . '</div>'
			),
			'console'      => esc_html__( 'hCaptcha Failed', 'hcaptcha-for-forms-and-more' ),
			'required'     => null,
			'headers_sent' => headers_sent(),
		];

		wp_send_json_error( $data );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public static function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-kadence',
			HCAPTCHA_URL . "/assets/js/hcaptcha-kadence$min.js",
			[ 'wp-hooks', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Get entry.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_entry( array $form_data ): array {
		// Match Kadence's post selector before binding the widget and its fields.
		$raw_post_id    = $form_data['_kb_form_post_id'] ?? '';
		$native_post_id = is_scalar( $raw_post_id ) ? sanitize_text_field( wp_unslash( (string) $raw_post_id ) ) : '';
		$post_id        = absint( $native_post_id );
		$post           = get_post( $post_id );

		$entry = [
			'h-captcha-response' => $form_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $post->post_modified_gmt ?? null,
			'data'               => [],
			'expected_id'        => $this->get_expected_id( $post_id ),
		];

		$blocks = $this->get_form_blocks( $native_post_id );
		$fields = $this->get_fields( $blocks );
		$emails = [];

		foreach ( $fields as $id => $field ) {
			$field = wp_parse_args(
				$field,
				[
					'type'  => '',
					'label' => '',
				]
			);

			$type = $field['type'];

			$label = (string) $field['label'];
			$value = $form_data[ "kb_field_$id" ] ?? '';

			if ( 'email' === $type && is_string( $value ) ) {
				$emails[] = sanitize_email( trim( wp_unslash( $value ) ) );
			}

			if (
				! array_key_exists( "kb_field_$id", $form_data ) ||
				! EntryData::is_content_field_type( (string) $type ) ||
				EntryData::has_sensitive_field( (string) $id, $label )
			) {
				continue;
			}

			EntryData::add_field( $entry['data'], $label, $value, (string) $id );
		}

		return EntryData::with_emails( $entry, $emails );
	}

	/**
	 * Resolve the post or block widget selected by native Kadence processing.
	 *
	 * @param string $post_id Native post or widget identifier.
	 *
	 * @return array
	 */
	private function get_form_blocks( string $post_id ): array {
		if ( false === strpos( $post_id, 'block' ) ) {
			$post = get_post( absint( $post_id ) );

			return parse_blocks( $post->post_content ?? '' );
		}

		$widgets = (array) get_option( 'widget_block', [] );

		if ( false === strpos( $post_id, 'block-unknown' ) ) {
			$widget_id = absint( preg_replace( '/[^0-9]/', '', $post_id ) );

			return parse_blocks( $widgets[ $widget_id ]['content'] ?? '' );
		}

		$form_id = Request::filter_input( INPUT_POST, '_kb_form_id' );

		if ( ! is_string( $form_id ) ) {
			return [];
		}

		foreach ( $widgets as $widget ) {
			$content = is_array( $widget ) ? ( $widget['content'] ?? '' ) : '';

			if ( is_string( $content ) && false !== strpos( $content, '<!-- wp:kadence/form {"uniqueID":"' . $form_id . '"' ) ) {
				return parse_blocks( $content );
			}
		}

		return [];
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
	 * Get Kadence fields.
	 *
	 * @param array $blocks Blocks.
	 *
	 * @return array
	 */
	private function get_fields( array $blocks ): array {
		$form_id = Request::filter_input( INPUT_POST, '_kb_form_id' );
		$form    = $this->get_form( $blocks, $form_id );

		return $form['attrs']['fields'] ?? [];
	}

	/**
	 * Get Kadence form.
	 *
	 * @param array  $blocks   Blocks.
	 * @param string $block_id Block ID.
	 * @param array  $references Referenced blocks already visited.
	 *
	 * @return array
	 */
	private function get_form( array $blocks, string $block_id, array $references = [] ): array {
		foreach ( $blocks as $block ) {
			$form = $this->get_referenced_form( $block, $block_id, $references );

			if ( $form ) {
				return $form;
			}

			$current_block_id = $block['attrs']['uniqueID'] ?? '';

			if ( 'kadence/form' === $block['blockName'] && $current_block_id === $block_id ) {
				return $block;
			}

			$form = $this->get_form( $block['innerBlocks'] ?? [], $block_id, $references );

			if ( $form ) {
				return $form;
			}
		}

		return [];
	}

	/**
	 * Resolve the native reusable form location without revisiting cycles.
	 *
	 * @param array  $block      Block.
	 * @param string $block_id   Form ID.
	 * @param array  $references Referenced blocks already visited.
	 *
	 * @return array
	 */
	private function get_referenced_form( array $block, string $block_id, array $references ): array {
		if ( 'core/block' !== ( $block['blockName'] ?? '' ) ) {
			return [];
		}

		$reference = (int) ( $block['attrs']['ref'] ?? 0 );

		if ( ! $reference || in_array( $reference, $references, true ) ) {
			return [];
		}

		$post = get_post( $reference );

		if ( ! $post || 'wp_block' !== $post->post_type ) {
			return [];
		}

		return $this->get_form( parse_blocks( $post->post_content ), $block_id, array_merge( $references, [ $reference ] ) );
	}
}
