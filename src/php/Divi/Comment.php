<?php
/**
 * Comment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

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
		// Add origin after forming of the submit button by Divi, which does it on 10.
		add_filter( 'comment_form_submit_button', [ $this, 'add_origin' ], 20, 2 );

		if ( $this->active ) {
			add_filter( self::TAG . '_shortcode_output', [ $this, 'add_captcha' ], 10, 2 );
		}
	}

	/**
	 * Add origin.
	 *
	 * @param string $submit_button HTML markup for the submit button.
	 * @param array  $args          Arguments passed to comment_form().
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_origin( $submit_button, $args ) {
		$divi_comment_form = isset( $args['id_submit'] ) && ( 'et_pb_submit' === $args['id_submit'] );

		$origin = $this->active && $divi_comment_form ?
			Origin::create( self::ACTION, self::NONCE ) :
			'';

		return $origin . $submit_button;
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
		if ( et_core_is_fb_enabled() ) {
			// Do not add captcha in frontend builder.

			return $output;
		}

		$pattern     = '/(<button name="submit")/';
		$replacement = hcap_form( self::ACTION, self::NONCE ) . "\n" . '$1';

		// Insert hcaptcha.
		return preg_replace( $pattern, $replacement, $output );
	}
}
