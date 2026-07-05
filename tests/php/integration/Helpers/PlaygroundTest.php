<?php
/**
 * PlaygroundTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use Closure;
use Exception;
use HCaptcha\Helpers\Playground;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Admin_Bar;
use WP_Theme;

/**
 * Test Playground class.
 *
 * @group helpers
 * @group helpers-playground
 */
class PlaygroundTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST, $_REQUEST );
		delete_transient( 'hcaptcha_playground_data' );
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'home_url' );

		parent::tearDown();
	}

	/**
	 * Test constructor outside Playground mode.
	 *
	 * @return void
	 */
	public function test_constructor_not_playground(): void {
		$subject = new Playground();

		self::assertFalse( has_action( 'plugins_loaded', [ $subject, 'setup_playground' ] ) );
	}

	/**
	 * Test constructor in Playground mode.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_constructor_playground_mode(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'HCAPTCHA_PLAYGROUND_MODE' === $constant_name;
			}
		);
		FunctionMocker::replace(
			'constant',
			static function ( $constant_name ) {
				return 'HCAPTCHA_PLAYGROUND_MODE' === $constant_name;
			}
		);

		$subject = new Playground();

		self::assertSame( Playground::LOAD_PRIORITY, has_action( 'plugins_loaded', [ $subject, 'setup_playground' ] ) );
		self::assertTrue( $this->get_protected_property( $subject, 'renew' ) );

		$this->call_hook_closure( 'login_head' );

		self::assertTrue( wp_style_is( 'admin-bar' ) );
		self::assertTrue( wp_script_is( 'admin-bar' ) );

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return ! ( 'wp_admin_bar_render' === $function_name );
			}
		);

		ob_start();
		$this->call_hook_closure( 'login_footer' );
		$admin_bar = ob_get_clean();

		self::assertStringContainsString( 'wpadminbar', $admin_bar );

		$session = new class() {
			/**
			 * Placeholder callback.
			 *
			 * @return void
			 * @noinspection PhpUnused
			 */
			public function maybe_update_nonce_user_logged_out(): void {}
		};
		$wc      = (object) [ 'session' => $session ];

		add_filter( 'nonce_user_logged_out', [ $session, 'maybe_update_nonce_user_logged_out' ] );
		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'WC' === $function_name;
			}
		);
		FunctionMocker::replace(
			'HCaptcha\Helpers\WC',
			static function () use ( $wc ) {
				return $wc;
			}
		);

		$this->call_hook_closure( 'init', 20 );

		self::assertFalse( has_filter( 'nonce_user_logged_out', [ $session, 'maybe_update_nonce_user_logged_out' ] ) );
	}

	/**
	 * Test constructor on a Playground host.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_constructor_playground_host(): void {
		$filter = static function () {
			return 'https://playground.wordpress.net/';
		};

		add_filter( 'home_url', $filter );

		try {
			$subject = new Playground();
		} finally {
			remove_filter( 'home_url', $filter );
		}

		self::assertSame( Playground::LOAD_PRIORITY, has_action( 'plugins_loaded', [ $subject, 'setup_playground' ] ) );
		self::assertFalse( $this->get_protected_property( $subject, 'renew' ) );
	}

	/**
	 * Test setup_playground().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_playground(): void {
		$subject = $this->make_subject();

		$subject->setup_playground();

		$data     = get_transient( 'hcaptcha_playground_data' );
		$settings = get_option( 'hcaptcha_settings' );

		self::assertTrue( $data['setup'] );
		self::assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
		self::assertSame( 'pro', $settings['license'] );

		$subject = $this->make_subject( true, [ 'setup' => true ] );

		$subject->setup_playground();

		self::assertTrue( get_transient( 'hcaptcha_playground_data' )['setup'] );
	}

	/**
	 * Test setup_plugin().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_plugin(): void {
		$subject = $this->make_subject();

		wp_insert_post(
			[
				'post_type'   => 'wpcf7_contact_form',
				'post_status' => 'publish',
				'post_title'  => 'Contact Form',
			]
		);

		$methods = [
			'setup_contact_form_7',
			'setup_elementor_pro',
			'setup_essential_addons',
			'setup_jetpack',
			'setup_mailchimp',
			'setup_spectra',
			'setup_ultimate_addons',
			'setup_woocommerce',
			'setup_wpforms',
		];

		foreach ( $methods as $method_name ) {
			$method = $this->set_method_accessibility( $subject, $method_name );
			$method->invoke( $subject );
		}

		$subject->setup_plugin( 'unknown/unknown.php', false );

		$data = get_transient( 'hcaptcha_playground_data' );

		self::assertTrue( $data['plugins']['unknown/unknown.php'] );
		self::assertNotNull( get_page_by_path( 'contact-form-7-test' ) );
		self::assertNotNull( get_page_by_path( 'wpforms-test' ) );

		$subject = $this->make_subject( false, [ 'plugins' => [ 'jetpack/jetpack.php' => true ] ] );
		$subject->setup_plugin( 'jetpack/jetpack.php', false );

		self::assertTrue( get_transient( 'hcaptcha_playground_data' )['plugins']['unknown/unknown.php'] );
	}

	/**
	 * Test setup_theme().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_theme(): void {
		$subject = $this->make_subject();
		$theme4  = $this->make_theme( '4.27.4' );
		$theme5  = $this->make_theme( '5.0.0-public-beta.2' );

		$subject->setup_theme( 'Avada', $theme4, $theme4 );
		$subject->setup_theme( 'Divi', $theme4, $theme4 );
		$subject->setup_theme( 'Divi', $theme5, $theme4 );
		$subject->setup_theme( 'Extra', $theme4, $theme4 );
		$subject->setup_theme( 'Unknown', $theme4, $theme4 );

		$data = get_transient( 'hcaptcha_playground_data' );

		self::assertTrue( $data['themes']['Avada'] );
		self::assertTrue( $data['themes']['Divi'] );
		self::assertTrue( $data['themes']['Extra'] );
		self::assertArrayNotHasKey( 'Unknown', $data['themes'] );

		$subject = $this->make_subject( false, [ 'themes' => [ 'Avada' => true ] ] );
		$subject->setup_theme( 'Avada', $theme4, $theme4 );

		self::assertTrue( get_transient( 'hcaptcha_playground_data' )['themes']['Avada'] );
	}

	/**
	 * Test display and enqueue methods.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_display_and_enqueue_methods(): void {
		$subject = $this->make_subject();

		ob_start();
		$subject->head_styles();
		$styles = ob_get_clean();

		self::assertStringContainsString( 'playground-icon.svg', $styles );

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-playground' ) );
		self::assertStringContainsString(
			'HCaptchaPlaygroundObject',
			(string) wp_scripts()->get_data( 'hcaptcha-playground', 'data' )
		);
	}

	/**
	 * Test admin_bar_menu().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_bar_menu(): void {
		$subject = $this->make_subject();

		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		$bar = new WP_Admin_Bar();

		do_action( 'login_init' );
		$bar->add_node(
			[
				'id'    => 'search',
				'title' => 'Search',
			]
		);

		$subject->admin_bar_menu( $bar );

		self::assertNull( $bar->get_node( 'search' ) );
		self::assertNotNull( $bar->get_node( 'hcaptcha-menu' ) );
		self::assertNotNull( $bar->get_node( 'hcaptcha-menu-wp-login' ) );
	}

	/**
	 * Test update_menu().
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function test_update_menu(): void {
		$subject = $this->make_subject();

		$_REQUEST['nonce'] = 'bad nonce';
		$response          = $this->call_ajax_method( [ $subject, 'update_menu' ] );

		self::assertFalse( $response['success'] );

		$_REQUEST['nonce'] = wp_create_nonce( 'hcaptcha-playground-update-menu' );
		$response          = $this->call_ajax_method( [ $subject, 'update_menu' ] );

		self::assertFalse( $response['success'] );

		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		$_REQUEST['nonce'] = wp_create_nonce( 'hcaptcha-playground-update-menu' );
		$response          = $this->call_ajax_method( [ $subject, 'update_menu' ] );

		self::assertTrue( $response['success'] );
		self::assertContains( 'hcaptcha-menu-wp-login', wp_list_pluck( $response['data'], 'id' ) );
	}

	/**
	 * Test private helper methods.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_private_helpers(): void {
		$subject            = $this->make_subject();
		$get_href           = $this->set_method_accessibility( $subject, 'get_href' );
		$is_playground_host = $this->set_method_accessibility( $subject, 'is_playground_host' );
		$is_wp_playground   = $this->set_method_accessibility( $subject, 'is_wp_playground' );
		$get_divi_slug      = $this->set_method_accessibility( $subject, 'get_divi_test_slug' );
		$insert_post        = $this->set_method_accessibility( $subject, 'insert_post' );
		$setup_permalinks   = $this->set_method_accessibility( $subject, 'setup_permalinks' );
		$setup_settings     = $this->set_method_accessibility( $subject, 'setup_settings' );

		$main             = hcaptcha();
		$original_modules = $main->modules;
		$main->modules    = [
			'WP Core' => [ [ 'wp_status', null ], '', [] ],
			'Plugin'  => [ [ 'plugin_status', null ], 'missing/missing.php', [] ],
		];

		try {
			self::assertSame( 'https://example.test/', $get_href->invoke( $subject, 'wp_status', 'https://example.test/' ) );
			self::assertStringContainsString(
				'suggest_activate=plugin_status',
				$get_href->invoke( $subject, 'plugin_status', 'https://example.test/plugin' )
			);
		} finally {
			$main->modules = $original_modules;
		}

		self::assertFalse( $is_playground_host->invoke( $subject ) );
		self::assertFalse( $is_wp_playground->invoke( $subject ) );

		$filter = static function () {
			return 'https://playground.wordpress.net/';
		};

		add_filter( 'home_url', $filter );

		try {
			self::assertTrue( $is_playground_host->invoke( $subject ) );
			self::assertTrue( $is_wp_playground->invoke( $subject ) );
		} finally {
			remove_filter( 'home_url', $filter );
		}

		self::assertSame( 'divi-test', $get_divi_slug->invoke( $subject ) );

		wp_insert_post(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Divi 5 Test Page',
				'post_name'   => 'divi5-test',
			]
		);

		self::assertSame( 'divi5-test', $get_divi_slug->invoke( $subject ) );

		$original_stylesheet = get_stylesheet();

		$this->make_theme( '5.0.0', 'Divi5' );
		$this->make_theme( '4.27.4', 'Divi4' );
		$this->make_theme( '1.0.0', 'not-divi', 'Not Divi' );

		try {
			switch_theme( 'Divi5' );
			self::assertSame( 'divi5-test', $get_divi_slug->invoke( $subject ) );

			switch_theme( 'Divi4' );
			self::assertSame( 'divi-test', $get_divi_slug->invoke( $subject ) );

			switch_theme( 'not-divi' );
			self::assertSame( 'divi5-test', $get_divi_slug->invoke( $subject ) );
		} finally {
			switch_theme( $original_stylesheet );
		}

		$this->set_protected_property( $subject, 'renew', false );

		$post_id = $insert_post->invoke(
			$subject,
			[
				'title'   => 'Existing Test Page',
				'name'    => 'existing-test-page',
				'content' => 'first',
			]
		);
		$same_id = $insert_post->invoke(
			$subject,
			[
				'title'   => 'Existing Test Page',
				'name'    => 'existing-test-page',
				'content' => 'second',
			]
		);

		self::assertSame( $post_id, $same_id );

		$this->set_protected_property( $subject, 'renew', true );

		$new_id = $insert_post->invoke(
			$subject,
			[
				'title'   => 'Existing Test Page',
				'name'    => 'existing-test-page',
				'content' => 'third',
			]
		);

		self::assertNotSame( $post_id, $new_id );

		$setup_permalinks->invoke( $subject );
		$setup_settings->invoke( $subject );

		self::assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
		self::assertSame( 'pro', get_option( 'hcaptcha_settings' )['license'] );
	}

	/**
	 * Call the first closure registered for a hook.
	 *
	 * @param string $hook_name Hook name.
	 * @param int    $priority  Hook priority.
	 *
	 * @return void
	 */
	private function call_hook_closure( string $hook_name, int $priority = 10 ): void {
		global $wp_filter;

		$callbacks = $wp_filter[ $hook_name ]->callbacks[ $priority ] ?? [];

		foreach ( $callbacks as $callback ) {
			if ( ( $callback['function'] ?? null ) instanceof Closure ) {
				$callback['function']();

				return;
			}
		}

		self::fail( "No closure found for $hook_name." );
	}
	/**
	 * Make a Playground subject.
	 *
	 * @param bool  $renew Renew flag.
	 * @param array $data  Playground data.
	 *
	 * @return Playground
	 * @throws ReflectionException Reflection exception.
	 */
	private function make_subject( bool $renew = true, array $data = [] ): Playground {
		$reflection = new ReflectionClass( Playground::class );
		$subject    = $reflection->newInstanceWithoutConstructor();

		$this->set_protected_property( $subject, 'renew', $renew );
		$this->set_protected_property( $subject, 'data', $data );

		return $subject;
	}

	/**
	 * Make a WP_Theme instance.
	 *
	 * @param string $version    Theme version.
	 * @param string $stylesheet Theme stylesheet.
	 * @param string $name       Theme name.
	 *
	 * @return WP_Theme
	 * @noinspection PhpMethodParametersCountMismatchInspection
	 */
	private function make_theme( string $version, string $stylesheet = '', string $name = 'Divi' ): WP_Theme {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		WP_Filesystem();

		$stylesheet = $stylesheet ?: 'divi-' . sanitize_key( $version );
		$theme_root = trailingslashit( get_temp_dir() ) . 'hcaptcha-playground-themes';
		$theme_dir  = trailingslashit( $theme_root ) . $stylesheet;

		wp_mkdir_p( $theme_dir );
		$wp_filesystem->put_contents(
			trailingslashit( $theme_dir ) . 'style.css',
			"/*\nTheme Name: $name\nVersion: $version\n*/\n",
			FS_CHMOD_FILE
		);

		register_theme_directory( $theme_root );

		global $wp_theme_directories;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary test theme root must take precedence.
		$wp_theme_directories = array_values(
			array_unique(
				array_merge( [ $theme_root ], (array) $wp_theme_directories )
			)
		);

		search_theme_directories( true );
		wp_clean_themes_cache();

		return new WP_Theme( $stylesheet, $theme_root );
	}

	/**
	 * Call an ajax method and decode the response.
	 *
	 * @param callable $callback Callback.
	 *
	 * @return array
	 * @throws Exception Exception.
	 * @noinspection ThrowRawExceptionInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function call_ajax_method( callable $callback ): array {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new Exception( 'wp_die' );
				};
			}
		);

		ob_start();

		try {
			$callback();
		} catch ( Exception $e ) {
			if ( 'wp_die' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			remove_all_filters( 'wp_die_ajax_handler' );
			remove_all_filters( 'wp_doing_ajax' );
		}

		$json = ob_get_clean();

		return (array) json_decode( $json, true );
	}
}
