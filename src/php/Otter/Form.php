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
use WP_Block;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

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
	 * Error code.
	 *
	 * @var int
	 */
	private $error_code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private $error_message;

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
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
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

		$post_data = $form_data->dump_data()['form_data'];

		$this->error_message = API::verify_post_data( self::NONCE, self::ACTION, $post_data );

		if ( null !== $this->error_message ) {
			$this->error_code = array_search( $this->error_message, hcap_get_error_messages(), true ) ?: 'fail';

			// Error processing in Otter is not very helpful.
			$form_data->set_error( $this->error_code, $this->error_message );

			add_filter( 'rest_request_after_callbacks', [ $this, 'filter_response' ], 10, 3 );
		}

		return $form_data;
	}

	/**
	 * Filters the response immediately after executing any REST API
	 * callbacks.
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
	 *
	 * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function filter_response( $response, array $handler, WP_REST_Request $request ) {
		if ( '/otter/v1/form/frontend' !== $request->get_route() ) {
			return $response;
		}

		$data                 = $response->get_data();
		$data['code']         = $this->error_code;
		$data['displayError'] = $this->error_message;

		$response->set_data( $data );

		return $response;
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
}
