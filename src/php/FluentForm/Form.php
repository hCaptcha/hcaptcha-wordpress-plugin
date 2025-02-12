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

use FluentForm\App\Models\Form as FluentForm;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;
use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Main;
use stdClass;

/**
 * Class Form
 *
 * Can be used as a login form also.
 */
class Form extends LoginBase {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_fluentform';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_fluentform_nonce';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-fluentform';

	/**
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-fluentform';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaFluentFormObject';

	/**
	 * Conversational form id.
	 *
	 * @var int
	 */
	protected $form_id = 0;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_filter( 'fluentform/rendering_field_html_hcaptcha', [ $this, 'render_field_hcaptcha' ], 10, 3 );
		add_action( 'fluentform/render_item_submit_button', [ $this, 'add_hcaptcha' ], 9, 2 );
		add_action( 'fluentform/validation_errors', [ $this, 'verify' ], 10, 4 );
		add_filter( 'fluentform/rendering_form', [ $this, 'fluentform_rendering_form_filter' ] );
		add_filter( 'fluentform/has_hcaptcha', [ $this, 'fluentform_has_hcaptcha' ] );
		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Replace Fluent Forms hCaptcha field.
	 * Works for embedded hCaptcha field.
	 *
	 * @param string|mixed $html The hCaptcha field HTML.
	 * @param array        $data Field data.
	 * @param stdClass     $form Form.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function render_field_hcaptcha( $html, array $data, stdClass $form ): string {
		$this->form_id = (int) $form->id;

		return $this->get_hcaptcha_wrapped();
	}

