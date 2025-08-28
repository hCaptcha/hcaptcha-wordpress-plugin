<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Spectra;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Block;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_spectra_form';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_spectra_form_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-spectra';

	/**
	 * Whether form has reCaptcha field.
	 *
	 * @var bool
	 */
	protected $has_recaptcha_field;

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
		add_action( 'wp_ajax_uagb_process_forms', [ $this, 'process_ajax' ], 9 );
		add_action( 'wp_ajax_nopriv_uagb_process_forms', [ $this, 'process_ajax' ], 9 );

		if ( ! Request::is_frontend() ) {
			return;
		}

		add_filter( 'render_block', [ $this, 'render_block' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ], 0 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
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
		if ( 'uagb/forms' !== $block['blockName'] ) {
			return $block_content;
		}

		$block_content = (string) $block_content;

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => isset( $block['attrs']['block_id'] ) ? (int) $block['attrs']['block_id'] : 0,
			],
		];

		$this->has_recaptcha_field = false;

		if ( false !== strpos( $block_content, 'uagb-forms-recaptcha' ) ) {
			$this->has_recaptcha_field = true;

			// Do not replace reCaptcha.
			return $block_content;
		}

		$search = '<div class="uagb-forms-main-submit-button-wrap';

		return (string) str_replace(
			$search,
			HCaptcha::form( $args ) . $search,
			$block_content
		);
	}

	/**
	 * Process ajax.
	 *
	 * @return void
	 */
	public function process_ajax(): void {
		if ( $this->has_recaptcha() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$form_data = isset( $_POST['form_data'] )
			? json_decode( sanitize_text_field( wp_unslash( $_POST['form_data'] ) ), true )
			: [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$widget_id_name = 'hcaptcha-widget-id';
		$hp_sig_name    = 'hcap_hp_sig';
		$token_name     = 'hcap_fst_token';
		$hp_name        = API::get_hp_name( $form_data );

		$_POST[ self::NONCE ]     = $form_data[ self::NONCE ] ?? '';
		$_POST[ $widget_id_name ] = $form_data[ $widget_id_name ] ?? '';
		$_POST[ $hp_sig_name ]    = $form_data[ $hp_sig_name ] ?? '';
		$_POST[ $hp_name ]        = $form_data[ $hp_name ] ?? '';
		$_POST[ $token_name ]     = $form_data[ $token_name ] ?? '';

		$error_message = API::verify( $this->get_entry( $form_data ) );

		unset(
			$_POST[ self::NONCE ],
			$_POST[ $widget_id_name ],
			$_POST[ $hp_sig_name ],
			$_POST[ $hp_name ],
			$_POST[ $token_name ]
		);

		if ( null === $error_message ) {
			return;
		}

		wp_send_json_error( $error_message );
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		static $style_shown;

		if ( $style_shown ) {
			return;
		}

		$style_shown = true;

		/* language=CSS */
		$css = '
	.uagb-forms-main-form .h-captcha {
		margin-bottom: 20px;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Filter print hCaptcha scripts status and return true if no reCaptcha is in the form.
	 *
	 * @param bool|mixed $status Print scripts status.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		return ! $this->has_recaptcha_field;
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-spectra$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add type="module" attribute to script tag.
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

	/**
	 * Whether form has recaptcha.
	 *
	 * @return bool
	 */
	protected function has_recaptcha(): bool {
		// Spectra checks nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_id  = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
		$block_id = isset( $_POST['block_id'] ) ? sanitize_text_field( wp_unslash( $_POST['block_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$post_content = get_post_field( 'post_content', sanitize_text_field( $post_id ) );

		foreach ( parse_blocks( $post_content ) as $block ) {
			if (
				isset( $block['blockName'], $block['attrs']['block_id'] ) &&
				'uagb/forms' === $block['blockName'] &&
				$block_id === $block['attrs']['block_id'] &&
				! empty( $block['attrs']['reCaptchaEnable'] )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get entry.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_entry( array $form_data ): array {
		$post_id = (int) Request::filter_input( INPUT_POST, 'post_id' );
		$post    = get_post( $post_id );

		$entry = [
			'nonce_name'         => self::NONCE,
			'nonce_action'       => self::ACTION,
			'h-captcha-response' => $form_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $post->post_modified_gmt ?? null,
			'data'               => [],
		];

		$blocks = parse_blocks( $post->post_content ?? '' );
		$fields = $this->get_fields( $blocks );
		$name   = [];

		foreach ( $fields as $field ) {
			$value = $form_data[ $field['label'] ] ?? '';

			if ( 'name' === $field['type'] ) {
				$name[] = $value;
			}

			if ( 'email' === $field['type'] ) {
				$entry['data']['email'] = $value;
			}

			$entry['data'][ $field['label'] ] = $value;
		}

		$entry['data']['name'] = implode( ' ', $name ) ?: null;

		return $entry;
	}

	/**
	 * Get Spectra fields.
	 *
	 * @param array $blocks Blocks.
	 *
	 * @return array
	 */
	private function get_fields( array $blocks ): array {
		$block_id     = Request::filter_input( INPUT_POST, 'block_id' );
		$form         = $this->get_form( $blocks, $block_id );
		$inner_blocks = $form['innerBlocks'] ?? [];
		$fields       = [];

		foreach ( $inner_blocks as $inner_block ) {
			if ( ! preg_match( '#uagb/forms-(.+)#', $inner_block['blockName'], $m ) ) {
				continue;
			}

			$type = $m[1];

			if ( ! in_array( $type, [ 'name', 'email', 'textarea' ], true ) ) {
				continue;
			}

			$label = '';

			if ( preg_match( '#<div class="uagb-forms-' . $type . '-label.*?>(.*?)</div>#', $inner_block['innerHTML'], $m ) ) {
				$label = $m[1];
			}

			$fields[] = [
				'type'  => $type,
				'label' => $label,
			];
		}

		return $fields;
	}

	/**
	 * Get a Spectra form.
	 *
	 * @param array  $blocks   Blocks.
	 * @param string $block_id Block ID.
	 *
	 * @return array
	 */
	private function get_form( array $blocks, string $block_id ): array {
		foreach ( $blocks as $block ) {
			$current_block_id = $block['attrs']['block_id'] ?? '';

			if ( 'uagb/forms' === $block['blockName'] && $current_block_id === $block_id ) {
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
