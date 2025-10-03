<?php
/**
 * HCaptchaHandlerTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\ElementorPro;

use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Classes\HCaptcha_Handler;
use Elementor\Controls_Manager;
use Elementor\Controls_Stack;
use Elementor\Plugin;
use Elementor\Widget_Base;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Utils;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use Elementor\Settings;

/**
 * Class HCaptchaHandlerTest
 *
 * @group elementor-pro
 * @group elementor-pro-hcaptcha-handler
 */
class HCaptchaHandlerTest extends HCaptchaWPTestCase {
	/**
	 * Setup class.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		Mockery::getConfiguration()->setConstantsMap(
			[
				Settings::class => [
					'PAGE_ID'          => 'elementor',
					'TAB_INTEGRATIONS' => 'integrations',
				],
			]
		);

		parent::setUpBeforeClass();
	}

	/**
	 * Teardown test.
	 */
	public function tearDown(): void {
		unset( $_GET['elementor-preview'], $GLOBALS['current_screen'] );

		wp_dequeue_script( 'admin-elementor-pro' );
		wp_deregister_script( 'admin-elementor-pro' );

		wp_dequeue_script( 'hcaptcha-elementor-pro' );
		wp_deregister_script( 'hcaptcha-elementor-pro' );

		parent::tearDown();
	}

	/**
	 * Test constructor.
	 */
	public function test_constructor(): void {
		$subject = new HCaptchaHandler();

		self::assertSame(
			20,
			has_action( 'elementor/init', [ $subject, 'init' ] )
		);
		self::assertSame(
			10,
			has_filter( 'pre_option_elementor_pro_hcaptcha_site_key', '__return_empty_string' )
		);
		self::assertSame(
			10,
			has_filter( 'pre_option_elementor_pro_hcaptcha_secret_key', '__return_empty_string' )
		);
		self::assertSame(
			20,
			has_action( 'elementor/init', [ $subject, 'block_native_integration' ] )
		);
	}

