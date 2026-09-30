<?php
/**
 * IntegrationsAjaxTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Settings\Integrations;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test integration dependency Ajax actions.
 *
 * @group settings
 * @group settings-integrations
 */
class IntegrationsAjaxTest extends HCaptchaTestCase {
	/**
	 * Clear request data after each test.
	 */
	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Set up request functions used by the settings actions.
	 */
	private function mock_request(): void {
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'esc_html' );
	}

	/**
	 * Test unsupported activation plan entities are rejected.
	 */
	public function test_activation_plan_rejects_unknown_entity(): void {
		$this->mock_request();
		$_POST['entity'] = 'unknown';
		$subject         = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::ACTIVATION_PLAN_ACTION )->once();
		WP_Mock::userFunction( 'wp_send_json_error' )->with( 'Unsupported integration entity.' )->once();

		$subject->activation_plan();
	}

	/**
	 * Test activation plan permission errors are returned to the caller.
	 */
	public function test_activation_plan_rejects_permission_error(): void {
		$this->mock_request();
		$_POST['entity'] = 'plugin';
		$subject         = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::ACTIVATION_PLAN_ACTION )->once();
		$error = Mockery::mock( 'overload:WP_Error' );
		$error->shouldReceive( 'get_error_message' )->once()->andReturn( 'Permission denied' );
		$subject->shouldReceive( 'get_activation_error' )->with( true, '', '' )->once()->andReturn( $error );
		WP_Mock::userFunction( 'wp_send_json_error' )->with( 'Permission denied' )->once();

		$subject->activation_plan();
	}

	/**
	 * Test unsupported deactivation plan entities are rejected.
	 */
	public function test_deactivation_plan_rejects_unknown_entity(): void {
		$this->mock_request();
		$_POST['entity'] = 'unknown';
		$subject         = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::DEACTIVATION_PLAN_ACTION )->once();
		WP_Mock::userFunction( 'wp_send_json_error' )->with( 'Unsupported integration entity.' )->once();

		$subject->deactivation_plan();
	}

	/**
	 * Test a deactivation plan rejects users without a plugin capability.
	 */
	public function test_deactivation_plan_rejects_permission_error(): void {
		$this->mock_request();
		$_POST['entity'] = 'plugin';
		$subject         = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::DEACTIVATION_PLAN_ACTION )->once();
		$error = Mockery::mock( 'overload:WP_Error' );
		$error->shouldReceive( 'get_error_message' )->once()->andReturn( 'Permission denied' );
		$subject->shouldReceive( 'get_plugin_activation_error' )->once()->andReturn( $error );
		WP_Mock::userFunction( 'wp_send_json_error' )->with( 'Permission denied' )->once();

		$subject->deactivation_plan();
	}

	/**
	 * Test a plugin deactivation plan is returned when it has no blockers.
	 */
	public function test_deactivation_plan_for_plugin(): void {
		$this->mock_request();
		$_POST['entity'] = 'plugin';
		$_POST['status'] = 'example-status';
		$subject         = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::DEACTIVATION_PLAN_ACTION )->once();
		$subject->shouldReceive( 'get_plugin_activation_error' )->once()->andReturn( null );
		$subject->shouldReceive( 'get_status_plugins' )->with( 'example_status' )->once()->andReturn( [ 'example/plugin.php' ] );

		$plan = [
			'items'         => [],
			'rootBlockedBy' => [],
		];

		$subject->shouldReceive( 'build_deactivation_plan' )->with( [ 'example/plugin.php' ], '', '' )->once()->andReturn( $plan );
		WP_Mock::userFunction( 'wp_send_json_success' )->with( [ 'plan' => $plan ] )->once();

		$subject->deactivation_plan();
	}

	/**
	 * Test theme blockers are reported in the deactivation plan.
	 */
	public function test_deactivation_plan_reports_blocked_theme(): void {
		$this->mock_request();
		$_POST['entity'] = 'theme';
		$subject         = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::DEACTIVATION_PLAN_ACTION )->once();
		$subject->shouldReceive( 'get_theme_switch_error' )->once()->andReturn( null );
		$subject->shouldReceive( 'build_deactivation_plan' )->with( [], '', '' )->once()
			->andReturn( [ 'rootBlockedBy' => [ 'Active Theme' ] ] );
		$subject->shouldReceive( 'send_deactivation_blocked_error' )->with( [ 'Active Theme' ] )->once();

		$subject->deactivation_plan();
	}

	/**
	 * Test a plugin deactivation with selected dependencies.
	 */
	public function test_activate_deactivates_selected_dependencies(): void {
		$this->mock_request();
		$this->mock_activate_input();
		$_POST['manageDependencies'] = '1';
		$_POST['dependencies']       = [ 'dependency/dependency.php' ];
		$subject                     = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::ACTIVATE_ACTION )->once();
		$subject->shouldReceive( 'get_activation_error' )->with( false, '', '' )->once()->andReturn( null );
		$subject->shouldReceive( 'get_status_plugins' )->with( 'example_status' )->once()->andReturn( [ 'root/root.php' ] );
		$plan = [
			'rootBlockedBy' => [],
			'items'         => [],
		];
		$subject->shouldReceive( 'build_deactivation_plan' )->with( [ 'root/root.php' ], '', '' )->once()->andReturn( $plan );
		$subject->shouldReceive( 'get_current_theme_consumer' )->once()->andReturn( [] );
		$subject->shouldReceive( 'get_deactivation_plugins' )
			->with( $plan, [ 'dependency/dependency.php' ], false, [] )->once()->andReturn( [ 'root/root.php' ] );
		$subject->shouldReceive( 'process_plugins' )->with( false, [ 'root/root.php' ], '' )->once();
		FunctionMocker::replace( 'header_remove' );
		FunctionMocker::replace( 'http_response_code', 200 );

		$subject->activate();
	}

	/**
	 * Test a blocked root stops plugin deactivation.
	 */
	public function test_activate_stops_on_blocked_root(): void {
		$this->mock_request();
		$this->mock_activate_input();
		$_POST['manageDependencies'] = '1';
		$subject                     = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::ACTIVATE_ACTION )->once();
		$subject->shouldReceive( 'get_activation_error' )->with( false, '', '' )->once()->andReturn( null );
		$subject->shouldReceive( 'get_status_plugins' )->with( 'example_status' )->once()->andReturn( [ 'root/root.php' ] );
		$subject->shouldReceive( 'build_deactivation_plan' )->with( [ 'root/root.php' ], '', '' )->once()
			->andReturn( [ 'rootBlockedBy' => [ 'Active Theme' ] ] );
		$subject->shouldReceive( 'send_deactivation_blocked_error' )->with( [ 'Active Theme' ] )->once();
		$subject->shouldReceive( 'process_plugins' )->never();
		FunctionMocker::replace( 'header_remove' );
		FunctionMocker::replace( 'http_response_code', 200 );

		$subject->activate();
	}

	/**
	 * Mock native input for a plugin deactivation request.
	 */
	private function mock_activate_input(): void {
		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name ) {
				if ( 'activate' === $name || 'install' === $name ) {
					return false;
				}
				if ( 'entity' === $name ) {
					return 'plugin';
				}
				if ( 'status' === $name ) {
					return 'example-status';
				}
				return '';
			}
		);
	}
}
