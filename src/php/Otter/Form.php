<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Otter;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Response;
use WP_Block;

/**
 * Class Form.
 */
class Form {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-otter';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_otter';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_otter_nonce';

	/**
	 * Otter Form constructor.
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
		add_filter( 'option_themeisle_google_captcha_api_site_key', [ $this, 'replace_site_key' ], 10, 2 );
		add_filter( 'default_option_themeisle_google_captcha_api_site_key', [ $this, 'replace_site_key' ], 99, 3 );
		add_filter( 'render_block', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_filter( 'otter_form_anti_spam_validation', array( $this, 'verify' ) );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Block enqueuing a Google reCaptcha script by replacing Site Key.
	 *
	 * @return string
	 */
	public function replace_site_key(): string {
		return '';
	}

	/**
	 * Add hcaptcha to an Otter form.
	 *
	 * @param string|mixed $block_content The block content.
	 * @param array        $block         The full block, including name and attributes.
	 * @param WP_Block     $instance      The block instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $block_content, array $block, WP_Block $instance ): string {
		$block_content = (string) $block_content;

		if ( 'themeisle-blocks/form' !== $block['blockName'] ) {
			return $block_content;
		}

		$form_id = 0;

		if ( preg_match( '/<div id="wp-block-themeisle-blocks-form-(.+?)"/', $block_content, $m ) ) {
			$form_id = $m[1];
		}

		$args    = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];
		$button  = '<div class="wp-block-button">';
		$search  = [
			'/class="(.*?)has-captcha(.*?)"/',
			"/$button/",
		];
		$replace = [
			'class="$1$2"',
			HCaptcha::form( $args ) . "\n" . $button,
		];

		return preg_replace( $search, $replace, $block_content );
	}

	/**
	 * Verify the hCaptcha.
	 *
	 * @param Form_Data_Request|null|mixed $form_data Data from the request.
	 *
	 * @return Form_Data_Request|null
	 */
	public function verify( $form_data ): ?Form_Data_Request {
		if ( ! isset( $form_data ) ) {
			return $form_data;
		}

		if ( $form_data->has_error() ) {
			return $form_data;
		}

		$_POST['h-captcha-response'] = $form_data->get_root_data( 'h-captcha-response' ) ?: '';
		$_POST[ self::NONCE ]        = $form_data->get_root_data( self::NONCE ) ?: '';

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			$form_data->set_error( Form_Data_Response::ERROR_MISSING_CAPTCHA );
		}

		return $form_data;
	}

	/**
	 * Enqueue Otter script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-otter$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}
}
