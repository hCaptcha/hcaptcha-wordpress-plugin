<?php
/**
 * HCaptchaHandler class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ElementorPro;

use Elementor\Controls_Stack;
use Elementor\Plugin;
use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Module;
use HCaptcha\Main;

/**
 * Class HCaptchaHandler.
 */
class HCaptchaHandler {

	const OPTION_NAME_SITE_KEY   = 'hcaptcha_api_key';
	const OPTION_NAME_SECRET_KEY = 'hcaptcha_secret_key';
	const OPTION_NAME_THEME      = 'hcaptcha_theme';
	const OPTION_NAME_SIZE       = 'hcaptcha_size';

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
		global $hcaptcha_wordpress_plugin;

		$this->main = $hcaptcha_wordpress_plugin;

		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'after_enqueue_scripts' ] );
		add_action( 'elementor/init', [ $this, 'init' ] );
	}

	/**
	 * Enqueue elementor support script.
	 */
	public function after_enqueue_scripts() {
		wp_enqueue_script(
			'hcaptcha-elementor-pro',
			HCAPTCHA_URL . '/assets/js/hcaptcha-elementor-pro.js',
			[ 'elementor-editor' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add hooks.
	 */
	public function init() {
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
	 */
	public function register_action( $module ) {
		$module->add_component( 'hcaptcha', $this );
	}

	/**
	 * Get hCaptcha field name.
	 *
	 * @return string
	 */
	protected static function get_hcaptcha_name() {
		return 'hcaptcha';
	}

	/**
	 * Get site key.
	 *
	 * @return false|mixed|void
	 */
	public static function get_site_key() {
		return get_option( self::OPTION_NAME_SITE_KEY );
	}

	/**
	 * Get secret key.
	 *
	 * @return false|mixed|void
	 */
	public static function get_secret_key() {
		return get_option( self::OPTION_NAME_SECRET_KEY );
	}

	/**
	 * Get hCaptcha theme.
	 *
	 * @return false|mixed|void
	 */
	public static function get_hcaptcha_theme() {
		return get_option( self::OPTION_NAME_THEME );
	}

	/**
	 * Get hCaptcha size.
	 *
	 * @return false|mixed|void
	 */
	public static function get_hcaptcha_size() {
		return get_option( self::OPTION_NAME_SIZE );
	}

	/**
	 * Get setup message.
	 *
	 * @return mixed|string|void
	 */
	public static function get_setup_message() {
		return __( 'To use hCaptcha, you need to add the API Key and Secret Key.', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Is field enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return static::get_site_key() && static::get_secret_key();
	}

	/**
	 * Localize settings.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array|mixed
	 */
	public function localize_settings( $settings ) {
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
	 * Get script handle.
	 *
	 * @return string
	 */
	protected static function get_script_handle() {
		return 'elementor-' . static::get_hcaptcha_name() . '-api';
	}

	/**
	 * Register scripts.
	 */
	private function register_scripts() {
		$src = $this->main->get_api_src();

		wp_register_script(
			static::get_script_handle(),
			$src,
			[],
			HCAPTCHA_VERSION,
			true
		);

		wp_register_script(
			'hcaptcha',
			HCAPTCHA_URL . '/assets/js/hcaptcha.js',
			[],
			HCAPTCHA_VERSION,
			true
		);

		wp_register_script(
			'hcaptcha-elementor-pro-frontend',
			HCAPTCHA_URL . '/assets/js/hcaptcha-elementor-pro-frontend.js',
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		$this->main->print_inline_styles();
		wp_enqueue_script( static::get_script_handle() );
		wp_enqueue_script( 'hcaptcha' );
		wp_enqueue_script( 'hcaptcha-elementor-pro-frontend' );
	}

	/**
	 * Field validation.
	 *
	 * @param Form_Record  $record       Record.
	 * @param Ajax_Handler $ajax_handler Ajax handler.
	 */
	public function validation( $record, $ajax_handler ) {
		$fields = $record->get_field( [ 'type' => static::get_hcaptcha_name() ] );

		if ( empty( $fields ) ) {
			return;
		}

		$field = current( $fields );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_STRING ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = hcaptcha_request_verify( $hcaptcha_response );

		if ( 'success' !== $result ) {
			$messages = [
				'empty' => __( 'Please complete the captcha.', 'hcaptcha-for-forms-and-more' ),
				'fail'  => __( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ),
			];
			$ajax_handler->add_error( $field['id'], $messages[ $result ] );

			return;
		}

		// If success - remove the field form list (don't send it in emails etc. ).
		$record->remove_field( $field['id'] );
	}

	/**
	 * Render field.
	 *
	 * @param array       $item       Item.
	 * @param int         $item_index Item index.
	 * @param Widget_Base $widget     Widget.
	 */
	public function render_field( $item, $item_index, $widget ) {
		$hcaptcha_html = '<div class="elementor-field" id="form-field-' . $item['custom_id'] . '">';

		$this->add_render_attributes( $item, $item_index, $widget );

		$hcaptcha_html .=
			'<div class="elementor-hcaptcha">' .
			hcap_form() .
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
	protected function add_render_attributes( $item, $item_index, $widget ) {
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
	 * Add filed type.
	 *
	 * @param array $field_types Field types.
	 *
	 * @return array
	 */
	public function add_field_type( $field_types ) {
		$field_types['hcaptcha'] = __( 'hCaptcha', 'elementor-pro' );

		return $field_types;
	}

	/**
	 * After section end.
	 *
	 * Fires after Elementor section ends in the editor panel.
	 *
	 * The dynamic portions of the hook name, `$stack_name` and `$section_id`, refers to the section name and section
	 * ID, respectively.
	 *
	 * @param Controls_Stack $controls_stack The controls stack.
	 * @param array          $args           Section arguments.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function modify_controls( $controls_stack, $args ) {
		$control_id   = 'form_fields';
		$control_data = Plugin::$instance->controls_manager->get_control_from_stack(
			$controls_stack->get_unique_name(),
			$control_id
		);

		$term = [
			'name'     => 'field_type',
			'operator' => '!in',
			'value'    => [ 'hcaptcha' ],
		];

		$control_data['fields']['width']['conditions']['terms'][]        = $term;
		$control_data['fields']['width_tablet']['conditions']['terms'][] = $term;
		$control_data['fields']['width_mobile']['conditions']['terms'][] = $term;
		$control_data['fields']['required']['conditions']['terms'][]     = $term;

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
	 * @param array $item Item.
	 *
	 * @return array
	 */
	public function filter_field_item( $item ) {
		if ( static::get_hcaptcha_name() === $item['field_type'] ) {
			$item['field_label'] = false;
		}

		return $item;
	}
}
