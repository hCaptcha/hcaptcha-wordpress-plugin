<?php
/**
 * Quform class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Quform;

use HCaptcha\Helpers\HCaptcha;
use Quform_Element_Page;
use Quform_Form;

/**
 * Class Quform.
 */
class Quform {

	/**
	 * Verify action.
	 */
	const ACTION = 'hcaptcha_quform';

	/**
	 * Verify nonce.
	 */
	const NONCE = 'hcaptcha_quform_nonce';

	/**
	 * Max form element id.
	 */
	const MAX_ID = '9999_9999';

	/**
	 * Quform constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'quform_pre_validate', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hcaptcha.
	 *
	 * @param string       $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string
	 */
	public function add_hcaptcha( string $output, string $tag, $attr, array $m ): string {
		if ( 'quform' !== $tag ) {
			return $output;
		}

		$max_id = self::MAX_ID;

		if ( preg_match_all( '/quform-element-(\d+?)_(\d+)\D/', $output, $m ) ) {
			$element_ids = array_map( 'intval', array_unique( $m[2] ) );
			$max_id      = $m[1][0] . '_' . ( max( $element_ids ) + 1 );
		}

		ob_start();
		?>
		<div class="quform-element quform-element-hcaptcha quform-element-<?php echo esc_attr( $max_id ); ?> quform-cf quform-element-required quform-hcaptcha-no-size">
			<div class="quform-spacer">
				<div class="quform-inner quform-inner-hcaptcha quform-inner-<?php echo esc_attr( $max_id ); ?>">
					<div class="quform-input quform-input-hcaptcha quform-input-<?php echo esc_attr( $max_id ); ?> quform-cf">
						<?php
						$args = [
							'action' => self::ACTION,
							'name'   => self::NONCE,
							'id'     => [
								'source'  => HCaptcha::get_class_source( static::class ),
								'form_id' => (int) $attr['id'],
							],
						];

						HCaptcha::form_display( $args );
						?>
						<noscript><?php esc_html_e( 'Please enable JavaScript to submit this form.', 'hcaptcha-for-forms-and-more' ); ?></noscript>
					</div>
				</div>
			</div>
		</div>
		<?php
		$hcaptcha = ob_get_clean();

		return preg_replace( '/(<div class="quform-element quform-element-submit)/', $hcaptcha . '$1', $output );
	}

	/**
	 * Verify.
	 *
	 * @param array|mixed $result Result.
	 * @param Quform_Form $form   Form.
	 *
	 * @return array|mixed
	 */
	public function verify( $result, Quform_Form $form ) {
		$page           = $form->getCurrentPage();
		$page_id        = $page ? $page->getId() : 0;
		$hcaptcha_name  = $this->get_max_element_id( $page );
		$hcaptcha_error = [
			'type'   => 'error',
			'error'  =>
				[
					'enabled' => false,
					'title'   => '',
					'content' => '',
				],
			'errors' => [ $hcaptcha_name => '' ],
			'page'   => $page_id,
		];

		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null !== $error_message ) {
			$hcaptcha_error['errors'] = [ $hcaptcha_name => $error_message ];

			return $hcaptcha_error;
		}

		return $result;
	}

	/**
	 * Get max form element id.
	 *
	 * @param Quform_Element_Page|null $page Current page.
	 *
	 * @return string
	 * @noinspection PhpMissingParamTypeInspection
	 */
	private function get_max_element_id( $page ): string {
		$max_id = self::MAX_ID;

		if ( null === $page ) {
			return $max_id;
		}

		$ids = array_map(
			static function ( $element ) {
				return $element->getId();
			},
			$page->getElements()
		);

		return $page->getForm()->getId() . '_' . ( max( $ids ) + 1 );
	}
}
