<?php
/**
 * Onboarding Wizard class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Helpers\Request;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\Settings;

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
	private const STEP_PARAM = 'onboarding';

	/**
	 * Option name for the onboarding wizard state.
	 */
	public const OPTION_NAME = 'onboarding_wizard';

	/**
	 * Current admin tab.
	 *
	 * @var PluginSettingsBase
	 */
	private PluginSettingsBase $tab;

	/**
	 * General tab instance.
	 *
	 * @var PluginSettingsBase
	 */
	private PluginSettingsBase $general_tab;

	/**
	 * Integrations tab instance.
	 *
	 * @var PluginSettingsBase
	 */
	private PluginSettingsBase $integrations_tab;

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * OnboardingWizard constructor.
	 *
	 * @param PluginSettingsBase $tab Current admin tab.
	 */
	public function __construct( PluginSettingsBase $tab ) {
		$this->tab = $tab;

		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->settings         = hcaptcha()->settings();
		$this->general_tab      = $this->settings->get_tab( General::class );
		$this->integrations_tab = $this->settings->get_tab( Integrations::class );

		$this->init_wizard_state();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'init' ] );

		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_' . self::UPDATE_ACTION, [ $this, 'ajax_update' ] );

			return;
		}

		add_action( 'current_screen', [ $this, 'maybe_handle_direct_step' ], 30 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * If GET parameter wizard=x is provided, set the wizard step and redirect to the proper page.
	 * This allows manual restart/positioning of the onboarding for testing or support.
	 *
	 * @return void
	 */
	public function maybe_handle_direct_step(): void {
		// Helper to restart the wizard from any step.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::STEP_PARAM ] ) ) {
			return;
		}

		// Accept a numeric step only: onb=1..8.
		$num = (int) Request::filter_input( INPUT_GET, self::STEP_PARAM );

		if ( $num < 1 || $num > 8 ) {
			$num = 1;
		}

		// Persist a step in the main settings option.
		$this->set_wizard_state( 'step ' . $num );

		// Determine the target page by step.
		$url = ( $num <= 6 )
			? $this->tab->tab_url( $this->general_tab )
			: $this->tab->tab_url( $this->integrations_tab );

		// Perform safe redirect and stop.
		wp_safe_redirect( $url );

		exit;
	}

	/**
	 * Enqueue assets and localize config.
	 */
	public function admin_enqueue_scripts(): void {
		if ( ! $this->tab->is_options_screen() ) {
			return;
		}

		$wizard = $this->get_wizard_state();

		// Start the wizard if the key is absent or its value is not 'completed'.
		if ( 'completed' === $wizard ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/onboarding$min.js",
			[ 'jquery', $this->tab::HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/onboarding$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		$current_step = preg_match( '/^step\s\d+$/', $wizard ) ? $wizard : 'step 1';

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
		$page = $this->tab === $this->integrations_tab ? 'integrations' : 'general';

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
				'videoUrl'        => 'https://youtu.be/khKYehgr8t0',
				'ratingUrl'       => 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/#new-post',
				'i18n'            => [
					'done'         => __( 'Done', 'hcaptcha-for-forms-and-more' ),
					'close'        => __( 'Close', 'hcaptcha-for-forms-and-more' ),
					'steps'        => __( 'Onboarding Steps', 'hcaptcha-for-forms-and-more' ),
					'next'         => __( 'Next', 'hcaptcha-for-forms-and-more' ),
					'welcomeTitle' => __( 'Welcome to hCaptcha for WordPress', 'hcaptcha-for-forms-and-more' ),
					'welcomeBody'  => __( 'The hCaptcha plugin supports 60+ WordPress plugins and themes. This short tour will highlight the key settings so you can get up and running quickly.', 'hcaptcha-for-forms-and-more' ),
					'letsGo'       => __( "Let's Go!", 'hcaptcha-for-forms-and-more' ),
					'videoCta'     => __( 'Watch a quick setup video', 'hcaptcha-for-forms-and-more' ),
					'videoTitle'   => __( 'Quick Setup Video', 'hcaptcha-for-forms-and-more' ),
					'ratingTitle'  => __( 'Congrats — setup complete!', 'hcaptcha-for-forms-and-more' ),
					'ratingBody'   => __( 'You’ve completed the onboarding wizard. If hCaptcha helps you, please consider leaving a 5‑star review on WordPress.org — your support motivates us to build even more great features. Thank you!', 'hcaptcha-for-forms-and-more' ),
					'ratingCta'    => __( 'Rate hCaptcha on WordPress.org', 'hcaptcha-for-forms-and-more' ),
				],
			]
		);
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

		$this->set_wizard_state( $value );

		wp_send_json_success();
	}

	/**
	 * Init the onboarding wizard state to 'completed' if keys are set.
	 *
	 * @return void
	 */
	private function init_wizard_state(): void {
		$wizard = $this->get_wizard_state();

		if ( $wizard ) {
			return;
		}

		// Get the site and secret key here directly from options.
		$site_key   = $this->settings->get( 'site_key' );
		$secret_key = $this->settings->get( 'secret_key' );

		if ( $site_key && $secret_key ) {
			// Do not run wizard if user has already made initial settings.
			$this->set_wizard_state( 'completed' );
		}
	}

	/**
	 * Get the current wizard state from the database.
	 *
	 * @return string
	 */
	private function get_wizard_state(): string {
		return $this->settings->get( self::OPTION_NAME );
	}

	/**
	 * Set the wizard state in the database.
	 *
	 * @param string $state Wizard state.
	 *
	 * @return void
	 */
	private function set_wizard_state( string $state ): void {
		$this->tab->update_option( self::OPTION_NAME, $state );
	}
}
