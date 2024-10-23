<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

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
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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

		$pattern       = '/(<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">).+?(<\/div>)/';
		$block_content = (string) $block_content;

		if ( preg_match( $pattern, $block_content ) ) {
			// Do not replace reCaptcha V2.
			return $block_content;
		}

		if ( false !== strpos( $block_content, 'recaptcha_response' ) ) {
			// Do not replace reCaptcha V3.
			return $block_content;
		}

		$this->has_hcaptcha = true;

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => isset( $block['attrs']['postID'] ) ? (int) $block['attrs']['postID'] : 0,
			],
		];

		$search = '<div class="kadence-blocks-form-field kb-submit-field';

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
			'html'         => '<div class="kadence-blocks-form-message kadence-blocks-form-warning">' . $error . '</div>',
			'console'      => __( 'hCaptcha Failed', 'hcaptcha-for-forms-and-more' ),
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
			[ 'hcaptcha' ],
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
		// Nonce is checked by Kadence.

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$form_id = isset( $_POST['_kb_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['_kb_form_id'] ) ) : '';
		$post_id = isset( $_POST['_kb_form_post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['_kb_form_post_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		foreach ( parse_blocks( $post->post_content ) as $block ) {
			if (
				isset( $block['blockName'], $block['attrs']['uniqueID'] ) &&
				'kadence/form' === $block['blockName'] &&
				$form_id === $block['attrs']['uniqueID'] &&
				! empty( $block['attrs']['recaptcha'] )
			) {
				return true;
			}
		}

		return false;
	}
}
