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
use Elementor\Settings;
use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Module as FormsModule;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Pages;
use HCaptcha\Helpers\Utils;
use ElementorPro\Modules\Forms\Classes\HCaptcha_Handler;
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
	 * Elementor Pro Handle.
	 */
	public const HANDLE = 'hcaptcha-elementor-pro';

	/**
	 * Admin handle.
	 */
	private const ADMIN_HANDLE = 'admin-elementor-pro';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Init this integration.
		add_action( 'elementor/init', [ $this, 'init' ], 20 );

		// Block native integration.
		add_filter( 'pre_option_elementor_pro_hcaptcha_site_key', '__return_empty_string' );
		add_filter( 'pre_option_elementor_pro_hcaptcha_secret_key', '__return_empty_string' );
		add_action( 'elementor/init', [ $this, 'block_native_integration' ], 20 );
	}

	/**
	 * Block native hCaptcha integration.
	 *
	 * @return void
	 */
	public function block_native_integration(): void {
		// Native integration handler. Created on elementor/init 10.
		if ( ! class_exists( HCaptcha_Handler::class, false ) ) {
			return;
		}

		$callback_pattern = '#^' . preg_quote( HCaptcha_Handler::class, '#' ) . '#';
		$actions          = [
			'elementor_pro/forms/field_types',
			'elementor/element/form/section_form_fields/after_section_end',
			'elementor_pro/forms/render_field/hcaptcha',
			'elementor_pro/forms/render/item',
			'wp_head',
			'wp_print_footer_scripts',
			'elementor/preview/enqueue_scripts',
			'elementor/editor/after_enqueue_scripts',
		];

		foreach ( $actions as $action ) {
			Utils::instance()->remove_action_regex( $callback_pattern, $action );
		}

		wp_deregister_script( 'elementor-hcaptcha-api' );
		wp_deregister_script( 'hcaptcha' );
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register or re-register hCaptcha component.
		FormsModule::instance()->add_component( self::FIELD_ID, $this );

		// Settings.
		if ( is_admin() ) {
			$callback_pattern = '#^' . preg_quote( HCaptcha_Handler::class . '::register_admin_fields', '#' ) . '#';

			Utils::instance()->replace_action_regex(
				$callback_pattern,
				[ $this, 'register_admin_fields' ],
				'elementor/admin/after_create_settings/' . Settings::PAGE_ID
			);
			add_filter( 'elementor_pro/editor/localize_settings', [ $this, 'localize_settings' ], 20 );
		}

		// Render field.
		if ( static::is_enabled() || is_admin() ) {
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
		}

		if ( static::is_enabled() ) {
			add_filter( 'elementor/frontend/the_content', [ $this, 'elementor_content' ] );
		}

		// General hCaptcha scripts and styles.
		add_action(
			'elementor/editor/init',
			static function () {
				// Block general hCaptcha scripts and styles on Elementor editor page.
				add_filter( 'hcap_print_hcaptcha_scripts', '__return_false' );
			}
		);

		if ( Pages::is_elementor_preview_page() ) {
			// Allow general hCaptcha scripts and styles on Elementor preview page (in iframe).
			add_filter( 'hcap_print_hcaptcha_scripts', '__return_true' );
		}

		// Elementor-related scripts and styles.
		if ( static::is_enabled() || is_admin() ) {
			$this->register_scripts();

			add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
			add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 9 );
			add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_preview_scripts' ] );
		}

		if ( is_admin() ) {
			add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'after_enqueue_scripts' ] );
		}

		// Validation.
		if ( static::is_enabled() ) {
			add_action( 'elementor_pro/forms/validation', [ $this, 'validation' ], 10, 2 );
		}
	}

	/**
	 * Register admin fields.
	 *
	 * @param Settings $settings Settings.
	 *
	 * @return void
	 */
	public function register_admin_fields( Settings $settings ): void {
		$notice = HCaptcha::get_hcaptcha_plugin_notice();

		$settings->add_section(
			Settings::TAB_INTEGRATIONS,
			static::get_hcaptcha_name(),
			[
				'callback' => function () use ( $notice ) {
					echo '<hr><h2>' . esc_html__( 'hCaptcha', 'hcaptcha-for-forms-and-more' ) . '</h2>';
					echo wp_kses_post(
						'<p>' .
						sprintf(
						/* translators: 1: hCaptcha link. */
							__( '%1$s is a free service that protects user privacy. It does not retain or sell personal data, whilst providing excellent protection against bots and abuse.', 'hcaptcha-for-forms-and-more' ),
							sprintf(
								'<a href="https://www.hcaptcha.com" target="_blank">%1$s</a>',
								__( 'hCaptcha', 'hcaptcha-for-forms-and-more' )
							)
						) .
						'</p>'
					);
					echo '<p><strong>' . wp_kses_post( $notice['label'] ) . '</strong></p>';
					echo '<p>' . wp_kses_post( $notice['description'] ) . '</p>';
				},
			]
		);
	}

	/**
	 * Enqueue elementor admin support script.
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
	 * Get a secret key.
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
	 * Whether a field is enabled.
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
	 * Register scripts.
	 */
	private function register_scripts(): void {
		$min = hcap_min_suffix();

		// Elementor Pro support script.
		wp_register_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-elementor-pro$min.js",
			[ 'jquery', Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Enqueue preview scripts.
	 *
	 * @return void
	 */
	public function enqueue_preview_scripts(): void {
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

		$result = API::verify_request( $hcaptcha_response );

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
		$hcaptcha_html = '<div class="elementor-field" id="form-field-' . esc_html( $item['custom_id'] ) . '">';

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

		return Utils::array_insert(
			$field_types,
			'recaptcha',
			[ self::FIELD_ID => __( 'hCaptcha', 'hcaptcha-for-forms-and-more' ) ]
		);
	}

	/**
	 * After section end.
	 *
	 * Fires after an Elementor section ends in the editor panel.
	 *
	 * The dynamic portions of the hook name, `$stack_name` and `$section_id`,
	 * refer to the section name and section ID, respectively.
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
	 * Filter field item.
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
	 * Add the hCaptcha Elementor Pro script to the footer.
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void {
		wp_enqueue_script( self::HANDLE );
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
	.elementor-field-type-hcaptcha .elementor-field {
		background: transparent !important;
	}

	.elementor-field-type-hcaptcha .h-captcha {
		margin-bottom: unset;
	}
';

		HCaptcha::css_display( $css );
	}
}