	/**
	 * Insert hCaptcha before the 'submit' button.
	 * Works for auto-added hCaptcha.
	 *
	 * @param array    $submit_button Form data and settings.
	 * @param stdClass $form          Form data and settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( array $submit_button, stdClass $form ): void {
		// Do not add if the form has its own hcaptcha.
		if ( $this->has_own_hcaptcha( $form ) ) {
			return;
		}

		$this->form_id = (int) $form->id;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_hcaptcha_wrapped();
	}

	/**
	 * Filter errors during form validation.
	 *
	 * @param array      $errors Errors.
	 * @param array      $data   Sanitized entry fields.
	 * @param FluentForm $form   Form data and settings.
	 * @param array      $fields Form fields.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( array $errors, array $data, FluentForm $form, array $fields ): array {
		if ( $this->is_login_form( $form ) ) {
			$email    = (string) ArrayHelper::get( $data, 'email' );
			$password = (string) ArrayHelper::get( $data, 'password' );
			$user     = get_user_by( 'email', $email );

			if ( $user && wp_check_password( $password, $user->user_pass, $user->ID ) ) {
				$this->login( $email, $user );
			} else {
				$this->login_failed( $email );
			}

			if ( ! $this->is_login_limit_exceeded() ) {
				return $errors;
			}

			wp_send_json(
				__( 'Login failed. Please reload the page.', 'hcaptcha-for-forms-and-more' ),
				423
			);

			// For testing purposes.
			return $errors;
		}

		remove_filter( 'pre_http_request', [ $this, 'pre_http_request' ] );

		$hcaptcha_response           = $data['h-captcha-response'] ?? '';
		$_POST['hcaptcha-widget-id'] = $data['hcaptcha-widget-id'] ?? '';
		$error_message               = hcaptcha_request_verify( $hcaptcha_response );

		if ( null !== $error_message ) {
			$errors['h-captcha-response'] = [ $error_message ];
		}

		return $errors;
	}

	/**
	 * Filter print hCaptcha scripts status and return true, so, always run hCaptcha scripts.
	 * Form can have own hCaptcha field, or we add hCaptcha automatically.
	 *
	 * @param bool|mixed $status Print scripts status.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		// Remove an API script by Fluent Forms, having the 'hcaptcha' handle.
		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

		return true;
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		global $wp_scripts;

		$fluent_forms_conversational_script = 'fluent_forms_conversational_form';

		// Proceed with conversational form only.
		if ( ! wp_script_is( $fluent_forms_conversational_script ) ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-fluentform$min.js",
			[ Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'id'  => 'fluent_forms_conversational_form',
				'url' => $wp_scripts->registered[ $fluent_forms_conversational_script ]->src,
			]
		);

		// Print localization data of conversational script.
		$wp_scripts->print_extra_script( $fluent_forms_conversational_script );

		// Remove a localization script. We will launch it from our HANDLE script on hCaptchaLoaded event.
		wp_dequeue_script( $fluent_forms_conversational_script );
		wp_deregister_script( $fluent_forms_conversational_script );

		$form = $this->get_hcaptcha();
		$form = str_replace(
			[
				'class="h-captcha"',
				'class="hcaptcha-widget-id"',
			],
			[
				'class="h-captcha-hidden" style="display: none;"',
				'class="h-captcha-hidden hcaptcha-widget-id"',
			],
			$form
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Enqueue script in admin.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		if ( ! $this->is_fluent_forms_admin_page() ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/admin-fluentform$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		$notice = HCaptcha::get_hcaptcha_plugin_notice();

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'noticeLabel'       => $notice['label'],
				'noticeDescription' => $notice['description'],
			]
		);

		wp_enqueue_style(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/admin-fluentform$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Whether we are on the Fluent Forms admin pages.
	 *
	 * @return bool
	 */
	protected function is_fluent_forms_admin_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			// @codeCoverageIgnoreStart
			return false;
			// @codeCoverageIgnoreEnd
		}

		$fluent_forms_admin_pages = [
			'fluent-forms_page_fluent_forms_settings',
		];

		return in_array( $screen->id, $fluent_forms_admin_pages, true );
	}

	/**
	 * Fluentform load form assets hook.
	 *
	 * @param stdClass|mixed $form Form.
	 *
	 * @return stdClass|mixed
	 */
	public function fluentform_rendering_form_filter( $form ) {
		if ( ! $form instanceof stdClass ) {
			return $form;
		}

		$this->form_id = (int) ( $form->id ?? 0 );

		return $form;
	}

	/**
	 * Do not allow auto-adding of hCaptcha by Fluent form plugin. We do it by ourselves.
	 *
	 * @return false
	 */
	public function fluentform_has_hcaptcha(): bool {
		add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10, 3 );

		return false;
	}

	/**
	 * Filter http request to block hCaptcha validation by Fluent Forms plugin.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 *
	 * @return false|array|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function pre_http_request( $response, array $parsed_args, string $url ) {
		$verify_url     = hcaptcha()->get_verify_url();
		$old_verify_url = str_replace( 'api.', '', $verify_url );
		$api_urls       = [
			$verify_url,
			$old_verify_url,
		];

		if ( ! in_array( $url, $api_urls, true ) ) {
			return $response;
		}

		return [
			'body'     => '{"success":true}',
			'response' =>
				[
					'code'    => 200,
					'message' => 'OK',
				],
		];
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
	.frm-fluent-form .h-captcha {
		line-height: 0;
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}


	/**
	 * Whether the form has its own hcaptcha set in admin.
	 *
	 * @param FluentForm|stdClass $form Form data and settings.
	 *
	 * @return bool
	 */
	protected function has_own_hcaptcha( $form ): bool {
		return $this->has_element( $form, 'hcaptcha' );
	}

	/**
	 * Get hCaptcha.
	 *
	 * @return string
	 */
	protected function get_hcaptcha(): string {
		$form = FluentForm::find( $this->form_id );

		if ( $this->is_login_form( $form ) && ! $this->is_login_limit_exceeded() ) {
			return '';
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		return HCaptcha::form( $args );
	}

	/**
	 * Whether the form is a login form.
	 *
	 * @param FluentForm|stdClass $form Form.
	 *
	 * @return bool
	 */
	private function is_login_form( $form ): bool {

		return (
			has_action( 'fluentform/before_insert_submission' ) &&
			$this->has_element( $form, 'input_email' ) &&
			$this->has_element( $form, 'input_password' )
		);
	}

	/**
	 * Whether the form has an element.
	 *
	 * @param FluentForm|stdClass $form         Form.
	 * @param string              $element_name Element name.
	 *
	 * @return bool
	 */
	private function has_element( $form, string $element_name ): bool {
		FormFieldsParser::resetData();

		return FormFieldsParser::hasElement( $form, $element_name );
	}

	/**
	 * Get hCaptcha wrapped as Fluent Forms field.
	 *
	 * @return string
	 */
	private function get_hcaptcha_wrapped(): string {
		ob_start();

		/* language=HTML */
		?>
		<div class="ff-el-group">
			<div class="ff-el-input--content">
				<div data-fluent_id="1" name="h-captcha-response">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->get_hcaptcha();
					?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}
