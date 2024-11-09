<?php
/**
 * AdvancedForm class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
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

		if ( Request::is_frontend() ) {
			add_filter(
				'block_parser_class',
				static function () {
					return AdvancedBlockParser::class;
				}
			);

			add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );

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
	 */
	public function process_ajax(): void {
		// Nonce is checked by Kadence.

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$error = hcaptcha_request_verify( $hcaptcha_response );

		if ( null === $error ) {
			return;
		}

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$data = [
			'html'     => '<div class="kb-adv-form-message kb-adv-form-warning">' . $error . '</div>',
			'console'  => __( 'hCaptcha Failed', 'hcaptcha-for-forms-and-more' ),
			'required' => null,
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
			'hcaptcha-kadence-advanced',
			HCAPTCHA_URL . "/assets/js/hcaptcha-kadence-advanced$min.js",
			[ 'hcaptcha', 'kadence-blocks-advanced-form' ],
			HCAPTCHA_VERSION,
			true
		);
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
}