	/**
	 * Test block_native_integration().
	 *
	 * @param bool $is_native_exist Whether native integration exists.
	 *
	 * @return void
	 * @dataProvider dp_test_block_native_integration
	 */
	public function test_block_native_integration( bool $is_native_exist ): void {
		$actions = [
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
			add_action( $action, [ HCaptcha_Handler::class, $action . '_callback' ] );
		}

		wp_register_script( 'elementor-hcaptcha-api', '', [], HCAPTCHA_VERSION, true );
		wp_register_script( 'hcaptcha', '', [], HCAPTCHA_VERSION, true );

		if ( $is_native_exist ) {
			Mockery::mock( HCaptcha_Handler::class );
		}

		$subject = Mockery::mock( HCaptchaHandler::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->block_native_integration();

		if ( $is_native_exist ) {
			foreach ( $actions as $action ) {
				self::assertFalse( has_action( $action, [ HCaptcha_Handler::class, $action . '_callback' ] ) );
			}

			self::assertFalse( wp_script_is( 'elementor-hcaptcha-api', 'registered' ) );
			self::assertFalse( wp_script_is( 'hcaptcha', 'registered' ) );
		} else {
			foreach ( $actions as $action ) {
				self::assertSame( 10, has_action( $action, [ HCaptcha_Handler::class, $action . '_callback' ] ) );
			}

			self::assertTrue( wp_script_is( 'elementor-hcaptcha-api', 'registered' ) );
			self::assertTrue( wp_script_is( 'hcaptcha', 'registered' ) );
		}
	}

	/**
	 * Data provider for test_block_native_integration().
	 *
	 * @return array
	 */
	public function dp_test_block_native_integration(): array {
		return [
			[ false ],
			[ true ],
		];
	}

	/**
	 * Test init().
	 *
	 * @param bool $is_enabled The field is enabled.
	 * @param bool $is_admin   Admin mode.
	 * @param bool $is_preview Elementor preview page.
	 *
	 * @dataProvider dp_test_init
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init( bool $is_enabled, bool $is_admin, bool $is_preview ): void {
		$this->prepare_test_init( $is_enabled, $is_admin, $is_preview );

		$subject = new HCaptchaHandler();

		Mockery::mock( 'alias:Elementor\Settings' );

		$utils = Mockery::mock( Utils::class )->makePartial();

		$utils->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $utils, 'instance', $utils );

		$forms_module = Mockery::mock( 'alias:ElementorPro\Modules\Forms\Module' );

		$forms_module->shouldReceive( 'instance' )->once()->with()->andReturn( $forms_module );
		$forms_module->shouldReceive( 'add_component' )->once()->with( 'hcaptcha', $subject );

		self::assertFalse( wp_script_is( 'elementor-hcaptcha-api', 'registered' ) );
		self::assertFalse( wp_script_is( 'hcaptcha', 'registered' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro', 'registered' ) );

		// Settings prepare.
		if ( $is_admin ) {
			$callback_pattern = '#^' . preg_quote( HCaptcha_Handler::class . '::register_admin_fields', '#' ) . '#';
			$replace          = [ $subject, 'register_admin_fields' ];
			$hook_name        = 'elementor/admin/after_create_settings/elementor';

			$utils->shouldReceive( 'replace_action_regex' )
				->once()->with( $callback_pattern, $replace, $hook_name );
		}

		$subject->init();

		// Settings.
		if ( $is_admin ) {
			self::assertSame(
				20,
				has_filter( 'elementor_pro/editor/localize_settings', [ $subject, 'localize_settings' ] )
			);
		} else {
			self::assertFalse(
				has_filter( 'elementor_pro/editor/localize_settings', [ $subject, 'localize_settings' ] )
			);
		}

		// Render field.
		if ( $is_enabled || $is_admin ) {
			self::assertSame(
				10,
				has_filter( 'elementor_pro/forms/field_types', [ $subject, 'add_field_type' ] )
			);
			self::assertSame(
				10,
				has_action(
					'elementor/element/form/section_form_fields/after_section_end',
					[ $subject, 'modify_controls' ]
				)
			);
			self::assertSame(
				10,
				has_action( 'elementor_pro/forms/render_field/hcaptcha', [ $subject, 'render_field' ] )
			);
			self::assertSame(
				10,
				has_filter( 'elementor_pro/forms/render/item', [ $subject, 'filter_field_item' ] )
			);
		} else {
			self::assertFalse(
				has_filter( 'elementor_pro/forms/field_types', [ $subject, 'add_field_type' ] )
			);
			self::assertFalse(
				has_action(
					'elementor/element/form/section_form_fields/after_section_end',
					[ $subject, 'modify_controls' ]
				)
			);
			self::assertFalse(
				has_action( 'elementor_pro/forms/render_field/hcaptcha', [ $subject, 'render_field' ] )
			);
			self::assertFalse(
				has_filter( 'elementor_pro/forms/render/item', [ $subject, 'filter_field_item' ] )
			);
		}

		if ( $is_enabled ) {
			self::assertSame(
				10,
				has_filter( 'elementor/frontend/the_content', [ $subject, 'elementor_content' ] )
			);
		} else {
			self::assertFalse(
				has_filter( 'elementor/frontend/the_content', [ $subject, 'elementor_content' ] )
			);
		}

		// General hCaptcha scripts and styles.
		self::assertTrue( has_action( 'elementor/editor/init' ) );

		// Elementor preview page.
		if ( $is_preview ) {
			self::assertSame( 10, has_filter( 'hcap_print_hcaptcha_scripts', '__return_true' ) );
		} else {
			self::assertFalse( has_filter( 'hcap_print_hcaptcha_scripts', '__return_true' ) );
		}

		// Elementor-related scripts and styles.
		if ( $is_enabled || $is_admin ) {
			self::assertTrue( wp_script_is( 'hcaptcha-elementor-pro', 'registered' ) );

			$hcaptcha_elementor_pro_frontend = wp_scripts()->registered['hcaptcha-elementor-pro'];
			self::assertSame( HCAPTCHA_URL . '/assets/js/hcaptcha-elementor-pro.min.js', $hcaptcha_elementor_pro_frontend->src );
			self::assertSame( [ 'jquery', 'hcaptcha' ], $hcaptcha_elementor_pro_frontend->deps );
			self::assertSame( HCAPTCHA_VERSION, $hcaptcha_elementor_pro_frontend->ver );
			self::assertSame( [ 'group' => 1 ], $hcaptcha_elementor_pro_frontend->extra );

			self::assertSame(
				20,
				has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
			);
			self::assertSame(
				9,
				has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
			);
			self::assertSame(
				10,
				has_action( 'elementor/preview/enqueue_scripts', [ $subject, 'enqueue_preview_scripts' ] )
			);
		} else {
			self::assertFalse(
				has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
			);
			self::assertFalse(
				has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
			);
			self::assertFalse(
				has_action( 'elementor/preview/enqueue_scripts', [ $subject, 'enqueue_preview_scripts' ] )
			);
		}

		if ( $is_admin ) {
			self::assertSame(
				10,
				has_action( 'elementor/editor/after_enqueue_scripts', [ $subject, 'after_enqueue_scripts' ] )
			);
		} else {
			self::assertFalse(
				has_action( 'elementor/editor/after_enqueue_scripts', [ $subject, 'after_enqueue_scripts' ] )
			);
		}

		// Validation.
		if ( $is_enabled ) {
			self::assertSame(
				10,
				has_action( 'elementor_pro/forms/validation', [ $subject, 'validation' ] )
			);
		} else {
			self::assertFalse(
				has_action( 'elementor_pro/forms/validation', [ $subject, 'validation' ] )
			);
		}

		// Block general hCaptcha scripts and styles on Elementor editor page.
		self::assertFalse(
			has_filter( 'hcap_print_hcaptcha_scripts', '__return_false' )
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		do_action( 'elementor/editor/init' );

		self::assertSame(
			10,
			has_filter( 'hcap_print_hcaptcha_scripts', '__return_false' )
		);
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array
	 */
	public function dp_test_init(): array {
		return [
			'not enabled, not admin, not preview' => [ false, false, false ],
			'not enabled, admin, not preview'     => [ false, true, false ],
			'enabled, not admin, not preview'     => [ true, false, false ],
			'enabled, admin, not preview'         => [ true, true, false ],
			'not enabled, not admin, preview'     => [ false, false, true ],
			'not enabled, admin, preview'         => [ false, true, true ],
			'enabled, not admin, preview'         => [ true, false, true ],
			'enabled, admin, preview'             => [ true, true, true ],
		];
	}

	/**
	 * Test register_admin_fields().
	 *
	 * @return void
	 */
	public function test_register_admin_fields(): void {
		$settings = Mockery::mock( 'alias:Elementor\Settings' );
		$captured = null;

		$settings->shouldReceive( 'add_section' )
			->once()
			->with(
				Settings::TAB_INTEGRATIONS,
				'hcaptcha',
				Mockery::on(
					static function ( $arg ) use ( &$captured ) {
						if ( is_array( $arg ) && isset( $arg['callback'] ) && is_callable( $arg['callback'] ) ) {
							$captured = $arg['callback'];

							return true;
						}

						return false;
					}
				)
			);

		$subject = new HCaptchaHandler();

		$subject->register_admin_fields( $settings );

		self::assertIsCallable( $captured );

		ob_start();
		$captured();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<hr><h2>hCaptcha</h2>', $output );
		self::assertStringContainsString( '<a href="https://www.hcaptcha.com" target="_blank">hCaptcha</a>', $output );

		$notice = HCaptcha::get_hcaptcha_plugin_notice();

		self::assertStringContainsString( '<p><strong>' . $notice['label'] . '</strong></p>', $output );
		self::assertStringContainsString( '<p>' . $notice['description'] . '</p>', $output );
	}

	/**
	 * Test after_enqueue_scripts().
	 */
	public function test_after_enqueue_scripts(): void {
		self::assertFalse( wp_script_is( 'admin-elementor-pro' ) );

		$subject = new HCaptchaHandler();
		$subject->after_enqueue_scripts();

		self::assertTrue( wp_script_is( 'admin-elementor-pro' ) );

		$hcaptcha_elementor_pro = wp_scripts()->registered['admin-elementor-pro'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/admin-elementor-pro.min.js', $hcaptcha_elementor_pro->src );
		self::assertSame( [ 'elementor-editor' ], $hcaptcha_elementor_pro->deps );
		self::assertSame( HCAPTCHA_VERSION, $hcaptcha_elementor_pro->ver );
		self::assertSame( [ 'group' => 1 ], $hcaptcha_elementor_pro->extra );
	}

	/**
	 * Test get_site_key().
	 */
	public function test_get_site_key(): void {
		$site_key = 'some site key';

		update_option( 'hcaptcha_settings', [ 'site_key' => $site_key ] );
		hcaptcha()->init_hooks();

		self::assertSame( $site_key, HCaptchaHandler::get_site_key() );
	}

	/**
	 * Test get_secret_key().
	 */
	public function test_get_secret_key(): void {
		$secret_key = 'some secret key';

		update_option( 'hcaptcha_settings', [ 'secret_key' => $secret_key ] );
		hcaptcha()->init_hooks();

		self::assertSame( $secret_key, HCaptchaHandler::get_secret_key() );
	}

	/**
	 * Test get_hcaptcha_theme().
	 */
	public function test_get_hcaptcha_theme(): void {
		$theme = 'some theme';

		update_option( 'hcaptcha_settings', [ 'theme' => $theme ] );
		hcaptcha()->init_hooks();

		self::assertSame( $theme, HCaptchaHandler::get_hcaptcha_theme() );
	}

	/**
	 * Test get_hcaptcha_size().
	 */
	public function test_get_hcaptcha_size(): void {
		$size = 'some size';

		update_option( 'hcaptcha_settings', [ 'size' => $size ] );
		hcaptcha()->init_hooks();

		self::assertSame( $size, HCaptchaHandler::get_hcaptcha_size() );
	}

	/**
	 * Test get_setup_message().
	 */
	public function test_get_setup_message(): void {
		self::assertSame(
			'To use hCaptcha, you need to add the Site and Secret keys.',
			HCaptchaHandler::get_setup_message()
		);
	}

	/**
	 * Test is_enabled().
	 *
	 * @param string|null $site_key   Site key.
	 * @param string|null $secret_key Secret key.
	 * @param bool        $expected   Expected.
	 *
	 * @dataProvider dp_test_is_enabled
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_is_enabled( $site_key, $secret_key, bool $expected ): void {
		$settings = [];

		if ( $site_key ) {
			$settings['site_key'] = $site_key;
		}

		if ( $secret_key ) {
			$settings['secret_key'] = $secret_key;
		}

		if ( $settings ) {
			update_option( 'hcaptcha_settings', $settings );
		}

		hcaptcha()->init_hooks();

		self::assertSame( $expected, HCaptchaHandler::is_enabled() );
	}

	/**
	 * Data provider for test_is_enabled().
	 *
	 * @return array
	 */
	public function dp_test_is_enabled(): array {
		return [
			[ null, null, false ],
			[ null, 'some secret key', false ],
			[ 'some site key', null, false ],
			[ 'some site key', 'some secret key', true ],
		];
	}

	/**
	 * Test localize_settings().
	 */
	public function test_localize_settings(): void {
		$settings = [
			'forms' => [
				'hcaptcha'  => [
					'enabled'  => false,
					'site_key' => '',
				],
				'recaptcha' => [
					'enabled'       => false,
					'site_key'      => 'recaptcha key',
					'setup_message' => 'recaptcha setup message',
				],
			],
		];

		$site_key   = 'some site key';
		$secret_key = 'some secret key';
		$theme      = 'some theme';
		$size       = 'some size';

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $site_key,
				'secret_key' => $secret_key,
				'theme'      => $theme,
				'size'       => $size,
			]
		);

		hcaptcha()->init_hooks();

		$expected = [
			'forms' => [
				'hcaptcha'  => [
					'enabled'        => true,
					'site_key'       => $site_key,
					'hcaptcha_theme' => $theme,
					'hcaptcha_size'  => $size,
					'setup_message'  => 'To use hCaptcha, you need to add the Site and Secret keys.',
				],
				'recaptcha' => [
					'enabled'       => false,
					'site_key'      => 'recaptcha key',
					'setup_message' => 'recaptcha setup message',
				],
			],
		];

		$subject = new HCaptchaHandler();

		self::assertSame( $expected, $subject->localize_settings( $settings ) );
	}

