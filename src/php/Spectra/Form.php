<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Spectra;

use Astra_Target_Rules_Fields;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use WP_Block;
use WP_Post;
use WP_Query;

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
	protected bool $has_recaptcha_field = false;

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
	 * @noinspection UnnecessaryCastingInspection
	 */
	public function render_block( $block_content, array $block, WP_Block $instance ) {
		if ( 'uagb/forms' !== $block['blockName'] ) {
			return $block_content;
		}

		$block_content = (string) $block_content;

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id( $block['attrs']['block_id'] ?? 0 ),
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Native email values are sanitized after decoding, without stripping HTML first.
		$raw_form_data = isset( $_POST['form_data'] ) && is_string( $_POST['form_data'] ) ? wp_unslash( $_POST['form_data'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$form_data        = Utils::json_decode_arr( sanitize_text_field( $raw_form_data ) );
		$native_form_data = Utils::json_decode_arr( $raw_form_data );

		$error_message = API::verify( $this->get_entry( $form_data, $native_form_data ) );

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
	 * Filter the print hCaptcha scripts status and return true if no reCaptcha is in the form.
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
	 * @param array      $form_data        Sanitized content and CAPTCHA transport.
	 * @param array|null $native_form_data Raw decoded native field values.
	 *
	 * @return array
	 */
	private function get_entry( array $form_data, ?array $native_form_data = null ): array {
		$native_form_data = $native_form_data ?? $form_data;
		$post_id          = (int) Request::filter_input( INPUT_POST, 'post_id' );
		$block_id         = Request::filter_input( INPUT_POST, 'block_id' );
		$post             = get_post( $post_id );

		$entry = [
			'nonce_name'         => self::NONCE,
			'nonce_action'       => self::ACTION,
			'h-captcha-response' => $form_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $post->post_modified_gmt ?? null,
			'post_data'          => $form_data,
			'data'               => [],
			'expected_id'        => $this->get_expected_id( $block_id ),
		];

		$blocks = parse_blocks( $post->post_content ?? '' );
		$fields = $this->get_fields( $blocks, $post->post_content ?? '' );
		$name   = [];
		$emails = [];

		foreach ( $fields as $field ) {
			$label = $field['label'];
			$this->add_email( $emails, $field, $native_form_data );

			if ( ! array_key_exists( $label, $form_data ) || '' === $label ) {
				continue;
			}
			$value = $form_data[ $label ];

			if ( EntryData::is_sensitive_field( (string) $label ) ) {
				continue;
			}

			if ( 'name' === $field['type'] ) {
				$name[] = $value;
			}

			EntryData::add_field( $entry['data'], (string) $label, $value, (string) $field['id'] );
		}

		EntryData::add_name( $entry['data'], $name );

		return EntryData::with_emails( $entry, $emails );
	}

	/**
	 * Normalize a saved native email field directly from its decoded JSON value.
	 *
	 * @param array $emails           Native email addresses.
	 * @param array $field            Trusted saved field schema.
	 * @param array $native_form_data Raw decoded native field values.
	 *
	 * @return void
	 */
	private function add_email( array &$emails, array $field, array $native_form_data ): void {
		$email = $native_form_data[ $field['label'] ] ?? '';
		if ( 'email' === $field['type'] && '' !== $field['label'] && is_string( $email ) ) {
			$emails[] = sanitize_email( $email );
		}
	}

	/**
	 * Get Spectra fields.
	 *
	 * @param array  $blocks Blocks.
	 * @param string $post_content Native page content.
	 *
	 * @return array
	 */
	private function get_fields( array $blocks, string $post_content = '' ): array {
		$block_id     = Request::filter_input( INPUT_POST, 'block_id' );
		$form         = $this->get_native_form( $blocks, $block_id, $post_content );
		$inner_blocks = $form['innerBlocks'] ?? [];

		return $this->collect_fields( $inner_blocks );
	}

	/**
	 * Collect saved field blocks below the selected native form.
	 * Native serialization includes all form elements, including those inside containers.
	 *
	 * @param array $blocks Form descendants.
	 *
	 * @return array
	 */
	private function collect_fields( array $blocks ): array {
		$fields = [];

		foreach ( $blocks as $inner_block ) {
			if ( 'uagb/forms' === ( $inner_block['blockName'] ?? '' ) ) {
				continue;
			}

			$field = $this->get_field( $inner_block, count( $fields ) );

			if ( ! $field ) {
				$fields = array_merge( $fields, $this->collect_fields( $inner_block['innerBlocks'] ?? [] ) );
				continue;
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Extract a saved static field and its native serialized label.
	 *
	 * @param array $block Block.
	 * @param int   $index Default field index.
	 *
	 * @return array
	 */
	private function get_field( array $block, int $index ): array {
		if ( ! preg_match( '#^uagb/forms-(.+)$#', $block['blockName'] ?? '', $matches ) ) {
			return [];
		}

		$type = $matches[1];

		if ( ! EntryData::is_content_field_type( $type ) ) {
			return [];
		}

		$label = '';

		if ( preg_match( '#<div class="uagb-forms-' . $type . '-label.*?>(.*?)</div>#', $block['innerHTML'] ?? '', $matches ) ) {
			$label = $matches[1];
		}

		return [
			'type'  => $type,
			'label' => $label,
			'id'    => (string) ( $block['attrs']['block_id'] ?? $index ),
		];
	}

	/**
	 * Follow native Spectra template precedence and block widget fallback.
	 *
	 * @param array  $blocks Page blocks.
	 * @param string $block_id Form block id.
	 * @param string $post_content Page content.
	 *
	 * @return array
	 */
	private function get_native_form( array $blocks, string $block_id, string $post_content ): array {
		$form          = has_block( 'uagb/forms', $post_content ) || has_block( 'core/block', $post_content ) ? $this->get_form( $blocks, $block_id ) : [];
		$template_form = $this->get_theme_form( $block_id, $post_content );

		if ( $template_form ) {
			return $template_form;
		}

		if ( $form ) {
			return $form;
		}

		foreach ( (array) get_option( 'widget_block', [] ) as $widget ) {
			if ( ! is_array( $widget ) || empty( $widget['content'] ) || ! has_block( 'uagb/forms', $widget['content'] ) ) {
				continue;
			}

			$form = $this->get_form( parse_blocks( $widget['content'] ), $block_id );

			if ( $form ) {
				return $form;
			}
		}

		return $this->get_astra_form( $block_id );
	}

	/**
	 * Resolve the first matching active-theme template using native precedence.
	 *
	 * @param string $block_id     Form ID.
	 * @param string $post_content Native page content.
	 *
	 * @return array
	 */
	private function get_theme_form( string $block_id, string $post_content ): array {
		if (
			! function_exists( '_build_block_template_result_from_post' ) ||
			! function_exists( 'wp_is_block_theme' ) ||
			! wp_is_block_theme()
		) {
			return [];
		}

		$query = new WP_Query(
			[
				'post_status' => [ 'publish' ],
				'post_type'   => [ 'wp_template', 'wp_template_part' ],
			]
		);

		foreach ( $query->posts as $post ) {
			$template = _build_block_template_result_from_post( $post );

			if ( is_wp_error( $template ) || get_template() !== $template->theme ) {
				continue;
			}

			$form = $this->get_form( parse_blocks( $template->content . $post_content ), $block_id );

			if ( $form ) {
				return $form;
			}
		}

		return [];
	}

	/**
	 * Get posts selected by native Astra advanced hook target rules.
	 *
	 * @return array
	 * @noinspection PhpUndefinedConstantInspection
	 */
	protected function get_astra_post_ids(): array {
		if ( ! defined( 'ASTRA_ADVANCED_HOOKS_POST_TYPE' ) || ! class_exists( '\Astra_Target_Rules_Fields' ) ) {
			return [];
		}

		$options = [
			'location'  => 'ast-advanced-hook-location',
			'exclusion' => 'ast-advanced-hook-exclusion',
			'users'     => 'ast-advanced-hook-users',
		];
		$posts   = Astra_Target_Rules_Fields::get_instance()->get_posts_by_conditions( ASTRA_ADVANCED_HOOKS_POST_TYPE, $options );

		return is_array( $posts ) ? array_keys( $posts ) : [];
	}

	/**
	 * Resolve a matching form in the native selected advanced hook posts.
	 *
	 * @param string $block_id Form ID.
	 *
	 * @return array
	 */
	private function get_astra_form( string $block_id ): array {
		foreach ( $this->get_astra_post_ids() as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || ! has_block( 'uagb/forms', $post->post_content ) ) {
				continue;
			}

			$form = $this->get_form( parse_blocks( $post->post_content ), $block_id );

			if ( $form ) {
				return $form;
			}
		}

		return [];
	}

	/**
	 * Get a Spectra form.
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

			$current_block_id = $block['attrs']['block_id'] ?? '';

			if ( 'uagb/forms' === $block['blockName'] && $current_block_id === $block_id ) {
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
	 * Follow native reusable and template-part references without revisiting cycles.
	 *
	 * @param array  $block      Block.
	 * @param string $block_id   Form ID.
	 * @param array  $references Referenced posts already visited.
	 *
	 * @return array
	 */
	private function get_referenced_form( array $block, string $block_id, array $references ): array {
		if ( 'core/template-part' === ( $block['blockName'] ?? '' ) ) {
			return $this->get_template_form( (string) ( $block['attrs']['slug'] ?? '' ), $block_id, $references );
		}

		if ( 'core/block' !== ( $block['blockName'] ?? '' ) ) {
			return [];
		}

		$reference = $block['attrs']['ref'] ?? 0;

		if ( ! is_int( $reference ) || ! $reference || in_array( $reference, $references, true ) ) {
			return [];
		}

		$post = get_post( $reference );

		return $post ? $this->get_form( parse_blocks( $post->post_content ), $block_id, array_merge( $references, [ $reference ] ) ) : [];
	}

	/**
	 * Resolve a template part using the native selected slug and saved post.
	 *
	 * @param string $slug       Template slug.
	 * @param string $block_id   Form ID.
	 * @param array  $references Referenced posts already visited.
	 *
	 * @return array
	 */
	private function get_template_form( string $slug, string $block_id, array $references ): array {
		foreach ( get_block_templates( [ 'slugs__in' => [ $slug ] ], 'wp_template_part' ) as $template ) {
			$reference = (int) $template->wp_id;

			if ( $slug !== $template->slug || ! $reference || in_array( $reference, $references, true ) ) {
				continue;
			}

			$post = get_post( $reference );
			$form = $this->get_form( parse_blocks( $post->post_content ?? '' ), $block_id, array_merge( $references, [ $reference ] ) );

			if ( $form ) {
				return $form;
			}
		}

		return [];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param int|string $form_id Form id.
	 *
	 * @return array
	 */
	private function get_expected_id( $form_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $form_id,
		];
	}
}
