<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

//phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Brizy;

use WP_Post;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Base constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ], 10, 4 );
		add_filter( static::VERIFY_HOOK, [ $this, 'verify' ] );
	}

	/**
	 * Add captcha to the form.
	 *
	 * @param string               $content Content of the current post.
	 * @param Brizy_Editor_Project $project Brizy project.
	 * @param WP_Post              $post    Post.
	 * @param string               $type    Type of the content.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $content, $project, $post, $type = '' ) {
		if ( 'body' !== $type ) {
			return $content;
		}

		$search  = '<div class="brz-forms2 brz-forms2__item brz-forms2__item-button">';
		$replace =
			'<div class="brz-forms2 brz-forms2__item">' .
			hcap_form( static::ACTION, static::NAME ) .
			'</div>' .
			$search;

		return str_replace( $search, $replace, $content );
	}

	/**
	 * Verify captcha.
	 *
	 * @param mixed $form Validate fields.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $form ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data              = isset( $_POST['data'] ) ? sanitize_text_field( wp_unslash( $_POST['data'] ) ) : '';
		$data_arr          = json_decode( $data, true );
		$hcaptcha_response = '';

		foreach ( $data_arr as $item ) {
			if (
				isset( $item['name'], $item['value'] ) &&
				( 'g-recaptcha-response' === $item['name'] || 'h-captcha-response' === $item['name'] )
			) {
				$hcaptcha_response = $item['value'];
				break;
			}
		}

		$error_message = hcaptcha_request_verify( $hcaptcha_response );

		if ( null !== $error_message ) {
			wp_send_json_error(
				[
					'code'    => 400,
					'message' => $error_message,
				],
				200
			);
		}

		return $form;
	}
}