	/**
	 * Test enqueue_preview_scripts().
	 */
	public function test_enqueue_preview_scripts(): void {
		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro' ) );

		$subject = new HCaptchaHandler();

		wp_register_script( 'hcaptcha-elementor-pro', '', [], HCAPTCHA_VERSION, true );

		$subject->enqueue_preview_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-elementor-pro' ) );
	}

	/**
	 * Test validation.
	 */
	public function test_validation(): void {
		$fields = [
			'field_014ea7c' =>
				[
					'id'        => 'field_014ea7c',
					'type'      => 'hcaptcha',
					'title'     => '',
					'value'     => '',
					'raw_value' => '',
					'required'  => false,
				],
		];
		$field  = current( $fields );

		$hcaptcha_response = 'some response';
		$this->prepare_verify_request( $hcaptcha_response );

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );
		$record->shouldReceive( 'remove_field' )->with( $field['id'] )->once();

		$ajax_handler = Mockery::mock( Ajax_Handler::class );

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test validation.
	 */
	public function test_validation_with_empty_fields(): void {
		$fields = [];

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );

		$ajax_handler = Mockery::mock( Ajax_Handler::class );
		$record->shouldReceive( 'remove_field' )->never();

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test validation with no hCaptcha response.
	 */
	public function test_validation_with_no_captcha(): void {
		$fields = [
			'field_014ea7c' =>
				[
					'id'        => 'field_014ea7c',
					'type'      => 'hcaptcha',
					'title'     => '',
					'value'     => '',
					'raw_value' => '',
					'required'  => false,
				],
		];
		$field  = current( $fields );

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );
		$record->shouldReceive( 'remove_field' )->never();

