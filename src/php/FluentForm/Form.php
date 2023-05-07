<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\FluentForm;

use FluentForm\App\Modules\Form\FormFieldsParser;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Main;
use stdClass;

/**
 * Class Form
 */
class Form {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_fluentform';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_fluentform_nonce';

	/**
	 * Script handle.
	 */
	const HANDLE = 'hcaptcha-fluentform';

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
		add_action( 'fluentform_render_item_submit_button', [ $this, 'add_captcha' ], 10, 2 );
		add_action( 'fluentform_validation_errors', [ $this, 'verify' ], 10, 4 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'fluentform_rendering_form', [ $this, 'fluentform_rendering_form_filter' ] );
	}

	/**
	 * Action that fires immediately before the submit button element is displayed.
	 *
	 * @param array    $submit_button Form data and settings.
	 * @param stdClass $form          Form data and settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $submit_button, $form ) {
		// Do not add if form has its own hcaptcha.
		if ( $this->has_own_hcaptcha( $form ) ) {
			return;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
		];

		?>
		<div class="ff-el-group">
			<div class="ff-el-input--content">
				<div name="h-captcha-response">
					<?php HCaptcha::form_display( $args ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Filter errors during form validation.
	 *
	 * @param array    $errors Errors.
	 * @param array    $data   Sanitized entry fields.
	 * @param stdClass $form   Form data and settings.
	 * @param array    $fields Form fields.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, $data, $form, $fields ) {
		// Do not verify if form has its own hcaptcha.
		if ( $this->has_own_hcaptcha( $form ) ) {
			return $errors;
		}

		$hcaptcha_response = isset( $data['h-captcha-response'] ) ? $data['h-captcha-response'] : '';
		$error_message     = hcaptcha_request_verify( $hcaptcha_response );

		if ( null !== $error_message ) {
			$errors['h-captcha-response'] = [ $error_message ];
		}

		return $errors;
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-fluentform$min.js",
			[ Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
		];

		$form = HCaptcha::form( $args );
		$form = str_replace(
			'class="h-captcha"',
			'class="h-captcha-hidden" style="display: none;"',
			$form
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Fluentform load form assets hook.
	 *
	 * @param stdClass $form Form.
	 *
	 * @return stdClass
	 */
	public function fluentform_rendering_form_filter( $form ) {
		static $has_own_captcha = false;

		if ( $this->has_own_hcaptcha( $form ) ) {
			$has_own_captcha = true;
		}

		hcaptcha()->fluentform_support_required = ! $has_own_captcha;

		return $form;
	}

	/**
	 * Whether form has its own hcaptcha set in admin.
	 *
	 * @param stdClass $form Form data and settings.
	 *
	 * @return bool
	 */
	protected function has_own_hcaptcha( $form ) {
		$auto_include = apply_filters( 'ff_has_auto_hcaptcha', false );

		if ( $auto_include ) {
			return true;
		}

		FormFieldsParser::resetData();

		if ( FormFieldsParser::hasElement( $form, 'hcaptcha' ) ) {
			return true;
		}

		return false;
	}
}
