<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

use HCaptcha\Helpers\API;
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
		$post_id = isset( $form_data['_kb_form_post_id'] ) ? (int) $form_data['_kb_form_post_id'] : 0;
		$post    = get_post( $post_id );

		$entry = [
			'h-captcha-response' => $form_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $post->post_modified_gmt ?? null,
			'data'               => [],
		];

		$blocks = parse_blocks( $post->post_content ?? '' );
		$fields = $this->get_fields( $blocks );

		foreach ( $fields as $id => $field ) {
			$field = wp_parse_args(
				$field,
				[
					'type'  => '',
					'label' => '',
				]
			);

			$type = $field['type'];

			if ( ! in_array( $type, [ 'text', 'email', 'textarea' ], true ) ) {
				continue;
			}

			$label = $field['label'];
			$value = $form_data[ "kb_field_$id" ] ?? '';

			if ( 'email' === $type ) {
				$entry['data']['email'] = $value;
			}

			$entry['data'][ $label ] = $value;
		}

		return $entry;
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
	 *
	 * @return array
	 */
	private function get_form( array $blocks, string $block_id ): array {
		foreach ( $blocks as $block ) {
			$current_block_id = $block['attrs']['uniqueID'] ?? '';

			if ( 'kadence/form' === $block['blockName'] && $current_block_id === $block_id ) {
				return $block;
			}

			$form = $this->get_form( $block['innerBlocks'] ?? [], $block_id );

			if ( $form ) {
				return $form;
			}
		}

		return [];
	}
}
