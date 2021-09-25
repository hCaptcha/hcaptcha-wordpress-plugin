<?php
/**
 * Comment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use WP_Error;

/**
 * Class Comment
 */
class Comment {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( 'comment_form_submit_button', [ $this, 'add_captcha' ], 10, 2 );
		add_filter( 'pre_comment_approved', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string $submit_button HTML markup for the submit button.
	 * @param array  $args          Arguments passed to comment_form().
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $submit_button, $args ) {
		return hcap_form( 'hcaptcha_comment_form', 'hcaptcha_comment_form_nonce' ) . $submit_button;
	}

	/**
	 * Verify comment.
	 *
	 * @param int|string|WP_Error $approved    The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 * @param array               $commentdata Comment data.
	 *
	 * @return int|string|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $approved, $commentdata ) {
		if ( is_admin() ) {
			return $approved;
		}

		$error_message = hcaptcha_get_verify_message_html(
			'hcaptcha_comment_form_nonce',
			'hcaptcha_comment_form'
		);

		if ( null === $error_message ) {
			return $approved;
		}

		return new WP_Error( 'invalid_hcaptcha', __( 'Invalid Captcha', 'hcaptcha-for-forms-and-more' ), 400 );
	}
}
