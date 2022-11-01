<?php
/**
 * Quform class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Quform;

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
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, $tag, $attr, $m ) {
		if ( 'quform' !== $tag ) {
			return $output;
		}

		$max_id = '9999_9999';

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
						<?php hcap_form_display( self::ACTION, self::NONCE ); ?>
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
	 * @param array       $result Result.
	 * @param Quform_Form $form   Form.
	 *
	 * @return array
	 */
	public function verify( $result, $form ) {
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
	 */
	private function get_max_element_id( $page ) {
		$max_id = '9999_9999';

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
