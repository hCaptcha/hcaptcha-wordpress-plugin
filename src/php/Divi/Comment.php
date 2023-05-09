<?php
/**
 * Comment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Comment.
 */
class Comment {

	/**
	 * Comment form shortcode tag.
	 */
	const TAG = 'et_pb_comments';

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_comment';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_comment_nonce';

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
		add_filter( self::TAG . '_shortcode_output', [ $this, 'add_captcha' ], 10, 2 );
	}

	/**
	 * Add hCaptcha to the comment form.
	 *
	 * @param string|string[] $output      Module output.
	 * @param string          $module_slug Module slug.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function add_captcha( $output, $module_slug ) {
		if ( et_core_is_fb_enabled() || false !== strpos( $output, 'h-captcha' ) ) {
			// Do not add captcha in frontend builder or if it already added by \HCaptcha\WP\Comment class.

			return $output;
		}

		$post_id = 0;

		if (
			preg_match(
				"<input type='hidden' name='comment_post_ID' value='(.+)?' id='comment_post_ID' />",
				$output,
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

		$pattern     = '/(<button name="submit")/';
		$replacement = HCaptcha::form( $args ) . "\n" . '$1';

		// Insert hcaptcha.
		return preg_replace( $pattern, $replacement, $output );
	}
}
