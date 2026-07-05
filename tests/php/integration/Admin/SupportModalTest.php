<?php
/**
 * SupportModalTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\SupportModal;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;
use ReflectionException;
use function get_current_screen;

/**
 * Test SupportModal class.
 *
 * @group admin
 * @group support-modal
 */
class SupportModalTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		global $current_screen;

		unset( $_GET['page'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_screen = null;

		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @return void
	 */
	public function test_init(): void {
		$subject = new SupportModal();

		self::assertSame( 0, has_action( 'kagg_settings_header', [ $subject, 'render_actions_start' ] ) );
		self::assertSame( 20, has_action( 'kagg_settings_header', [ $subject, 'render_button' ] ) );
		self::assertSame( PHP_INT_MAX, has_action( 'kagg_settings_header', [ $subject, 'render_actions_end' ] ) );
		self::assertSame( 10, has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_assets' ] ) );
		self::assertSame( 10, has_action( 'admin_footer', [ $subject, 'render_modal' ] ) );
	}

	/**
	 * Test enqueue_assets().
	 *
	 * @return void
	 */
	public function test_enqueue_assets(): void {
		$handle  = 'hcaptcha-support-modal';
		$subject = new SupportModal();

		wp_set_current_user( 0 );
		$subject->enqueue_assets();

		self::assertFalse( wp_style_is( $handle ) );

		$this->set_hcaptcha_admin_page();
		$subject->enqueue_assets();

		self::assertTrue( wp_style_is( $handle ) );

		$style = wp_styles()->registered[ $handle ];
		self::assertSame( HCAPTCHA_URL . '/assets/css/support-modal.min.css', $style->src );
		self::assertSame( [], $style->deps );
		self::assertSame( HCAPTCHA_VERSION, $style->ver );

		$script = wp_scripts()->registered[ $handle ];
		self::assertSame( HCAPTCHA_URL . '/assets/js/support-modal.min.js', $script->src );
		self::assertSame( [], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( 1, $script->extra['group'] );
		self::assertStringContainsString( 'var HCaptchaSupportModalObject = ', $script->extra['data'] );
		self::assertStringContainsString( '"systemInfo"', $script->extra['data'] );
		self::assertStringContainsString( '### Begin System Info ###', $script->extra['data'] );
	}

	/**
	 * Test render_actions_start() and render_actions_end().
	 *
	 * @return void
	 */
	public function test_render_actions(): void {
		$subject = new SupportModal();

		ob_start();
		$subject->render_actions_start();
		$subject->render_actions_end();
		$output = ob_get_clean();

		self::assertSame( '', $output );

		$this->set_hcaptcha_admin_page();

		ob_start();
		$subject->render_actions_start();
		$subject->render_actions_end();
		$output = ob_get_clean();

		self::assertStringContainsString( '<div class="hcaptcha-header-actions">', $output );
		self::assertStringContainsString( '</div>', $output );
	}

	/**
	 * Test render_button().
	 *
	 * @return void
	 */
	public function test_render_button(): void {
		$subject = new SupportModal();

		ob_start();
		$subject->render_button();
		$output = ob_get_clean();

		self::assertSame( '', $output );

		$this->set_hcaptcha_admin_page();

		ob_start();
		$subject->render_button();
		$output = ob_get_clean();

		self::assertStringContainsString( 'class="button button-secondary hcaptcha-help-button"', $output );
		self::assertStringContainsString( 'aria-controls="hcaptcha-support-modal"', $output );
		self::assertStringContainsString( 'Help', $output );
	}

	/**
	 * Test render_modal().
	 *
	 * @return void
	 */
	public function test_render_modal(): void {
		$this->set_hcaptcha_admin_page();

		$subject = new SupportModal();

		ob_start();
		$subject->render_modal();
		$output = ob_get_clean();

		self::assertStringContainsString( 'id="hcaptcha-support-modal"', $output );
		self::assertStringContainsString( 'role="dialog"', $output );
		self::assertStringContainsString( 'aria-modal="true"', $output );
		self::assertStringContainsString( 'Need help with hCaptcha?', $output );
		self::assertStringContainsString( 'Report a bug', $output );
		self::assertStringContainsString( 'Request a feature', $output );
		self::assertStringContainsString( 'Ask a setup question', $output );
		self::assertStringContainsString( 'No information is sent automatically.', $output );
		self::assertStringContainsString( 'id="hcaptcha-support-include-system-info" checked', $output );
		self::assertStringContainsString( 'Add system information to the report', $output );
		self::assertStringContainsString( 'Continue on GitHub', $output );
		self::assertStringContainsString( 'Continue on WordPress.org', $output );
		self::assertStringContainsString( 'class="hcaptcha-support-action-help"', $output );
		self::assertStringContainsString( 'aria-expanded="false"', $output );
		self::assertStringContainsString( 'data-hcaptcha-support-continue="wordpress" aria-describedby="hcaptcha-support-wordpress-description hcaptcha-support-wordpress-copy-description" disabled', $output );
		self::assertStringContainsString( 'Best for general setup questions and public community support.', $output );
		self::assertStringContainsString( 'First copy the report', $output );
		self::assertStringContainsString( 'Copy report', $output );
		self::assertStringContainsString( 'data-hcaptcha-support-action="copy"', $output );
		self::assertStringNotContainsString( 'secret_key', $output );

		$copy_pos         = strpos( $output, 'data-hcaptcha-support-copy' );
		$status_pos       = strpos( $output, 'id="hcaptcha-support-status"' );
		$area_pos         = strpos( $output, 'id="hcaptcha-support-area"' );
		$actual_pos       = strpos( $output, 'id="hcaptcha-support-actual"' );
		$alternatives_pos = strpos( $output, 'id="hcaptcha-support-alternatives"' );
		$tried_pos        = strpos( $output, 'id="hcaptcha-support-tried"' );
		$details_pos      = strpos( $output, 'id="hcaptcha-support-details"' );

		self::assertNotFalse( $copy_pos );
		self::assertNotFalse( $status_pos );
		self::assertNotFalse( $area_pos );
		self::assertNotFalse( $actual_pos );
		self::assertNotFalse( $alternatives_pos );
		self::assertNotFalse( $tried_pos );
		self::assertNotFalse( $details_pos );
		self::assertLessThan( $status_pos, $copy_pos );
		self::assertLessThan( $actual_pos, $area_pos );
		self::assertLessThan( $details_pos, $actual_pos );
		self::assertLessThan( $details_pos, $alternatives_pos );
		self::assertLessThan( $details_pos, $tried_pos );
	}

	/**
	 * Test get_system_info().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_system_info(): void {
		$this->set_hcaptcha_admin_page();

		$subject = new SupportModal();
		$method  = $this->set_method_accessibility( $subject, 'get_system_info' );
		$data    = $method->invoke( $subject );

		self::assertStringContainsString( '### Begin System Info ###', $data );
		self::assertStringContainsString( '-- hCaptcha Info --', $data );
		self::assertStringContainsString( '### End System Info ###', $data );
	}

	/**
	 * Test render_modal() outside an hCaptcha admin page.
	 *
	 * @return void
	 */
	public function test_render_modal_returns_outside_hcaptcha_admin_page(): void {
		$subject = new SupportModal();

		ob_start();
		$subject->render_modal();
		$output = ob_get_clean();

		self::assertSame( '', $output );
	}

	/**
	 * Test is_hcaptcha_admin_page() resolves hCaptcha from the current screen.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_is_hcaptcha_admin_page_from_current_screen(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$subject = new SupportModal();
		$method  = $this->set_method_accessibility( $subject, 'is_hcaptcha_admin_page' );

		wp_set_current_user( $user_id );
		set_current_screen( 'settings_page_hcaptcha-network' );
		$_GET['page'] = 'settings';

		self::assertTrue( $method->invoke( $subject ) );
	}

	/**
	 * Test is_hcaptcha_admin_page() when get_current_screen() is unavailable.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_is_hcaptcha_admin_page_without_current_screen_function(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$subject = new SupportModal();
		$method  = $this->set_method_accessibility( $subject, 'is_hcaptcha_admin_page' );

		set_current_screen( 'some' );
		wp_set_current_user( $user_id );
		$_GET['page'] = 'settings';

		FunctionMocker::replace(
			'function_exists',
			static function ( string $function_name ): bool {
				return 'get_current_screen' !== $function_name;
			}
		);

		self::assertFalse( $method->invoke( $subject ) );
	}

	/**
	 * Test is_hcaptcha_admin_page() when the current screen is unavailable.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_is_hcaptcha_admin_page_without_current_screen(): void {
		global $current_screen;

		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$subject = new SupportModal();
		$method  = $this->set_method_accessibility( $subject, 'is_hcaptcha_admin_page' );

		set_current_screen( 'some' );
		wp_set_current_user( $user_id );
		$_GET['page'] = 'settings';

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_screen = null;

		self::assertNull( get_current_screen() );
		self::assertFalse( $method->invoke( $subject ) );
	}

	/**
	 * Test get_system_info() without settings.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_system_info_without_settings(): void {
		$main     = hcaptcha();
		$settings = $this->get_protected_property( $main, 'settings' );

		$this->set_protected_property( $main, 'settings', null );

		$subject = new SupportModal();
		$method  = $this->set_method_accessibility( $subject, 'get_system_info' );

		try {
			self::assertSame( '', $method->invoke( $subject ) );
		} finally {
			$this->set_protected_property( $main, 'settings', $settings );
		}
	}

	/**
	 * Test get_system_info() without the SystemInfo tab.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_system_info_without_system_info_tab(): void {
		$settings = hcaptcha()->settings();
		$tabs     = $this->get_protected_property( $settings, 'tabs' );

		$this->set_protected_property( $settings, 'tabs', [] );

		$subject = new SupportModal();
		$method  = $this->set_method_accessibility( $subject, 'get_system_info' );

		try {
			self::assertSame( '', $method->invoke( $subject ) );
		} finally {
			$this->set_protected_property( $settings, 'tabs', $tabs );
		}
	}

	/**
	 * Set the current request to an hCaptcha admin page.
	 *
	 * @return void
	 */
	private function set_hcaptcha_admin_page(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );

		wp_set_current_user( $user_id );
		set_current_screen( 'settings_page_hcaptcha' );

		$_GET['page'] = 'hcaptcha';
	}
}
