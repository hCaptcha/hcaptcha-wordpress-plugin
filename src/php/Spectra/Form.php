<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Spectra;

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
		add_action( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ] );
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
		$form_data = isset( $_POST['form_data'] ) ?
			json_decode( sanitize_text_field( wp_unslash( $_POST['form_data'] ) ), true ) :
			[];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$_POST['h-captcha-response'] = $form_data['h-captcha-response'] ?? '';
		$_POST[ self::NONCE ]        = $form_data[ self::NONCE ] ?? '';

		$error_message = hcaptcha_verify_post( self::NONCE, self::ACTION );

		unset( $_POST['h-captcha-response'], $_POST[ self::NONCE ] );

		if ( null === $error_message ) {
			return;
		}

		// Spectra cannot process error messages from the backend.
		wp_send_json_error( 400 );
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
}
