<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Forminator;

use HCaptcha\Helpers\HCaptcha;
use Quform_Element_Page;
use Quform_Form;

/**
 * Class Form.
 */
class Form {

	/**
	 * Verify action.
	 */
	const ACTION = 'hcaptcha_forminator';

	/**
	 * Verify nonce.
	 */
	const NONCE = 'hcaptcha_forminator_nonce';

	/**
	 * Form id.
	 *
	 * @var int
	 */
	private $form_id = 0;

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
		add_action( 'forminator_before_form_render', [ $this, 'before_form_render' ], 10, 5 );
		add_filter( 'forminator_render_button_markup', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_filter( 'forminator_cform_form_is_submittable', [ $this, 'verify' ], 10, 3 );

		add_action( 'forminator_page_forminator-settings', [ $this, 'before_forminator_admin_page' ], 9 );
		add_action( 'forminator_page_forminator-settings', [ $this, 'after_forminator_admin_page' ], 11 );
	}

	/**
	 * Get form id before render.
	 *
	 * @param int|mixed $id            Form id.
	 * @param string    $form_type     Form type.
	 * @param int       $post_id       Post id.
	 * @param array     $form_fields   Form fields.
	 * @param array     $form_settings Form settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function before_form_render( $id, string $form_type, int $post_id, array $form_fields, array $form_settings ) {
		$this->form_id = $id;
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string|mixed $html   Shortcode output.
	 * @param string       $button Shortcode name.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $html, string $button ): string {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		return str_replace( '<button ', $hcaptcha . '<button ', (string) $html );
	}

	/**
	 * Verify.
	 *
	 * @param array|mixed $can_show      Can show the form.
	 * @param int         $id            Form id.
	 * @param array       $form_settings Form settings.
	 *
	 * @return array|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $can_show, int $id, array $form_settings ) {
		$error_message = hcaptcha_get_verify_message( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			return [
				'can_submit' => false,
				'error'      => $error_message,
			];
		}

		return $can_show;
	}

	/**
	 * Start output buffer before Forminator admin page.
	 *
	 * @return void
	 */
	public function before_forminator_admin_page() {
		ob_start();
	}

	/**
	 * Get and modify output buffer after Forminator admin page.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function after_forminator_admin_page() {
		$html = ob_get_clean();

		ob_start();
		?>
		<style>
			#hcaptcha-tab .sui-form-field {
				display: none;
			}
		</style>
		<?php
		$style = ob_get_clean();

		$label = esc_html__( 'hCaptcha plugin is active', 'hcaptcha-for-forms-and-more' );

		$url         = admin_url( 'options-general.php?page=hcaptcha&tab=general' );
		$description = sprintf(
		/* translators: 1: link to the General setting page */
			__( 'When hCaptcha plugin is active and integration with Forminator is on, hCaptcha settings must be modified on the %1$s.', 'hcaptcha-for-forms-and-more' ),
			sprintf(
				'<a href="%s" target="_blank">General settings page</a>',
				esc_url( $url )
			)
		);

		// phpcs:disable Generic.Commenting.DocComment.MissingShort
		$search  = [
			/** @lang PhpRegExp */
			'/(<div .*id="hcaptcha-tab")/',
			/** @lang PhpRegExp */
			'#<span class="sui-settings-label">(.*?)</span>#',
			/** @lang PhpRegExp */
			'#<span class="sui-description".*?>.*?</span>#s',
		];
		$replace = [
			$style . '$1',
			'<span class="sui-settings-label">' . $label . '</span>',
			'<span class="sui-description">' . $description . '</span>',
		];

		$html = preg_replace( $search, $replace, $html );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}
}
