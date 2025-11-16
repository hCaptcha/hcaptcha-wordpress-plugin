<?php
/**
 * Onboarding Wizard class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Helpers\Request;
use HCaptcha\Settings\PluginSettingsBase;

/**
 * Class OnboardingWizard.
 *
 * Guides new users through initial setup on admin settings pages.
 */
class OnboardingWizard {

	/**
	 * Script/style handle.
	 */
	public const HANDLE = 'hcaptcha-onboarding';

	/**
	 * Script localization object name.
	 */
	private const OBJECT = 'HCaptchaOnboardingObject';

	/**
	 * Ajax action to update a wizard step.
	 */
	public const UPDATE_ACTION = 'hcaptcha_onboarding_update';

	/**
	 * GET parameter to force a specific wizard step.
	 */
	private const STEP_PARAM = 'onb';

	/**
	 * Init class hooks.
	 */
	public function init(): void {
		// Handle direct step forcing via GET parameter early in the admin lifecycle.
		add_action( 'admin_init', [ $this, 'maybe_handle_direct_step' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::UPDATE_ACTION, [ $this, 'ajax_update' ] );
	}

	/**
	 * Determine if we should load the wizard scripts on the current screen.
	 */
	private function is_applicable_screen(): bool {
		// Only on our plugin settings pages (General, Integrations tabs/pages).
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		// Our settings pages share the base option page slug prefix.
		// Also rely on body classes set by settings_page(): form has class hcaptcha-general / hcaptcha-integrations.
		return false !== strpos( $screen->base, PluginSettingsBase::PREFIX );
	}

	/**
	 * Enqueue assets and localize config.
	 */
	public function admin_enqueue_scripts(): void {
		if ( ! $this->is_applicable_screen() ) {
			return;
		}

		$options = (array) get_option( PluginSettingsBase::OPTION_NAME, [] );
		$wizard  = $options['onboarding_wizard'] ?? '';

		// Start the wizard if the key is absent or its value is not 'completed'.
		$should_start = ( 'completed' !== $wizard );

		if ( ! $should_start ) {
			return;
		}

		$min = function_exists( 'hcap_min_suffix' ) ? hcap_min_suffix() : '';

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/onboarding$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/onboarding$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		$current_step = is_string( $wizard ) && preg_match( '/^step\s\d+$/', $wizard ) ? $wizard : 'step 1';

		// Selector map.
		$selectors = [
			'general'      => [
				'site_key'     => '#site_key',
				'secret_key'   => '#secret_key',
				'mode'         => 'select[name="hcaptcha_settings[mode]"], input[name="hcaptcha_settings[mode]"]',
				'check_config' => '#check_config',
				'force'        => '#force_1',
				'antispam'     => '.hcaptcha-section-antispam+table',
				'save'         => '#hcaptcha-options #submit',
			],
			'integrations' => [
				'integrations_list' => '.hcaptcha-enabled-section+h3+table tr:first-child',
				'save'              => '#hcaptcha-options #submit',
			],
		];

		// Steps text (i18n-ready). Updated: split former step 4 into two steps.
		$steps = [
			1 => __( 'Get your keys at hcaptcha.com', 'hcaptcha-for-forms-and-more' ),
			2 => __( 'Switch Mode to Live', 'hcaptcha-for-forms-and-more' ),
			3 => __( 'Enter keys, solve hCaptcha and Check Config', 'hcaptcha-for-forms-and-more' ),
			4 => __( 'Enable Force (recommended)', 'hcaptcha-for-forms-and-more' ),
			5 => __( 'Enable Anti-spam options (recommended)', 'hcaptcha-for-forms-and-more' ),
			6 => __( 'Save settings', 'hcaptcha-for-forms-and-more' ),
			7 => __( 'Enable hCaptcha for installed plugins', 'hcaptcha-for-forms-and-more' ),
			8 => __( 'Save settings', 'hcaptcha-for-forms-and-more' ),
		];

		// Detect which settings page we are on by looking at body class via section title echoing into form class.
		$page = Request::filter_input( INPUT_GET, 'page' );
		$page = strpos( $page, 'integrations' ) ? 'integrations' : 'general';

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'updateAction'    => self::UPDATE_ACTION,
				'updateNonce'     => wp_create_nonce( self::UPDATE_ACTION ),
				'page'            => $page,
				'currentStep'     => $current_step,
				'selectors'       => $selectors,
				'steps'           => $steps,
				'generalUrl'      => admin_url( 'admin.php?page=' . PluginSettingsBase::PREFIX ),
				'integrationsUrl' => admin_url( 'admin.php?page=' . PluginSettingsBase::PREFIX . '-integrations' ),
				'stepParam'       => self::STEP_PARAM,
				'iconAnimatedUrl' => constant( 'HCAPTCHA_URL' ) . '/assets/images/hcaptcha-icon-animated.svg',
				'i18n'            => [
					'done'         => __( 'Done', 'hcaptcha-for-forms-and-more' ),
					'close'        => __( 'Close', 'hcaptcha-for-forms-and-more' ),
					'steps'        => __( 'Onboarding Steps', 'hcaptcha-for-forms-and-more' ),
					'next'         => __( 'Next', 'hcaptcha-for-forms-and-more' ),
					// Welcome popup texts.
					'welcomeTitle' => __( 'Welcome to hCaptcha for WordPress', 'hcaptcha-for-forms-and-more' ),
					'welcomeBody'  => __( 'The hCaptcha plugin supports 60+ WordPress plugins and themes. This short tour will highlight the key settings so you can get up and running quickly.', 'hcaptcha-for-forms-and-more' ),
					'letsGo'       => __( "Let's Go!", 'hcaptcha-for-forms-and-more' ),
				],
			]
		);
	}

	/**
	 * If GET parameter wizard=x is provided, set the wizard step and redirect to the proper page.
	 * This allows manual restart/positioning of the onboarding for testing or support.
	 *
	 * @return void
	 */
	public function maybe_handle_direct_step(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Only admins (options managers) can manipulate the onboarding state.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Helper to restart the wizard from any step.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::STEP_PARAM ] ) ) {
			return;
		}

		// Accept a numeric step only: wizard=1..8.
		$num = (int) Request::filter_input( INPUT_GET, self::STEP_PARAM );

		if ( $num < 1 || $num > 8 ) {
			$num = 1;
		}

		// Persist step in the main settings option.
		$options                      = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$options['onboarding_wizard'] = 'step ' . $num;

		update_option( PluginSettingsBase::OPTION_NAME, $options );

		// Determine the target page by step.
		$page_slug = ( $num <= 6 )
			? PluginSettingsBase::PREFIX // General.
			: PluginSettingsBase::PREFIX . '-integrations'; // Integrations.

		// Build clean URL without the wizard param to avoid loops.
		$target = admin_url( 'admin.php?page=' . $page_slug );

		// Perform safe redirect and stop.
		wp_safe_redirect( $target );

		exit;
	}

	/**
	 * AJAX: update a wizard step in a hcaptcha_settings option.
	 */
	public function ajax_update(): void {
		// Security.
		if ( ! check_ajax_referer( self::UPDATE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}

		$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		// Accept values: 'step x' or 'completed'.
		if ( 'completed' !== $value && 1 !== preg_match( '/^step\s\d+$/', $value ) ) {
			wp_send_json_error( esc_html__( 'Bad value', 'hcaptcha-for-forms-and-more' ) );
		}

		$options                      = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$options['onboarding_wizard'] = $value;
		$updated                      = update_option( PluginSettingsBase::OPTION_NAME, $options );

		if ( ! $updated ) {
			wp_send_json_error( esc_html__( 'Could not update option', 'hcaptcha-for-forms-and-more' ) );
		}

		wp_send_json_success();
	}
}
