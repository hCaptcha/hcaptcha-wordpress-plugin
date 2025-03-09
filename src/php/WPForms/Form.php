<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedFunctionInspection */

namespace HCaptcha\WPForms;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	public const ACTION = 'hcaptcha_wpforms';

	/**
	 * Nonce name.
	 */
	public const NAME = 'hcaptcha_wpforms_nonce';

	/**
	 * Whether hCaptcha should be auto-added to any form.
	 *
	 * @var bool
	 */
	private $mode_auto = false;

	/**
	 * Whether hCaptcha can be embedded into form in the WPForms form editor.
	 * WPForms settings are blocked in this case.
	 *
	 * @var bool
	 */
	private $mode_embed = false;

	/**
	 * Form constructor.
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
		if ( ! function_exists( 'wpforms' ) ) {
			return;
		}

		$this->mode_auto  = hcaptcha()->settings()->is( 'wpforms_status', 'form' );
		$this->mode_embed =
			hcaptcha()->settings()->is( 'wpforms_status', 'embed' ) &&
			$this->is_wpforms_provider_hcaptcha();

		if ( ! $this->mode_auto && ! $this->mode_embed ) {
			return;
		}

		if ( $this->mode_embed ) {
			add_filter( 'wpforms_admin_settings_captcha_enqueues_disable', [ $this, 'wpforms_admin_settings_captcha_enqueues_disable' ] );
			add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'hcap_print_hcaptcha_scripts' ], 0 );
			add_filter( 'wpforms_settings_fields', [ $this, 'wpforms_settings_fields' ], 10, 2 );
		}

		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_action( 'wpforms_wp_footer', [ $this, 'block_assets_recaptcha' ], 0 );

		add_action( 'wpforms_frontend_output', [ $this, 'wpforms_frontend_output' ], 19, 5 );
		add_filter( 'wpforms_process_bypass_captcha', '__return_true' );
		add_action( 'wpforms_process', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Action that fires during form entry processing after initial field validation.
	 *
	 * @link         https://wpforms.com/developers/wpforms_process/
	 *
	 * @param array $fields    Sanitized entry field: values/properties.
	 * @param array $entry     Original $_POST global.
	 * @param array $form_data Form data and settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( array $fields, array $entry, array $form_data ): void {
		if ( ! $this->process_hcaptcha( $form_data ) ) {
			return;
		}

		$wpforms_error_message = '';

		if ( ! $this->mode_embed && $this->form_has_hcaptcha( $form_data ) ) {
			$this->use_wpforms_settings();

			$wpforms_error_message = wpforms_setting( 'hcaptcha-fail-msg' );
		}

		$error_message = hcaptcha_get_verify_message(
			self::NAME,
			self::ACTION
		);

		if ( null !== $error_message ) {
			wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] = $wpforms_error_message ?: $error_message;
		}
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
	div.wpforms-container-full .wpforms-form .h-captcha {
		position: relative;
		display: block;
		margin-bottom: 0;
		padding: 0;
		clear: both;
	}

	div.wpforms-container-full .wpforms-form .h-captcha[data-size="normal"] {
		width: 303px;
		height: 78px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="compact"] {
		width: 164px;
		height: 144px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="invisible"] {
		display: none;
	}

	div.wpforms-container-full .wpforms-form .h-captcha iframe {
		position: relative;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Filter hCaptcha settings' fields and disable them.
	 *
	 * @param array  $fields Fields.
	 * @param string $view   View name.
	 *
	 * @return array
	 */
	public function wpforms_settings_fields( array $fields, string $view ): array {
		if ( 'captcha' !== $view ) {
			return $fields;
		}

		$inputs      = [
			'hcaptcha-site-key',
			'hcaptcha-secret-key',
			'hcaptcha-fail-msg',
			'recaptcha-noconflict',
		];
		$search      = [
			'class="wpforms-setting-field',
			'<input ',
		];
		$replace     = [
			'style="opacity: 0.4;" ' . $search[0],
			$search[1] . 'disabled ',
		];
		$notice      = HCaptcha::get_hcaptcha_plugin_notice();
		$label       = $notice['label'];
		$description = $notice['description'];

		foreach ( $inputs as $input ) {
			if ( ! isset( $fields[ $input ] ) ) {
				continue;
			}

			$fields[ $input ] = str_replace( $search, $replace, $fields[ $input ] );
		}

		if ( isset( $fields['hcaptcha-heading'] ) ) {
			$notice_content = '
<div
		id="wpforms-setting-row-hcaptcha-heading"
		class="wpforms-setting-row wpforms-setting-row-content wpforms-clear section-heading specific-note">
	<span class="wpforms-setting-field">
		<div class="wpforms-specific-note-wrap">
			<div class="wpforms-specific-note-lightbulb">
				<svg viewBox="0 0 14 20">
					<path d="M3.75 17.97c0 .12 0 .23.08.35l.97 1.4c.12.2.32.28.51.28H8.4c.2 0 .39-.08.5-.27l.98-1.41c.04-.12.08-.23.08-.35v-1.72H3.75v1.72Zm3.13-5.47c.66 0 1.25-.55 1.25-1.25 0-.66-.6-1.25-1.26-1.25-.7 0-1.25.59-1.25 1.25 0 .7.55 1.25 1.25 1.25Zm0-12.5A6.83 6.83 0 0 0 0 6.88c0 1.75.63 3.32 1.68 4.53.66.74 1.68 2.3 2.03 3.59H5.6c0-.16 0-.35-.08-.55-.2-.7-.86-2.5-2.42-4.25a5.19 5.19 0 0 1-1.21-3.32c-.04-2.86 2.3-5 5-5 2.73 0 5 2.26 5 5 0 1.2-.47 2.38-1.26 3.32a11.72 11.72 0 0 0-2.42 4.25c-.07.2-.07.35-.07.55H10a10.56 10.56 0 0 1 2.03-3.6A6.85 6.85 0 0 0 6.88 0Zm-.4 8.75h.75c.3 0 .58-.23.62-.55l.5-3.75a.66.66 0 0 0-.62-.7H5.98a.66.66 0 0 0-.63.7l.5 3.75c.05.32.32.55.63.55Z"></path>
				</svg>
			</div>
			<div class="wpforms-specific-note-content">
				<p><strong>' . $label . '</strong></p>
				<p>' . $description . '</p>
			</div>
		</div>
	</span>
</div>
';

			$fields['hcaptcha-heading'] .= $notice_content;
		}

		if ( isset( $fields['captcha-preview'] ) ) {
			$fields['captcha-preview'] = preg_replace(
				'#<div class="wpforms-captcha wpforms-captcha-hcaptcha".+?</div>#',
				HCaptcha::form(),
				$fields['captcha-preview']
			);
		}

		return $fields;
	}

	/**
	 * Filter whether to print hCaptcha scripts.
	 *
	 * @param bool|mixed $status Status.
	 *
	 * @return bool
	 */
	public function hcap_print_hcaptcha_scripts( $status ): bool {
		return $this->is_wpforms_hcaptcha_settings_page() || $status;
	}

	/**
	 * Disable enqueuing wpforms hCaptcha.
	 *
	 * @param bool|mixed $status Status.
	 *
	 * @return bool
	 */
	public function wpforms_admin_settings_captcha_enqueues_disable( $status ): bool {
		return $this->is_wpforms_hcaptcha_settings_page() || $status;
	}

	/**
	 * Block recaptcha assets on frontend.
	 *
	 * @return void
	 */
	public function block_assets_recaptcha(): void {
		if ( ! $this->is_wpforms_provider_hcaptcha() ) {
			return;
		}

		$captcha = wpforms()->get( 'captcha' );

		if ( ! $captcha ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		remove_action( 'wpforms_wp_footer', [ $captcha, 'assets_recaptcha' ] );
	}

	/**
	 * Output embedded hCaptcha.
	 *
	 * @param array|mixed $form_data   Form data and settings.
	 * @param null        $deprecated  Deprecated in v1.3.7, previously was $form object.
	 * @param bool        $title       Whether to display form title.
	 * @param bool        $description Whether to display form description.
	 * @param array       $errors      List of all errors filled in WPForms_Process::process().
	 *
	 * @noinspection HtmlUnknownAttribute
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function wpforms_frontend_output( $form_data, $deprecated, bool $title, bool $description, array $errors ): void {
		$form_data = (array) $form_data;

		if ( ! $this->process_hcaptcha( $form_data ) ) {
			return;
		}

		if ( $this->mode_embed ) {
			$captcha = wpforms()->get( 'captcha' );

			if ( ! $captcha ) {
				// @codeCoverageIgnoreStart
				return;
				// @codeCoverageIgnoreEnd
			}

			// Block native WPForms hCaptcha output.
			remove_action( 'wpforms_frontend_output', [ $captcha, 'recaptcha' ], 20 );

			$this->show_hcaptcha( $form_data );

			return;
		}

		if ( $this->mode_auto ) {
			$this->show_hcaptcha( $form_data );
		}
	}

	/**
	 * Show hCaptcha.
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	private function show_hcaptcha( array $form_data ): void {
		$frontend_obj = wpforms()->get( 'frontend' );

		if ( ! $frontend_obj ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => (int) $form_data['id'],
			],
		];

		if ( ! $this->mode_embed && $this->form_has_hcaptcha( $form_data ) ) {
			$this->use_wpforms_settings();
		}

		printf(
			'<div class="wpforms-recaptcha-container wpforms-is-hcaptcha" %s>',
			$frontend_obj->pages ? 'style="display:none;"' : ''
		);

		HCaptcha::form_display( $args );

		echo '</div>';
	}

	/**
	 * Whether form has hCaptcha.
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function form_has_hcaptcha( array $form_data ): bool {
		$captcha_settings = wpforms_get_captcha_settings();
		$provider         = $captcha_settings['provider'] ?? '';

		if ( 'hcaptcha' !== $provider ) {
			return false;
		}

		// Check that the CAPTCHA is configured for the specific form.
		$recaptcha = $form_data['settings']['recaptcha'] ?? '';

		return '1' === $recaptcha;
	}

	/**
	 * Check if the current page is wpforms captcha settings page and the current provider is hCaptcha.
	 *
	 * @return bool
	 */
	private function is_wpforms_hcaptcha_settings_page(): bool {
		if ( ! function_exists( 'get_current_screen' ) || ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();
		$id     = $screen->id ?? '';

		if ( 'wpforms_page_wpforms-settings' !== $id ) {
			return false;
		}

		return $this->is_wpforms_provider_hcaptcha();
	}

	/**
	 * Check if the current captcha provider is hCaptcha.
	 *
	 * @return bool
	 */
	private function is_wpforms_provider_hcaptcha(): bool {
		$captcha_settings = wpforms_get_captcha_settings();
		$provider         = $captcha_settings['provider'] ?? '';

		return 'hcaptcha' === $provider;
	}

	/**
	 * Process hCaptcha in the form.
	 * Returns true if form has hCaptcha or hCaptcha will be auto-added.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return bool
	 */
	protected function process_hcaptcha( array $form_data ): bool {
		return (
			$this->mode_auto ||
			( $this->mode_embed && $this->form_has_hcaptcha( $form_data ) )
		);
	}

	/**
	 * Use WPForms settings for hCaptcha.
	 *
	 * @return void
	 */
	private function use_wpforms_settings(): void {
		$captcha_settings = wpforms_get_captcha_settings();
		$site_key         = $captcha_settings['site_key'] ?? '';
		$secret_key       = $captcha_settings['secret_key'] ?? '';

		add_filter(
			'hcap_site_key',
			static function () use ( $site_key ) {
				return $site_key;
			}
		);

		add_filter(
			'hcap_secret_key',
			static function () use ( $secret_key ) {
				return $secret_key;
			}
		);

		add_filter(
			'hcap_theme',
			static function () {
				return 'light';
			}
		);
	}
}
