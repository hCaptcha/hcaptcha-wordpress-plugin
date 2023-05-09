<?php
/**
 * Comment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Origin;

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
	const ACTION = 'hcaptcha_divi_comment';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_divi_comment_nonce';

	/**
	 * Add captcha to the form.
	 *
	 * @var bool
	 */
	private $active;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->active = hcaptcha()->settings()->is( 'divi_status', 'comment' );

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( 'comment_form_submit_field', [ $this, 'add_origin' ], 20, 2 );

		if ( $this->active ) {
			add_filter( self::TAG . '_shortcode_output', [ $this, 'add_captcha' ], 10, 2 );
		}
	}

	/**
	 * Add origin.
	 *
	 * @param string $submit_field HTML markup for the submit field.
	 * @param array  $comment_args Arguments passed to comment_form().
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_origin( $submit_field, $comment_args ) {
		if ( false !== strpos( $submit_field, Origin::NAME ) ) {
			// Origin can be added by \HCaptcha\WP\Comment.
			return $submit_field;
		}

		$divi_comment_form = isset( $comment_args['id_submit'] ) && ( 'et_pb_submit' === $comment_args['id_submit'] );

		$origin = $this->active && $divi_comment_form ?
			Origin::create( self::ACTION, self::NONCE ) :
			'';

		return $origin . $submit_field;
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
		if ( false !== strpos( $output, 'h-captcha' ) ) {
			// Captcha can be added by \HCaptcha\WP\Comment.
			return $output;
		}

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
