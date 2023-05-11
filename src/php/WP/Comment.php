<?php
/**
 * Comment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Comment
 */
class Comment {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_comment';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_comment_nonce';

	/**
	 * Add captcha to the form.
	 *
	 * @var bool
	 */
	protected $active;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->active = hcaptcha()->settings()->is( 'wp_status', 'comment' );

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( 'comment_form_submit_field', [ $this, 'add_captcha' ], 10, 2 );
		add_filter( 'pre_comment_approved', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string $submit_field HTML markup for the submit field.
	 * @param array  $comment_args  Arguments passed to comment_form().
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $submit_field, $comment_args ) {
		$post_id = 0;

		if (
			preg_match(
				"<input type='hidden' name='comment_post_ID' value='(.+)?' id='comment_post_ID' />",
				$submit_field,
				$m
			)
		) {
			$post_id = $m[1];
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $post_id,
			],
		];

		if (
			! $this->active ||
			false !== strpos( $submit_field, 'et_pb_submit' )
		) {
			// If not active or Divi comment form, just add a signature.
			$args['protect'] = false;
		}

		$form = HCaptcha::form( $args );

		return $form . $submit_field;
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

		$error_message = hcaptcha_get_verify_message_html( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			return $this->invalid_captcha_error( $approved, $error_message );
		}

		return $approved;
	}

	/**
	 * Invalid captcha error.
	 *
	 * @param int|string|WP_Error $approved      The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 * @param string              $error_message The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 *
	 * @return WP_Error
	 */
	private function invalid_captcha_error( $approved, $error_message = '' ) {
		$error_message = $error_message ?: __( 'Invalid Captcha', 'hcaptcha-for-forms-and-more' );
		$approved      = is_wp_error( $approved ) ? $approved : new WP_Error();

		$approved->add( 'invalid_hcaptcha', $error_message, 400 );

		return $approved;
	}
}
