<?php
/**
 * OnboardingWizardTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use Exception;
use HCaptcha\Admin\OnboardingWizard;
use HCaptcha\Settings\AntiSpamPage;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class OnboardingWizardTest
 *
 * @group admin
 * @group onboarding-wizard
 */
class OnboardingWizardTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset(
			$_GET[ OnboardingWizard::STEP_PARAM ],
			$_GET[ OnboardingWizard::NONCE_PARAM ],
			$_POST['nonce'],
			$_POST['value'],
			$_REQUEST['nonce'],
			$_REQUEST['action'],
			$GLOBALS['current_screen']
		);

		wp_set_current_user( 0 );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		wp_dequeue_script( OnboardingWizard::HANDLE );
		wp_deregister_script( OnboardingWizard::HANDLE );
		wp_dequeue_style( OnboardingWizard::HANDLE );
		wp_deregister_style( OnboardingWizard::HANDLE );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks() outside ajax.
	 */
	public function test_init_hooks(): void {
		$subject = $this->make_wizard( $this->get_general_tab() );

		self::assertSame( 10, has_action( 'plugins_loaded', [ $subject, 'init' ] ) );
		self::assertSame( 30, has_action( 'current_screen', [ $subject, 'maybe_handle_direct_step' ] ) );
		self::assertSame( 10, has_action( 'admin_enqueue_scripts', [ $subject, 'admin_enqueue_scripts' ] ) );
		self::assertFalse( has_action( 'wp_ajax_' . OnboardingWizard::UPDATE_ACTION, [ $subject, 'ajax_update' ] ) );
	}

	/**
	 * Test constructor and init_hooks() during ajax.
	 */
	public function test_init_hooks_when_doing_ajax(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = $this->make_wizard( $this->get_general_tab() );

		self::assertSame( 10, has_action( 'plugins_loaded', [ $subject, 'init' ] ) );
		self::assertSame( 10, has_action( 'wp_ajax_' . OnboardingWizard::UPDATE_ACTION, [ $subject, 'ajax_update' ] ) );
		self::assertFalse( has_action( 'current_screen', [ $subject, 'maybe_handle_direct_step' ] ) );
		self::assertFalse( has_action( 'admin_enqueue_scripts', [ $subject, 'admin_enqueue_scripts' ] ) );
	}

	/**
	 * Test init() stores settings tabs and completes the wizard when keys exist.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_init_completes_wizard_when_keys_exist(): void {
		$this->refresh_settings(
			[
				'site_key'   => 'site key',
				'secret_key' => 'secret key',
			]
		);

		$subject = $this->make_wizard( $this->get_general_tab() );

		$subject->init();

		$option = get_option( PluginSettingsBase::OPTION_NAME );

		self::assertSame( 'completed', $option[ OnboardingWizard::OPTION_NAME ] );
		self::assertInstanceOf( General::class, $this->get_wizard_property( $subject, 'general_tab' ) );
		self::assertInstanceOf( AntiSpamPage::class, $this->get_wizard_property( $subject, 'antispam_tab' ) );
		self::assertInstanceOf( Integrations::class, $this->get_wizard_property( $subject, 'integrations_tab' ) );
	}

	/**
	 * Test init() preserves an existing wizard state.
	 */
	public function test_init_preserves_existing_wizard_state(): void {
		$this->refresh_settings(
			[
				OnboardingWizard::OPTION_NAME => 'step 3',
				'site_key'                    => 'site key',
				'secret_key'                  => 'secret key',
			]
		);

		$subject = $this->make_wizard( $this->get_general_tab() );

		$subject->init();

		$option = get_option( PluginSettingsBase::OPTION_NAME );

		self::assertSame( 'step 3', $option[ OnboardingWizard::OPTION_NAME ] );
	}

	/**
	 * Test maybe_handle_direct_step() exits when no step is provided.
	 *
	 * @noinspection PhpPossiblePolymorphicInvocationInspection
	 */
	public function test_maybe_handle_direct_step_without_step(): void {
		$subject = $this->make_initialized_wizard( $this->get_general_tab() );

		$subject->maybe_handle_direct_step();

		self::assertSame( [], $subject->redirects );
		self::assertArrayNotHasKey( OnboardingWizard::OPTION_NAME, (array) get_option( PluginSettingsBase::OPTION_NAME ) );
	}

	/**
	 * Test maybe_handle_direct_step() with a bad nonce.
	 *
	 * @noinspection PhpPossiblePolymorphicInvocationInspection
	 */
	public function test_maybe_handle_direct_step_with_bad_nonce(): void {
		$this->set_admin_user();

		$_GET[ OnboardingWizard::STEP_PARAM ]  = '3';
		$_GET[ OnboardingWizard::NONCE_PARAM ] = 'bad_nonce';

		$subject = $this->make_initialized_wizard( $this->get_general_tab() );

		$subject->maybe_handle_direct_step();

		self::assertSame( [], $subject->redirects );
		self::assertArrayNotHasKey( OnboardingWizard::OPTION_NAME, (array) get_option( PluginSettingsBase::OPTION_NAME ) );
	}

	/**
	 * Test maybe_handle_direct_step().
	 *
	 * @param string $step_param     Step request parameter.
	 * @param string $expected_state Expected saved state.
	 * @param string $target_tab     Target tab class name.
	 *
	 * @dataProvider dp_test_maybe_handle_direct_step
	 * @noinspection PhpPossiblePolymorphicInvocationInspection
	 */
	public function test_maybe_handle_direct_step( string $step_param, string $expected_state, string $target_tab ): void {
		$this->set_admin_user();

		$_GET[ OnboardingWizard::STEP_PARAM ]  = $step_param;
		$_GET[ OnboardingWizard::NONCE_PARAM ] = wp_create_nonce( OnboardingWizard::STEP_ACTION );

		$general      = $this->get_general_tab();
		$subject      = $this->make_initialized_wizard( $general );
		$target       = hcaptcha()->settings()->get_tab( $target_tab );
		$expected_url = $general->tab_url( $target );

		$subject->maybe_handle_direct_step();

		$option = get_option( PluginSettingsBase::OPTION_NAME );

		self::assertSame( $expected_state, $option[ OnboardingWizard::OPTION_NAME ] );
		self::assertSame( [ $expected_url ], $subject->redirects );
	}

	/**
	 * Data provider for test_maybe_handle_direct_step().
	 *
	 * @return array
	 */
	public function dp_test_maybe_handle_direct_step(): array {
		return [
			'general step'      => [ '3', 'step 3', General::class ],
			'anti-spam step'    => [ '6', 'step 6', AntiSpamPage::class ],
			'integrations step' => [ '8', 'step 8', Integrations::class ],
			'final step'        => [ '9', 'step 9', Integrations::class ],
			'out of range step' => [ '99', 'step 1', General::class ],
		];
	}

	/**
	 * Test verify_request().
	 */
	public function test_verify_request(): void {
		$_GET[ OnboardingWizard::NONCE_PARAM ] = wp_create_nonce( OnboardingWizard::STEP_ACTION );

		wp_set_current_user( 0 );

		self::assertFalse( OnboardingWizard::verify_request( OnboardingWizard::STEP_ACTION ) );

		$this->set_admin_user();

		$_GET[ OnboardingWizard::NONCE_PARAM ] = [ 'bad' ];

		self::assertFalse( OnboardingWizard::verify_request( OnboardingWizard::STEP_ACTION ) );

		$_GET[ OnboardingWizard::NONCE_PARAM ] = 'bad_nonce';

		self::assertFalse( OnboardingWizard::verify_request( OnboardingWizard::STEP_ACTION ) );

		$_GET[ OnboardingWizard::NONCE_PARAM ] = wp_create_nonce( OnboardingWizard::STEP_ACTION );

		self::assertTrue( OnboardingWizard::verify_request( OnboardingWizard::STEP_ACTION ) );
	}

	/**
	 * Test admin_enqueue_scripts() when not on an option screen.
	 */
	public function test_admin_enqueue_scripts_when_not_options_screen(): void {
		$subject = $this->make_initialized_wizard( $this->get_general_tab() );

		$subject->admin_enqueue_scripts();

		self::assertFalse( wp_script_is( OnboardingWizard::HANDLE, 'registered' ) );
	}

	/**
	 * Test admin_enqueue_scripts() when the wizard is completed.
	 */
	public function test_admin_enqueue_scripts_when_completed(): void {
		$this->refresh_settings( [ OnboardingWizard::OPTION_NAME => 'completed' ] );
		set_current_screen( 'settings_page_hcaptcha' );

		$subject = $this->make_initialized_wizard( $this->get_general_tab() );

		$subject->admin_enqueue_scripts();

		self::assertFalse( wp_script_is( OnboardingWizard::HANDLE, 'registered' ) );
	}

	/**
	 * A fresh installation shows settings until the wizard is explicitly started.
	 */
	public function test_admin_enqueue_scripts_without_explicit_start(): void {
		$this->refresh_settings();
		set_current_screen( 'settings_page_hcaptcha' );

		$subject = $this->make_initialized_wizard( $this->get_general_tab() );
		$subject->admin_enqueue_scripts();

		self::assertFalse( wp_script_is( OnboardingWizard::HANDLE, 'registered' ) );
	}

	/**
	 * Test admin_enqueue_scripts().
	 */
	public function test_admin_enqueue_scripts(): void {
		$this->refresh_settings( [ OnboardingWizard::OPTION_NAME => 'step 5' ] );
		set_current_screen( 'settings_page_hcaptcha' );

		$general = $this->get_general_tab();
		$subject = $this->make_initialized_wizard( $general );

		$subject->admin_enqueue_scripts();

		self::assertTrue( wp_script_is( OnboardingWizard::HANDLE, 'registered' ) );

		$script = wp_scripts()->registered[ OnboardingWizard::HANDLE ];

		self::assertSame( HCAPTCHA_URL . '/assets/js/onboarding-wizard.min.js', $script->src );
		self::assertSame( [ 'jquery', $general::HANDLE ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertStringContainsString( 'var HCaptchaOnboardingObject = ', $script->extra['data'] );
		self::assertStringContainsString( '"page":"general"', $script->extra['data'] );
		self::assertStringContainsString( '"currentStep":"step 5"', $script->extra['data'] );
		self::assertStringContainsString( '"signupUrl":"https:', $script->extra['data'] );
		self::assertStringContainsString( 'utm_campaign=sk', $script->extra['data'] );

		self::assertTrue( wp_style_is( OnboardingWizard::HANDLE, 'registered' ) );

		$style = wp_styles()->registered[ OnboardingWizard::HANDLE ];

		self::assertSame( HCAPTCHA_URL . '/assets/css/onboarding-wizard.min.css', $style->src );
		self::assertSame( [], $style->deps );
		self::assertSame( HCAPTCHA_VERSION, $style->ver );
	}

	/**
	 * The localized wizard identifies each non-general settings tab.
	 */
	public function test_admin_enqueue_scripts_on_other_tabs(): void {
		$this->refresh_settings( [ OnboardingWizard::OPTION_NAME => 'step 8' ] );
		foreach ( [
			Integrations::class => 'integrations',
			AntiSpamPage::class => 'antispam',
		] as $class => $page ) {
			$tab = hcaptcha()->settings()->get_tab( $class );
			self::assertInstanceOf( $class, $tab );
			$option_page = $this->set_method_accessibility( $tab, 'option_page' )->invoke( $tab );
			set_current_screen( 'settings_page_' . $option_page );
			$subject = $this->make_initialized_wizard( $tab );
			$subject->admin_enqueue_scripts();

			self::assertTrue( wp_script_is( OnboardingWizard::HANDLE, 'registered' ) );
			self::assertStringContainsString(
				'"page":"' . $page . '"',
				wp_scripts()->registered[ OnboardingWizard::HANDLE ]->extra['data']
			);
			wp_dequeue_script( OnboardingWizard::HANDLE );
			wp_deregister_script( OnboardingWizard::HANDLE );
		}
	}

	/**
	 * Debug mode serves the unminified wizard script with its file version.
	 */
	public function test_admin_enqueue_scripts_in_debug_mode(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name ? true : FunctionMocker::callOriginal();
			}
		);
		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name ? true : FunctionMocker::callOriginal();
			}
		);

		$this->refresh_settings( [ OnboardingWizard::OPTION_NAME => 'step 1' ] );
		set_current_screen( 'settings_page_hcaptcha' );
		$subject = $this->make_initialized_wizard( $this->get_general_tab() );
		$subject->admin_enqueue_scripts();

		$script = wp_scripts()->registered[ OnboardingWizard::HANDLE ];
		self::assertSame( HCAPTCHA_URL . '/assets/js/onboarding-wizard.js', $script->src );
		self::assertStringStartsWith( HCAPTCHA_VERSION . '-', $script->ver );
	}

	/**
	 * Test ajax_update() with a bad nonce.
	 */
	public function test_ajax_update_with_bad_nonce(): void {
		$this->set_admin_user();

		$_REQUEST['nonce'] = 'bad_nonce';
		$_POST['value']    = 'step 2';

		$json = $this->run_ajax_update( $this->get_general_tab() );

		self::assertSame(
			'{"success":false,"data":"Your session has expired. Please reload the page."}',
			$json
		);
	}

	/**
	 * Test ajax_update() without permission.
	 */
	public function test_ajax_update_without_permission(): void {
		wp_set_current_user( 0 );

		$_REQUEST['nonce'] = wp_create_nonce( OnboardingWizard::UPDATE_ACTION );
		$_POST['value']    = 'step 2';

		$json = $this->run_ajax_update( $this->get_general_tab() );

		self::assertSame(
			'{"success":false,"data":"You are not allowed to perform this action."}',
			$json
		);
	}

	/**
	 * Test ajax_update() with a bad value.
	 */
	public function test_ajax_update_with_bad_value(): void {
		$this->set_admin_user();

		$_REQUEST['nonce'] = wp_create_nonce( OnboardingWizard::UPDATE_ACTION );
		$_POST['value']    = 'bad';

		$json = $this->run_ajax_update( $this->get_general_tab() );

		self::assertSame( '{"success":false,"data":"Bad value"}', $json );
	}

	/**
	 * Test ajax_update().
	 *
	 * @param string $value Expected saved value.
	 *
	 * @dataProvider dp_test_ajax_update
	 */
	public function test_ajax_update( string $value ): void {
		$this->set_admin_user();

		$_REQUEST['nonce'] = wp_create_nonce( OnboardingWizard::UPDATE_ACTION );
		$_POST['value']    = $value;

		$json   = $this->run_ajax_update( $this->get_general_tab() );
		$option = get_option( PluginSettingsBase::OPTION_NAME );

		self::assertSame( '{"success":true}', $json );
		self::assertSame( $value, $option[ OnboardingWizard::OPTION_NAME ] );
	}

	/**
	 * Data provider for test_ajax_update().
	 *
	 * @return array
	 */
	public function dp_test_ajax_update(): array {
		return [
			'step'      => [ 'step 8' ],
			'completed' => [ 'completed' ],
		];
	}

	/**
	 * Get an OnboardingWizard private property from a test subject subclass.
	 *
	 * @param OnboardingWizard $subject       Subject.
	 * @param string           $property_name Property name.
	 *
	 * @return mixed
	 * @throws ReflectionException Reflection exception.
	 */
	private function get_wizard_property( OnboardingWizard $subject, string $property_name ) {
		$property = ( new ReflectionClass( OnboardingWizard::class ) )->getProperty( $property_name );
		$property->setAccessible( true );
		$value = $property->getValue( $subject );
		$property->setAccessible( false );

		return $value;
	}
	/**
	 * Make a testable wizard.
	 *
	 * @param PluginSettingsBase $tab Tab.
	 *
	 * @return OnboardingWizard
	 */
	private function make_wizard( PluginSettingsBase $tab ): OnboardingWizard {
		return new class( $tab ) extends OnboardingWizard {
			/**
			 * Redirect URLs.
			 *
			 * @var string[]
			 */
			public array $redirects = [];

			/**
			 * Redirect after direct step changes are applied.
			 *
			 * @param string $url Redirect URL.
			 *
			 * @return void
			 */
			protected function redirect_after_direct_step( string $url ): void {
				$this->redirects[] = $url;
			}
		};
	}

	/**
	 * Make an initialized testable wizard.
	 *
	 * @param PluginSettingsBase $tab Tab.
	 *
	 * @return OnboardingWizard
	 */
	private function make_initialized_wizard( PluginSettingsBase $tab ): OnboardingWizard {
		$subject = $this->make_wizard( $tab );

		$subject->init();

		return $subject;
	}

	/**
	 * Run ajax_update() and return JSON.
	 *
	 * @param PluginSettingsBase $tab Tab.
	 *
	 * @return string
	 * @noinspection ThrowRawExceptionInspection
	 */
	private function run_ajax_update( PluginSettingsBase $tab ): string {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new Exception( 'wp_die' );
				};
			}
		);

		$subject = $this->make_wizard( $tab );

		ob_start();

		try {
			$subject->ajax_update();
		} catch ( Exception $exception ) {
			self::assertSame( 'wp_die', $exception->getMessage() );
		}

		return ob_get_clean();
	}

	/**
	 * Set an administrator as the current user.
	 *
	 * @return void
	 */
	private function set_admin_user(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );

		wp_set_current_user( $user_id );
	}

	/**
	 * Refresh plugin settings from raw option values.
	 *
	 * @param array $settings Settings.
	 *
	 * @return void
	 */
	private function refresh_settings( array $settings = [] ): void {
		update_option( PluginSettingsBase::OPTION_NAME, $settings );
		hcaptcha()->init_hooks();
	}

	/**
	 * Get General tab.
	 *
	 * @return General
	 */
	private function get_general_tab(): General {
		$tab = hcaptcha()->settings()->get_tab( General::class );

		self::assertInstanceOf( General::class, $tab );

		return $tab;
	}
}
