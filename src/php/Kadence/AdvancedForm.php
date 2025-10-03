<?php
/**
 * AdvancedForm class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Kadence;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use KB_Ajax_Advanced_Form;
use WP_Block;

/**
 * Class AdvancedForm.
 */
class AdvancedForm extends Base {

	/**
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-kadence-advanced';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaKadenceAdvancedFormObject';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-kadence-advanced';

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

		add_filter( 'render_block', [ $this, 'render_block' ], 10, 3 );

		if ( Request::is_frontend() || Request::is_post() ) {
			add_filter(
				'block_parser_class',
				static function () {
					return AdvancedBlockParser::class;
				}
			);
		}

		if ( Request::is_frontend() ) {
			add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
			add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );

			return;
		}

		add_action( 'wp_ajax_kb_process_advanced_form_submit', [ $this, 'process_ajax' ], 9 );
		add_action( 'wp_ajax_nopriv_kb_process_advanced_form_submit', [ $this, 'process_ajax' ], 9 );
		add_filter(
			'pre_option_kadence_blocks_hcaptcha_site_key',
			static function () {
				return hcaptcha()->settings()->get_site_key();
			}
		);
		add_filter(
			'pre_option_kadence_blocks_hcaptcha_secret_key',
			static function () {
				return hcaptcha()->settings()->get_secret_key();
			}
		);
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_assets' ] );
	}

	/**
	 * Render block filter.
	 *
	 * @param string|mixed $block_content Block content.
	 * @param array        $block         Block.
	 * @param WP_Block     $instance      Instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection HtmlUnknownAttribute
	 */
	public function render_block( $block_content, array $block, WP_Block $instance ): string {
		$block_content = (string) $block_content;

		if ( 'kadence/advanced-form-submit' === $block['blockName'] && ! $this->has_hcaptcha ) {

			$search = '<div class="kb-adv-form-field kb-submit-field';

			return str_replace( $search, $this->get_hcaptcha() . $search, $block_content );
		}

		if ( 'kadence/advanced-form-captcha' !== $block['blockName'] ) {
			return $block_content;
		}

		$block_content = (string) preg_replace(
			'#<div class="h-captcha" .*?></div>#',
			$this->get_hcaptcha(),
			$block_content,
			1,
			$count
		);

		$this->has_hcaptcha = (bool) $count;

		return $block_content;
	}

	/**
	 * Process ajax.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function process_ajax(): void {
		// Nonce is checked by Kadence.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$error = API::verify( $this->get_entry( $_POST ) );

		if ( null === $error ) {
			return;
		}

		KB_Ajax_Advanced_Form::get_instance()->process_bail(
			$error,
			__( 'hCaptcha Failed', 'hcaptcha-for-forms-and-more' )
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public static function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-kadence-advanced$min.js",
			[ 'hcaptcha', 'kadence-blocks-advanced-form' ],
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
	 * Enqueue editor assets.
	 *
	 * @return void
	 */
	public function editor_assets(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			HCAPTCHA_URL . "/assets/js/admin-kadence-advanced$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);

		$notice = HCaptcha::get_hcaptcha_plugin_notice();

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'noticeLabel'       => $notice['label'],
				'noticeDescription' => $notice['description'],
			]
		);

		wp_enqueue_style(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/admin-kadence-advanced$min.css",
			[],
			HCAPTCHA_VERSION
		);
	}

	/**
	 * Get hCaptcha.
	 *
	 * @return string
	 */
	private function get_hcaptcha(): string {
		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => AdvancedBlockParser::$form_id,
			],
		];

		return HCaptcha::form( $args );
	}

	/**
	 * Get entry.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_entry( array $form_data ): array {
		$post_id = (int) Request::filter_input( INPUT_POST, '_kb_adv_form_post_id' );
		$post    = get_post( $post_id );

		$entry = [
			'h-captcha-response' => $form_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $post->post_modified_gmt ?? null,
			'data'               => [],
		];

		$blocks = parse_blocks( $post->post_content ?? '' );
		$fields = $this->get_fields( $blocks );

		foreach ( $fields as $field ) {
			if ( ! preg_match( '#kadence/advanced-form-(.+)#', $field['blockName'] ?? '', $m ) ) {
				continue;
			}

			$type = $m[1];

			if ( ! in_array( $type, [ 'text', 'email', 'textarea' ], true ) ) {
				continue;
			}

			$attrs = $field['attrs'] ?? [];
			$attrs = wp_parse_args(
				$attrs,
				[
					'label'    => '',
					'uniqueID' => '',
				]
			);

			$label     = $attrs['label'];
			$unique_id = $attrs['uniqueID'];
			$value     = Request::filter_input( INPUT_POST, "field$unique_id" );

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
		$form_id = Request::filter_input( INPUT_POST, '_kb_adv_form_post_id' );
		$form    = $this->get_form( $blocks, $form_id );

		return $this->get_form_fields( $form['innerBlocks'] ?? [] );
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

			if ( 'kadence/advanced-form' === $block['blockName'] && 0 === strpos( $current_block_id, $block_id ) ) {
				return $block;
			}

			$form = $this->get_form( $block['innerBlocks'] ?? [], $block_id );

			if ( $form ) {
				return $form;
			}
		}

		return [];
	}

	/**
	 * Get Kadence form fields.
	 *
	 * @param array $blocks Blocks.
	 *
	 * @return array
	 */
	private function get_form_fields( array $blocks ): array {
		$form_fields  = [];
		$inner_fields = [];

		foreach ( $blocks as $block ) {
			if ( 0 === strpos( $block['blockName'], 'kadence/advanced-form' ) ) {
				$form_fields[] = $block;

				continue;
			}

			$inner_blocks = $block['innerBlocks'] ?? [];

			if ( $inner_blocks ) {
				$inner_fields[] = $this->get_form_fields( $inner_blocks );
			}
		}

		return array_merge( $form_fields, ...$inner_fields );
	}
}
