<?php
/**
 * Customer Reviews for WooCommerce - Review Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CustomerReviews;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Review.
 */
class Review extends Base {

	/**
	 * Template name.
	 */
	protected const WC_TEMPLATE_NAME = 'cr-review-form.php';

	/**
	 * 'After template part' action.
	 * Gets the output buffer after the template part and adds hCaptcha.
	 *
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @param string $located       Located.
	 * @param array  $template_args Arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( string $template_name, string $template_path, string $located, array $template_args ): void {
		if ( self::WC_TEMPLATE_NAME !== $template_name ) {
			return;
		}

		$template = ob_get_clean();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'review',
			],
		];

		// Find the $search string and insert hCaptcha before it.
		$search  = '<div class="cr-review-form-buttons">';
		$replace =
			"\n" . '<div class="cr-review-form-item">' .
			HCaptcha::form( $args ) .
			"\n" . '</div>' .
			$search;

		$template = str_replace( $search, $replace, $template );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $template;
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_ajax_cr_submit_review', [ $this, 'verify' ], 0 );
		add_action( 'wp_ajax_nopriv_cr_submit_review', [ $this, 'verify' ], 0 );
	}
}
