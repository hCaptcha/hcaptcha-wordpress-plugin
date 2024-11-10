<?php
/**
 * HCaptchaHandler class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedMethodInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\ElementorPro;

use Elementor\Controls_Stack;
use Elementor\Plugin;
use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Module;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Main;

/**
 * Class HCaptchaHandler.
 */
class HCaptchaHandler {

	/**
	 * Site Key option name.
	 */
	private const OPTION_NAME_SITE_KEY = 'site_key';

	/**
	 * Secret Key option name.
	 */
	private const OPTION_NAME_SECRET_KEY = 'secret_key';

	/**
	 * Theme option name.
	 */
	private const OPTION_NAME_THEME = 'theme';

	/**
	 * Size option name.
	 */
	private const OPTION_NAME_SIZE = 'size';

	/**
	 * Field ID.
	 */
	private const FIELD_ID = 'hcaptcha';

	/**
	 * Handle.
	 */
	public const  HANDLE = 'hcaptcha-elementor-pro';

	/**
	 * Admin handle.
	 */
	private const ADMIN_HANDLE = 'admin-elementor-pro';

	/**
	 * The hCaptcha handle.
	 */
	private const HCAPTCHA_HANDLE = 'hcaptcha';

	/**
	 * Main class instance.
	 *
	 * @var Main
	 */
	protected $main;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->main = hcaptcha();

		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'after_enqueue_scripts' ] );
		add_action( 'elementor/init', [ $this, 'init' ] );

		add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 9 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Enqueue elementor support script.
	 *
	 * @return void
	 */
	public function after_enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			HCAPTCHA_URL . "/assets/js/admin-elementor-pro$min.js",
			[ 'elementor-editor' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_scripts();

		add_action( 'elementor_pro/forms/register/action', [ $this, 'register_action' ] );

		add_filter( 'elementor_pro/forms/field_types', [ $this, 'add_field_type' ] );
		add_action(
			'elementor/element/form/section_form_fields/after_section_end',
			[ $this, 'modify_controls' ],
			10,
			2
		);
		add_action(
			'elementor_pro/forms/render_field/' . static::get_hcaptcha_name(),
			[ $this, 'render_field' ],
			10,
			3
		);
		add_filter( 'elementor_pro/forms/render/item', [ $this, 'filter_field_item' ] );
		add_filter( 'elementor/frontend/the_content', [ $this, 'elementor_content' ] );
		add_filter( 'elementor_pro/editor/localize_settings', [ $this, 'localize_settings' ] );

		if ( static::is_enabled() ) {
			add_action( 'elementor_pro/forms/validation', [ $this, 'validation' ], 10, 2 );
			add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}
	}

	/**
	 * Register action.
	 *
	 * @param Module $module Module.
	 *
	 * @return void
	 */
	public function register_action( Module $module ): void {
		$module->add_component( self::FIELD_ID, $this );
	}

	/**
	 * Get hCaptcha field name.
	 *
	 * @return string
	 */
	protected static function get_hcaptcha_name(): string {
		return self::FIELD_ID;
	}

	/**
	 * Get a site key.
	 *
	 * @return array|string
	 */
	public static function get_site_key() {
		return hcaptcha()->settings()->get( self::OPTION_NAME_SITE_KEY );
	}

	/**
	 * Get secret key.
	 *
	 * @return array|string
	 */
	public static function get_secret_key() {
		return hcaptcha()->settings()->get( self::OPTION_NAME_SECRET_KEY );
	}

	/**
	 * Get hCaptcha theme.
	 *
	 * @return array|string
	 */
	public static function get_hcaptcha_theme() {
		return hcaptcha()->settings()->get( self::OPTION_NAME_THEME );
	}

	/**
	 * Get hCaptcha size.
	 *
	 * @return array|string
	 */
	public static function get_hcaptcha_size() {
		return hcaptcha()->settings()->get( self::OPTION_NAME_SIZE );
	}

	/**
	 * Get a setup message.
	 *
	 * @return string
	 */
	public static function get_setup_message(): string {
		return __( 'To use hCaptcha, you need to add the Site and Secret keys.', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Is field enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return static::get_site_key() && static::get_secret_key();
	}

	/**
	 * Localize settings.
	 *
	 * @param array|mixed $settings Settings.
	 *
	 * @return array
	 */
	public function localize_settings( $settings ): array {
		$settings = (array) $settings;

		return array_replace_recursive(
			$settings,
			[
				'forms' => [
					static::get_hcaptcha_name() => [
						'enabled'        => static::is_enabled(),
						'site_key'       => static::get_site_key(),
						'hcaptcha_theme' => static::get_hcaptcha_theme(),
						'hcaptcha_size'  => static::get_hcaptcha_size(),
						'setup_message'  => static::get_setup_message(),
					],
				],
			]
		);
	}

	/**
	 * Get a script handle.
	 *
	 * @return string
	 */
	protected static function get_script_handle(): string {
		return 'elementor-' . static::get_hcaptcha_name() . '-api';
	}

	/**
	 * Register scripts.
	 */
	private function register_scripts(): void {
		$src = $this->main->get_api_src();
		$min = hcap_min_suffix();

		wp_register_script(
			static::get_script_handle(),
			$src,
			[],
			HCAPTCHA_VERSION,
			true
		);

		wp_register_script(
			self::HCAPTCHA_HANDLE,
			HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js',
			[],
			HCAPTCHA_VERSION,
			true
		);

		wp_register_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-elementor-pro$min.js",
			[ 'jquery', self::HCAPTCHA_HANDLE ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$this->main->print_inline_styles();
		wp_enqueue_script( static::get_script_handle() );
		wp_enqueue_script( self::HCAPTCHA_HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Field validation.
	 *
	 * @param Form_Record  $record       Record.
	 * @param Ajax_Handler $ajax_handler Ajax handler.
	 *
	 * @return void
	 */
	public function validation( Form_Record $record, Ajax_Handler $ajax_handler ): void {
		$fields = $record->get_field( [ 'type' => static::get_hcaptcha_name() ] );

		if ( empty( $fields ) ) {
			return;
		}

		$field = current( $fields );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = hcaptcha_request_verify( $hcaptcha_response );

		if ( null !== $result ) {
			$ajax_handler->add_error( $field['id'], $result );

			return;
		}

		// If success - remove the field form list (don't send it in emails etc.).
		$record->remove_field( $field['id'] );
	}

	/**
	 * Render field.
	 *
	 * @param array       $item       Item.
	 * @param int         $item_index Item index.
	 * @param Widget_Base $widget     Widget.
	 *
	 * @return void
	 */
	public function render_field( array $item, int $item_index, Widget_Base $widget ): void {
		$hcaptcha_html = '<div class="elementor-field" id="form-field-' . $item['custom_id'] . '">';

		$this->add_render_attributes( $item, $item_index, $widget );

		$data    = $widget->get_raw_data();
		$form_id = $data['settings']['form_id'] ?? 0;

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];

		$hcaptcha_html .=
			'<div class="elementor-hcaptcha">' .
			HCaptcha::form( $args ) .
			'</div>';

		$hcaptcha_html .= '</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $hcaptcha_html;
	}

	/**
	 * Add render attributes.
	 *
	 * @param array       $item       Item.
	 * @param int         $item_index Item index.
	 * @param Widget_Base $widget     Widget.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function add_render_attributes( array $item, int $item_index, Widget_Base $widget ): void {
		$widget->add_render_attribute(
			[
				static::get_hcaptcha_name() . $item_index => [
					'class'        => 'elementor-hcaptcha',
					'data-sitekey' => static::get_site_key(),
					'data-theme'   => static::get_hcaptcha_theme(),
					'data-size'    => static::get_hcaptcha_size(),
				],
			]
		);
	}

	/**
	 * Add a field type.
	 *
	 * @param array|mixed $field_types Field types.
	 *
	 * @return array
	 */
	public function add_field_type( $field_types ): array {
		$field_types = (array) $field_types;

		$field_types[ self::FIELD_ID ] = __( 'hCaptcha', 'hcaptcha-for-forms-and-more' );

		return $field_types;
	}

	/**
	 * After section end.
	 *
	 * Fires after Elementor section ends in the editor panel.
	 *
	 * The dynamic portions of the hook name, `$stack_name` and `$section_id`, refer to the section name and section
	 * ID, respectively.
	 *
	 * @param Controls_Stack $controls_stack The controls stack.
	 * @param array          $args           Section arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function modify_controls( Controls_Stack $controls_stack, array $args ): void {
		$control_id   = 'form_fields';
		$control_data = Plugin::$instance->controls_manager->get_control_from_stack(
			$controls_stack->get_unique_name(),
			$control_id
		);

		$term = [
			'name'     => 'field_type',
			'operator' => '!in',
			'value'    => [ self::FIELD_ID ],
		];

		$control_data['fields']['width']['conditions']['terms'][]    = $term;
		$control_data['fields']['required']['conditions']['terms'][] = $term;

		Plugin::$instance->controls_manager->update_control_in_stack(
			$controls_stack,
			$control_id,
			$control_data,
			[ 'recursive' => true ]
		);
	}

	/**
	 * Filter field item/
	 *
	 * @param array|mixed $item Item.
	 *
	 * @return array
	 */
	public function filter_field_item( $item ): array {
		$item = (array) $item;

		if ( isset( $item['field_type'] ) && static::get_hcaptcha_name() === $item['field_type'] ) {
			$item['field_label'] = false;
		}

		return $item;
	}

	/**
	 * Filter Elementor content.
	 * This filter is needed to support Elementor Element Caching feature.
	 * With Caching feature active, Elementor does not render the content of the form fields.
	 * Therefore, we have to analyze the content and check if the hCaptcha field is present
	 * to enqueue scripts in the Main class.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	public function elementor_content( $content ): string {
		$content = (string) $content;

		if ( ! hcaptcha()->form_shown && false !== strpos( $content, '<h-captcha' ) ) {
			hcaptcha()->form_shown = true;
		}

		return $content;
	}

	/**
	 * Add the hCaptcha Elementor Pro script to footer.
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-elementor-pro$min.js",
			[ 'jquery', Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	.elementor-field-type-hcaptcha .elementor-field {
		background: transparent !important;
	}

	.elementor-field-type-hcaptcha .h-captcha {
		margin-bottom: unset;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
