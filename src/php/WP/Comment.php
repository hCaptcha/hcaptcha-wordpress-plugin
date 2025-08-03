<?php
/**
 * The 'Comment' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Abstracts\CommentBase;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Comment
 */
class Comment extends CommentBase {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_comment';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_comment_nonce';

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
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_filter( 'comment_form_submit_field', [ $this, 'add_captcha' ], 10, 2 );

		parent::init_hooks();
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $submit_field HTML markup for the 'submit' field.
	 * @param array        $comment_args Arguments passed to comment_form().
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $submit_field, array $comment_args ): string {
		$submit_field = (string) $submit_field;
		$post_id      = 0;

		if (
			preg_match(
				"<input type='hidden' name='comment_post_ID' value='(.+)?' id='comment_post_ID' />",
				$submit_field,
				$m
			)
		) {
			$post_id = $m[1];
		}

		$this->form_id = (int) $post_id;

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'sign'   => self::SIGNATURE_GROUP,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		if (
			! $this->active ||
			false !== strpos( $submit_field, 'et_pb_submit' )
		) {
			// If not active or Divi comment form, just add a signature.
			$args['protect'] = false;
		}

		$this->hcaptcha_shown = true;

		$form = HCaptcha::form( $args );

		return $form . $submit_field;
	}
}
