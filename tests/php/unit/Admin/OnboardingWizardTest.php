<?php
/**
 * OnboardingWizardTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Admin;

use HCaptcha\Admin\OnboardingWizard;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionClass;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class OnboardingWizardTest
 *
 * @group admin
 * @group onboarding-wizard
 */
class OnboardingWizardTest extends HCaptchaTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_GET[ OnboardingWizard::STEP_PARAM ], $_GET[ OnboardingWizard::NONCE_PARAM ] );

		parent::tearDown();
	}

	/**
	 * Set a private OnboardingWizard property.
	 *
	 * @param object $subject       Subject.
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property value.
	 *
	 * @return void
	 */
	private function set_wizard_property( object $subject, string $property_name, $value ): void {
		$property = ( new ReflectionClass( OnboardingWizard::class ) )->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $subject, $value );
		$property->setAccessible( false );
	}

	/**
	 * Mock onboarding GET input.
	 *
	 * @param string $step  Step parameter.
	 * @param string $nonce Nonce parameter.
	 *
	 * @return void
	 */
	private function mock_get_input( string $step, string $nonce ): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( $type, $name ) use ( $step, $nonce ) {
				if ( INPUT_GET !== $type ) {
					return '';
				}

				if ( OnboardingWizard::STEP_PARAM === $name ) {
					return $step;
				}

				if ( OnboardingWizard::NONCE_PARAM === $name ) {
					return $nonce;
				}

				return '';
			}
		);
	}

	/**
	 * Test maybe_handle_direct_step().
	 */
	public function test_maybe_handle_direct_step(): void {
		$nonce = 'step_nonce';
		$url   = 'https://test.test/wp-admin/admin.php?page=hcaptcha&tab=integrations';

		$this->mock_get_input( '7', $nonce );

		$tab          = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$general      = Mockery::mock( General::class )->makePartial();
		$integrations = Mockery::mock( Integrations::class )->makePartial();

		WP_Mock::userFunction( 'add_action' )->withAnyArgs();
		WP_Mock::userFunction( 'wp_doing_ajax' )->with()->once()->andReturn( false );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( true );
		WP_Mock::userFunction( 'wp_verify_nonce' )
			->with( $nonce, OnboardingWizard::STEP_ACTION )
			->once()
			->andReturn( 1 );

		$tab->shouldReceive( 'update_option' )->once()->with( OnboardingWizard::OPTION_NAME, 'step 7' );
		$tab->shouldReceive( 'tab_url' )->once()->with( $integrations )->andReturn( $url );

		$subject = Mockery::mock( OnboardingWizard::class, [ $tab ] )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'redirect_after_direct_step' )->once()->with( $url );

		$this->set_wizard_property( $subject, 'general_tab', $general );
		$this->set_wizard_property( $subject, 'integrations_tab', $integrations );

		$subject->maybe_handle_direct_step();
	}

	/**
	 * Test maybe_handle_direct_step() with a bad nonce.
	 */
	public function test_maybe_handle_direct_step_with_bad_nonce(): void {
		$nonce = 'bad_nonce';

		$this->mock_get_input( '3', $nonce );

		$tab = Mockery::mock( PluginSettingsBase::class )->makePartial();

		WP_Mock::userFunction( 'add_action' )->withAnyArgs();
		WP_Mock::userFunction( 'wp_doing_ajax' )->with()->once()->andReturn( false );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( true );
		WP_Mock::userFunction( 'wp_verify_nonce' )
			->with( $nonce, OnboardingWizard::STEP_ACTION )
			->once()
			->andReturn( false );

		$tab->shouldReceive( 'update_option' )->never();
		$tab->shouldReceive( 'tab_url' )->never();

		$subject = Mockery::mock( OnboardingWizard::class, [ $tab ] )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'redirect_after_direct_step' )->never();

		$subject->maybe_handle_direct_step();
	}
}