		$ajax_handler = Mockery::mock( Ajax_Handler::class );
		$ajax_handler->shouldReceive( 'add_error' )->with( $field['id'], 'Please complete the hCaptcha.' )->once();

		$this->prepare_verify_request( '', false );

		unset( $_POST['h-captcha-response'] );

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test validation with failed hCaptcha.
	 */
	public function test_validation_with_failed_captcha(): void {
		$fields = [
			'field_014ea7c' =>
				[
					'id'        => 'field_014ea7c',
					'type'      => 'hcaptcha',
					'title'     => '',
					'value'     => '',
					'raw_value' => '',
					'required'  => false,
				],
		];
		$field  = current( $fields );

		$hcaptcha_response = 'some response';
		$this->prepare_verify_request( $hcaptcha_response, false );

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );
		$record->shouldReceive( 'remove_field' )->never();

		$ajax_handler = Mockery::mock( Ajax_Handler::class );
		$ajax_handler->shouldReceive( 'add_error' )->with( $field['id'], 'The hCaptcha is invalid.' )->once();

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test validation with empty hCaptcha.
	 */
	public function test_validation_with_empty_captcha(): void {
		$fields = [
			'field_014ea7c' =>
				[
					'id'        => 'field_014ea7c',
					'type'      => 'hcaptcha',
					'title'     => '',
					'value'     => '',
					'raw_value' => '',
					'required'  => false,
				],
		];
		$field  = current( $fields );

		$hcaptcha_response = 'some response';
		$this->prepare_verify_request( $hcaptcha_response, null );

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );
		$record->shouldReceive( 'remove_field' )->never();

