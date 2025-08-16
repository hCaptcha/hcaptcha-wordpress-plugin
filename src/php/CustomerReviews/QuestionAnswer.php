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
	 * WC product Q&A tab callback.
	 *
	 * @var callable
	 */
	private $qna_callback;

	/**
	 * Whether hCaptcha was added.
	 *
	 * @var bool
	 */
	protected $has_hcaptcha = false;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'woocommerce_product_tabs', [ $this, 'product_tabs' ], 20 );

		add_action( 'wp_ajax_cr_new_qna', [ $this, 'verify' ], 0 );
		add_action( 'wp_ajax_nopriv_cr_new_qna', [ $this, 'verify' ], 0 );
	}

	/**
	 * Filter WC product tabs.
	 *
	 * @param array|mixed $tabs Tabs.
	 *
	 * @return array
	 */
	public function product_tabs( $tabs ): array {
		$tabs = (array) $tabs;

		if ( ! isset( $tabs['cr_qna'] ) ) {
			return $tabs;
		}

		$this->qna_callback = $tabs['cr_qna']['callback'];

		$tabs['cr_qna']['callback'] = [ $this, 'display_qna_tab' ];

		return $tabs;
	}

	/**
	 * Display Q&A tab.
	 *
	 * @param mixed $attributes Attributes.
	 *
	 * @return void
	 */
	public function display_qna_tab( $attributes ): void {
		ob_start();
		call_user_func( $this->qna_callback, $attributes );
		$this->add_captcha( self::WC_TEMPLATE_NAME, '', '', [] );
	}

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

		if ( ! $this->has_hcaptcha ) {
			$this->has_hcaptcha = true;

			$args = [
				'action' => self::ACTION,
				'name'   => self::NONCE,
				'id'     => [
					'source'  => HCaptcha::get_class_source( __CLASS__ ),
					'form_id' => 'q&a',
				],
			];

			// Find the $search string and insert hCaptcha before it.
			$search   = '#<div class="cr-review-form-buttons">\s*<button type="button" class="cr-review-form-submit" data-crcptcha=#';
			$template = preg_replace_callback(
				$search,
				static function ( array $m ) use ( $args ) {
					return (
						"\n" . '<div class="cr-review-form-item">' .
						HCaptcha::form( $args ) .
						"\n" . '</div>' .
						$m[0]
					);
				},
				$template
			);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $template;
	}
}
