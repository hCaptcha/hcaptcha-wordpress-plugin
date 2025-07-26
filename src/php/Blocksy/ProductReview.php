<?php
/**
 * Product Review class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Blocksy;

use HCaptcha\Abstracts\CommentBase;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\WP\Comment;

/**
 * Class Product Review.
 */
class ProductReview extends CommentBase {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_blocksy_product_review';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_blocksy_product_review_nonce';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		// The priority must be lower than in the HCaptcha\WP\Comment class to block its hook.
		add_filter( 'comment_form_submit_field', [ $this, 'add_hcaptcha' ], 0, 2 );

		parent::init_hooks();
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string|mixed $submit_field HTML markup for the 'submit' field.
	 * @param array        $comment_args Arguments passed to comment_form().
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $submit_field, array $comment_args ): string {
		$submit_field = (string) $submit_field;

		$screen = blocksy_manager()->screen;

		if ( ! $screen || ! $screen->is_product() ) {
			$search  = '<button';
			$replace = $this->get_signature() . $search;

			return str_replace( $search, $replace, $submit_field );
		}

		// Prevent adding hCaptcha by the HCaptcha\WP\Comment class.
		remove_action( 'comment_form_submit_field', [ hcaptcha()->get( Comment::class ), 'add_captcha' ] );

		$product       = get_queried_object();
		$this->form_id = (int) ( $product->ID ?? 0 );

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'sign'   => self::SIGNATURE_GROUP,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		$this->hcaptcha_shown = true;

		$search  = '<button';
		$replace = HCaptcha::form( $args ) . $search;

		return str_replace( $search, $replace, $submit_field );
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.ct-product-waitlist-form input[type="email"] {
		grid-row: 1;
	}

	.ct-product-waitlist-form h-captcha {
		grid-row: 2;
		margin-bottom: 0;
	}

	.ct-product-waitlist-form button {
		grid-row: 3;
	}
';

		HCaptcha::css_display( $css );
	}
}
