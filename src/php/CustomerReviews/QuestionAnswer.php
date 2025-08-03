<?php
/**
 * Customer Reviews for WooCommerce - Question and Answer Forms class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CustomerReviews;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Question and Answer.
 */
class QuestionAnswer extends Base {

	/**
	 * Template name.
	 */
	protected const WC_TEMPLATE_NAME = 'single-product/tabs/tabs.php';

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
				'form_id' => 'q&a',
			],
		];

		// Find the $search string and insert hCaptcha before it.
		$search  = '#<div class="cr-review-form-buttons">\s*<button type="button" class="cr-review-form-submit" data-crcptcha=#';
		$replace =
			"\n" . '<div class="cr-review-form-item">' .
			HCaptcha::form( $args ) .
			"\n" . '</div>' .
			'$0';

		$template = preg_replace( $search, $replace, $template );

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

		add_action( 'wp_ajax_cr_new_qna', [ $this, 'verify' ], 0 );
		add_action( 'wp_ajax_nopriv_cr_new_qna', [ $this, 'verify' ], 0 );
	}
}