		$ajax_handler = Mockery::mock( Ajax_Handler::class );
		$ajax_handler->shouldReceive( 'add_error' )->with( $field['id'], 'The hCaptcha is invalid.' )->once();

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test render_field.
	 */
	public function test_render_field(): void {
		$site_key = 'some site key';
		$theme    = 'some theme';
		$size     = 'some size';

		update_option(
			'hcaptcha_settings',
			[
				'site_key' => $site_key,
				'theme'    => $theme,
				'size'     => $size,
			]
		);

		hcaptcha()->init_hooks();

		$item['custom_id'] = '_014ea7c';
		$item_index        = 5;
		$render_attributes = [
			'hcaptcha' . $item_index => [
				'class'        => 'elementor-hcaptcha',
				'data-sitekey' => $site_key,
				'data-theme'   => $theme,
				'data-size'    => $size,
			],
		];
		$form_id           = 'test_form';
		$data              = [
			'settings' => [
				'form_id' => $form_id,
			],
		];
		$args              = [
			'size'    => $size,
			'id'      => [
				'source'  => [ 'elementor-pro/elementor-pro.php' ],
				'form_id' => $form_id,
			],
			'sitekey' => $site_key,
			'theme'   => $theme,
		];
		$expected          =
			'<div class="elementor-field" id="form-field-_014ea7c"><div class="elementor-hcaptcha">' .
			$this->get_hcap_form( $args ) .
			'</div></div>';

		$widget = Mockery::mock( Widget_Base::class );
		$widget->shouldReceive( 'add_render_attribute' )->with( $render_attributes )->once();
		$widget->shouldReceive( 'get_raw_data' )->with()->once()->andReturn( $data );

		$subject = new HCaptchaHandler();

		ob_start();
		$subject->render_field( $item, $item_index, $widget );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_field_type().
	 */
	public function test_add_field_type(): void {
		$field_types = [
			'text'      => 'text',
			'recaptcha' => 'reCaptcha',
		];
		$expected    = [
			'text'      => 'text',
			'hcaptcha'  => 'hCaptcha',
			'recaptcha' => 'reCaptcha',
		];

		$subject = new HCaptchaHandler();

		self::assertSame( $expected, $subject->add_field_type( $field_types ) );
	}

	/**
	 * Test modify_controls().
	 */
	public function test_modify_controls(): void {
		$args = [];

		$control_id  = 'form_fields';
		$unique_name = 'form';

		$control_data = [
			'type'        => 'form-fields-repeater',
			'tab'         => 'content',
			'section'     => 'section_form_fields',
			'fields'      =>
				[
					'_id'      =>
						[
							'type'    => 'hidden',
							'tab'     => 'content',
							'name'    => '_id',
							'default' => '',
						],
					'required' =>
						[
							'type'         => 'switcher',
							'tab'          => 'content',
							'label'        => 'Required',
							'return_value' => 'true',
							'default'      => '',
							'conditions'   =>
								[
									'terms' =>
										[
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[
														'checkbox',
														'recaptcha',
														'recaptcha_v3',
														'hidden',
														'html',
														'step',
													],
											],
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'honeypot' ],
											],
										],
								],
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'required',
						],
					'width'    =>
						[
							'type'          => 'select',
							'tab'           => 'content',
							'label'         => 'Column Width',
							'options'       =>
								[
									''  => 'Default',
									100 => '100%',
									80  => '80%',
									75  => '75%',
									70  => '70%',
									66  => '66%',
									60  => '60%',
									50  => '50%',
									40  => '40%',
									33  => '33%',
									30  => '30%',
									25  => '25%',
									20  => '20%',
								],
							'conditions'    =>
								[
									'terms' =>
										[
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'hidden', 'recaptcha', 'recaptcha_v3', 'step' ],
											],
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'honeypot' ],
											],
										],
								],
							'responsive'    => [],
							'is_responsive' => true,
							'parent'        => null,
							'default'       => '100',
							'tabs_wrapper'  => 'form_fields_tabs',
							'inner_tab'     => 'form_fields_content_tab',
							'name'          => 'width',
						],
				],
			'title_field' => '{{{ field_label }}}',
			'name'        => 'form_fields',
		];

		$expected = [
			'type'        => 'form-fields-repeater',
			'tab'         => 'content',
			'section'     => 'section_form_fields',
			'fields'      =>
				[
					'_id'      =>
						[
							'type'    => 'hidden',
							'tab'     => 'content',
							'name'    => '_id',
							'default' => '',
						],
					'required' =>
						[
							'type'         => 'switcher',
							'tab'          => 'content',
							'label'        => 'Required',
							'return_value' => 'true',
							'default'      => '',
							'conditions'   =>
								[
									'terms' =>
										[
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[
														'checkbox',
														'recaptcha',
														'recaptcha_v3',
														'hidden',
														'html',
														'step',
													],
											],
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'honeypot' ],
											],
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    => [ 'hcaptcha' ],
											],
										],
								],
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'required',
						],
					'width'    =>
						[
							'type'          => 'select',
							'tab'           => 'content',
							'label'         => 'Column Width',
							'options'       =>
								[
									''  => 'Default',
									100 => '100%',
									80  => '80%',
									75  => '75%',
									70  => '70%',
									66  => '66%',
									60  => '60%',
									50  => '50%',
									40  => '40%',
									33  => '33%',
									30  => '30%',
									25  => '25%',
									20  => '20%',
								],
							'conditions'    =>
								[
									'terms' =>
										[
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'hidden', 'recaptcha', 'recaptcha_v3', 'step' ],
											],
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'honeypot' ],
											],
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    => [ 'hcaptcha' ],
											],
										],
								],
							'responsive'    => [],
							'is_responsive' => true,
							'parent'        => null,
							'default'       => '100',
							'tabs_wrapper'  => 'form_fields_tabs',
							'inner_tab'     => 'form_fields_content_tab',
							'name'          => 'width',
						],
				],
			'title_field' => '{{{ field_label }}}',
			'name'        => 'form_fields',
		];

		$controls_stack = Mockery::mock( Controls_Stack::class );
		$controls_stack->shouldReceive( 'get_unique_name' )->andReturn( $unique_name )->once();

		$controls_manager = Mockery::mock( Controls_Manager::class );
		$controls_manager->shouldReceive( 'get_control_from_stack' )->with( $unique_name, $control_id )->once()
			->andReturn( $control_data );
		$controls_manager->shouldReceive( 'update_control_in_stack' )
			->with( $controls_stack, $control_id, $expected, [ 'recursive' => true ] )->once()
			->andReturn( $control_data );

		$plugin = Plugin::instance();

		$plugin::$instance        = $plugin;
		$plugin->controls_manager = $controls_manager;

		$subject = new HCaptchaHandler();
		$subject->modify_controls( $controls_stack, $args );
	}

	/**
	 * Test filter_field_item().
	 */
	public function test_filter_field_item(): void {
		$text_item = [
			'field_type'  => 'text',
			'field_label' => true,
		];

		$hcaptcha_item = [
			'field_type'  => 'hcaptcha',
			'field_label' => true,
		];

		$subject = new HCaptchaHandler();
		self::assertTrue( $subject->filter_field_item( $text_item )['field_label'] );
		self::assertFalse( $subject->filter_field_item( $hcaptcha_item )['field_label'] );
	}

	/**
	 * Test elementor_content().
	 *
	 * @return void
	 */
	public function test_elementor_content(): void {
		hcaptcha()->form_shown = false;

		$subject = new HCaptchaHandler();

		// Some content.
		$subject->elementor_content( 'some content' );

		self::assertFalse( hcaptcha()->form_shown );

		// Content with hCaptcha.
		$subject->elementor_content( 'some content <h-captcha ...' );

		self::assertTrue( hcaptcha()->form_shown );
	}

	/**
	 * Test print_footer_scripts().
	 *
	 * @return void
	 */
	public function test_print_footer_scripts(): void {
		$subject = new HCaptchaHandler();

		wp_register_script( HCaptchaHandler::HANDLE, '', [], HCAPTCHA_VERSION, true );

		$subject->print_footer_scripts();

		self::assertTrue( wp_script_is( HCaptchaHandler::HANDLE ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'CSS'
	.elementor-field-type-hcaptcha .elementor-field {
		background: transparent !important;
	}

	.elementor-field-type-hcaptcha .h-captcha {
		margin-bottom: unset;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new HCaptchaHandler();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Prepare test_init().
	 *
	 * @param bool $is_enabled The field is enabled.
	 * @param bool $is_admin Admin mode.
	 * @param bool $is_preview Elementor preview page.
	 *
	 * @return void
	 */
	protected function prepare_test_init( bool $is_enabled, bool $is_admin, bool $is_preview ): void {
		if ( $is_enabled ) {
			update_option(
				'hcaptcha_settings',
				[
					'site_key'   => 'some site key',
					'secret_key' => 'some secret key',
				]
			);
		}

		hcaptcha()->init_hooks();

		if ( $is_admin ) {
			set_current_screen( 'some' );
		}

		if ( $is_preview ) {
			$_GET['elementor-preview'] = 123;
		}
	}
}
