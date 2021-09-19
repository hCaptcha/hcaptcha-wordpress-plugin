<?php
/**
 * HCaptchaHandlerTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ElementorPro\Modules\Forms\Classes\HCaptchaHandler;

use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Module;
use Elementor\Controls_Manager;
use Elementor\Controls_Stack;
use Elementor\Plugin;
use Elementor\Widget_Base;
use HCaptcha\ElementorPro\Modules\Forms\Classes\HCaptchaHandler;
use HCaptcha\Main;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;

/**
 * Class HCaptchaHandlerTest
 *
 * @group hcaptcha-handler
 */
class HCaptchaHandlerTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_constructor() {
		global $hcaptcha_wordpress_plugin;

		$subject = new HCaptchaHandler();

		self::assertInstanceOf( Main::class, $this->get_protected_property( $subject, 'main' ) );
		self::assertSame( $hcaptcha_wordpress_plugin, $this->get_protected_property( $subject, 'main' ) );

		self::assertSame(
			10,
			has_action( 'elementor/editor/after_enqueue_scripts', [ $subject, 'after_enqueue_scripts' ] )
		);
		self::assertSame(
			10,
			has_action( 'elementor/init', [ $subject, 'init' ] )
		);
	}

	/**
	 * Test after_enqueue_scripts().
	 */
	public function test_after_enqueue_scripts() {
		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro' ) );

		$subject = new HCaptchaHandler();
		$subject->after_enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-elementor-pro' ) );

		$hcaptcha_elementor_pro = wp_scripts()->registered['hcaptcha-elementor-pro'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/hcaptcha-elementor-pro.js', $hcaptcha_elementor_pro->src );
		self::assertSame( [ 'elementor-editor' ], $hcaptcha_elementor_pro->deps );
		self::assertSame( HCAPTCHA_VERSION, $hcaptcha_elementor_pro->ver );
		self::assertSame( [ 'group' => 1 ], $hcaptcha_elementor_pro->extra );
	}

	/**
	 * Test init().
	 *
	 * @param bool $enabled Field is enabled.
	 *
	 * @dataProvider dp_test_init
	 */
	public function test_init( $enabled ) {
		if ( $enabled ) {
			update_option( 'hcaptcha_api_key', 'some api key' );
			update_option( 'hcaptcha_secret_key', 'some secret key' );
		}

		self::assertFalse( wp_script_is( 'elementor-hcaptcha-api', 'registered' ) );
		self::assertFalse( wp_script_is( 'hcaptcha', 'registered' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro-frontend', 'registered' ) );

		$subject = new HCaptchaHandler();
		$subject->init();

		self::assertTrue( wp_script_is( 'elementor-hcaptcha-api', 'registered' ) );

		$elementor_hcaptcha_api = wp_scripts()->registered['elementor-hcaptcha-api'];
		self::assertSame( 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit', $elementor_hcaptcha_api->src );
		self::assertSame( [], $elementor_hcaptcha_api->deps );
		self::assertSame( HCAPTCHA_VERSION, $elementor_hcaptcha_api->ver );
		self::assertSame( [ 'group' => 1 ], $elementor_hcaptcha_api->extra );

		self::assertTrue( wp_script_is( 'hcaptcha', 'registered' ) );

		$hcaptcha = wp_scripts()->registered['hcaptcha'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/hcaptcha.js', $hcaptcha->src );
		self::assertSame( [], $hcaptcha->deps );
		self::assertSame( HCAPTCHA_VERSION, $hcaptcha->ver );
		self::assertSame( [ 'group' => 1 ], $hcaptcha->extra );

		self::assertTrue( wp_script_is( 'hcaptcha-elementor-pro-frontend', 'registered' ) );

		$hcaptcha_elementor_pro_frontend = wp_scripts()->registered['hcaptcha-elementor-pro-frontend'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/hcaptcha-elementor-pro-frontend.js', $hcaptcha_elementor_pro_frontend->src );
		self::assertSame( [ 'jquery', 'hcaptcha' ], $hcaptcha_elementor_pro_frontend->deps );
		self::assertSame( HCAPTCHA_VERSION, $hcaptcha_elementor_pro_frontend->ver );
		self::assertSame( [ 'group' => 1 ], $hcaptcha_elementor_pro_frontend->extra );

		self::assertSame(
			10,
			has_action( 'elementor_pro/forms/register_action', [ $subject, 'register_action' ] )
		);

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
		self::assertSame(
			10,
			has_filter( 'elementor_pro/editor/localize_settings', [ $subject, 'localize_settings' ] )
		);

		if ( $enabled ) {
			self::assertSame(
				10,
				has_action( 'elementor_pro/forms/validation', [ $subject, 'validation' ] )
			);
			self::assertSame(
				10,
				has_action( 'elementor/preview/enqueue_scripts', [ $subject, 'enqueue_scripts' ] )
			);
		} else {
			self::assertFalse(
				has_action( 'elementor_pro/forms/validation', [ $subject, 'validation' ] )
			);
			self::assertFalse(
				has_action( 'elementor/preview/enqueue_scripts', [ $subject, 'enqueue_scripts' ] )
			);
		}
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array
	 */
	public function dp_test_init() {
		return [
			'not enabled' => false,
			'enabled'     => true,
		];
	}

	/**
	 * Test register_action().
	 */
	public function test_register_action() {
		$subject = new HCaptchaHandler();

		$module = Mockery::mock( Module::class );
		$module->shouldReceive( 'add_component' )->with( 'hcaptcha', $subject )->once();

		$subject->register_action( $module );
	}

	/**
	 * Test get_site_key().
	 */
	public function test_get_site_key() {
		$site_key = 'some api key';
		update_option( 'hcaptcha_api_key', $site_key );

		self::assertSame( $site_key, HCaptchaHandler::get_site_key() );
	}

	/**
	 * Test get_secret_key().
	 */
	public function test_get_secret_key() {
		$secret_key = 'some secret key';
		update_option( 'hcaptcha_secret_key', $secret_key );

		self::assertSame( $secret_key, HCaptchaHandler::get_secret_key() );
	}

	/**
	 * Test get_hcaptcha_theme().
	 */
	public function test_get_hcaptcha_theme() {
		$theme = 'some theme';
		update_option( 'hcaptcha_theme', $theme );

		self::assertSame( $theme, HCaptchaHandler::get_hcaptcha_theme() );
	}

	/**
	 * Test get_hcaptcha_size().
	 */
	public function test_get_hcaptcha_size() {
		$size = 'some size';
		update_option( 'hcaptcha_size', $size );

		self::assertSame( $size, HCaptchaHandler::get_hcaptcha_size() );
	}

	/**
	 * Test get_setup_message().
	 */
	public function test_get_setup_message() {
		self::assertSame(
			'To use hCaptcha, you need to add the API Key and Secret Key.',
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
	 */
	public function test_is_enabled( $site_key, $secret_key, $expected ) {
		if ( $site_key ) {
			update_option( 'hcaptcha_api_key', $site_key );
		}

		if ( $secret_key ) {
			update_option( 'hcaptcha_secret_key', $secret_key );
		}

		self::assertSame( $expected, HCaptchaHandler::is_enabled() );
	}

	/**
	 * Data provider for test_is_enabled().
	 *
	 * @return array
	 */
	public function dp_test_is_enabled() {
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
	public function test_localize_settings() {
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

		$site_key   = 'some api key';
		$secret_key = 'some secret key';
		$theme      = 'some theme';
		$size       = 'some size';

		update_option( 'hcaptcha_api_key', $site_key );
		update_option( 'hcaptcha_secret_key', $secret_key );
		update_option( 'hcaptcha_theme', $theme );
		update_option( 'hcaptcha_size', $size );

		$expected = [
			'forms' => [
				'hcaptcha'  => [
					'enabled'        => true,
					'site_key'       => $site_key,
					'hcaptcha_theme' => $theme,
					'hcaptcha_size'  => $size,
					'setup_message'  => 'To use hCaptcha, you need to add the API Key and Secret Key.',
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
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts() {
		global $hcaptcha_wordpress_plugin;

		self::assertFalse( wp_script_is( 'elementor-hcaptcha-api' ) );
		self::assertFalse( wp_script_is( 'hcaptcha' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro-frontend' ) );

		ob_start();
		$hcaptcha_wordpress_plugin->print_inline_styles();
		$expected = ob_get_clean();

		ob_start();
		$subject = new HCaptchaHandler();
		$subject->init();
		$subject->enqueue_scripts();
		self::assertSame( $expected, ob_get_clean() );

		self::assertTrue( wp_script_is( 'elementor-hcaptcha-api' ) );
		self::assertTrue( wp_script_is( 'hcaptcha' ) );
		self::assertTrue( wp_script_is( 'hcaptcha-elementor-pro-frontend' ) );
	}

	/**
	 * Test validation.
	 */
	public function test_validation() {
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
		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

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
	public function test_validation_with_empty_fields() {
		$fields = [];

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );

		$ajax_handler = Mockery::mock( Ajax_Handler::class );
		$record->shouldReceive( 'remove_field' )->never();

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test validation.
	 */
	public function test_validation_with_failed_captcha() {
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
		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );
		$record->shouldReceive( 'remove_field' )->never();

		$ajax_handler = Mockery::mock( Ajax_Handler::class );
		$ajax_handler->shouldReceive( 'add_error' )->with( $field['id'], 'The Captcha is invalid.' )->once();

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test validation.
	 */
	public function test_validation_with_empty_captcha() {
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
		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, null );

		$record = Mockery::mock( Form_Record::class );
		$record->shouldReceive( 'get_field' )->with( [ 'type' => 'hcaptcha' ] )->once()->andReturn( $fields );
		$record->shouldReceive( 'remove_field' )->never();

		$ajax_handler = Mockery::mock( Ajax_Handler::class );
		$ajax_handler->shouldReceive( 'add_error' )->with( $field['id'], 'The Captcha is invalid.' )->once();

		$subject = new HCaptchaHandler();
		$subject->validation( $record, $ajax_handler );
	}

	/**
	 * Test render_field.
	 */
	public function test_render_field() {
		$site_key = 'some api key';
		$theme    = 'some theme';
		$size     = 'some size';

		update_option( 'hcaptcha_api_key', $site_key );
		update_option( 'hcaptcha_theme', $theme );
		update_option( 'hcaptcha_size', $size );

		$custom_id         = '_014ea7c';
		$item['custom_id'] = $custom_id;
		$item_index        = 5;
		$render_attributes = [
			'hcaptcha' . $item_index => [
				'class'        => 'elementor-hcaptcha',
				'data-sitekey' => $site_key,
				'data-theme'   => $theme,
				'data-size'    => $size,
			],
		];
		$expected          = '<div class="elementor-field" id="form-field-_014ea7c"><div class="elementor-hcaptcha">	<div
			class="h-captcha"
			data-sitekey="some api key"
			data-theme="some theme"
			data-size="some size"
						data-auto="false">
	</div>
	</div></div>';

		$widget = Mockery::mock( Widget_Base::class );
		$widget->shouldReceive( 'add_render_attribute' )->with( $render_attributes )->once();

		$subject = new HCaptchaHandler();

		ob_start();
		$subject->render_field( $item, $item_index, $widget );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_field_type().
	 */
	public function test_add_field_type() {
		$field_types = [
			'text'      => 'text',
			'recaptcha' => 'reCaptcha',
		];

		$expected             = $field_types;
		$expected['hcaptcha'] = 'hCaptcha';

		$subject = new HCaptchaHandler();

		self::assertSame( $expected, $subject->add_field_type( $field_types ) );
	}

	/**
	 * Test modify_controls().
	 */
	public function test_modify_controls() {
		$args = [];

		$control_id  = 'form_fields';
		$unique_name = 'form';

		$control_data = [
			'type'        => 'form-fields-repeater',
			'tab'         => 'content',
			'section'     => 'section_form_fields',
			'fields'      =>
				[
					'_id'          =>
						[
							'type'    => 'hidden',
							'tab'     => 'content',
							'name'    => '_id',
							'default' => '',
						],
					'required'     =>
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
					'width'        =>
						[
							'type'         => 'select',
							'tab'          => 'content',
							'label'        => 'Column Width',
							'options'      =>
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
							'conditions'   =>
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
							'responsive'   =>
								[
									'max' => 'desktop',
								],
							'parent'       => null,
							'default'      => '100',
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'width',
							'inheritors'   =>
								[
									'width_tablet',
								],
						],
					'width_tablet' =>
						[
							'type'         => 'select',
							'tab'          => 'content',
							'label'        => 'Column Width',
							'options'      =>
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
							'conditions'   =>
								[
									'terms' =>
										[
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'hidden', 'recaptcha', 'step', 'recaptcha_v3' ],
											],
										],
								],
							'responsive'   =>
								[
									'max' => 'tablet',
								],
							'parent'       => 'width',
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'width_tablet',
							'default'      => '',
							'inheritors'   =>
								[
									'width_mobile',
								],
						],
					'width_mobile' =>
						[
							'type'         => 'select',
							'tab'          => 'content',
							'label'        => 'Column Width',
							'options'      =>
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
							'conditions'   =>
								[
									'terms' =>
										[
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'hidden', 'recaptcha', 'recaptcha_v3', 'step' ],
											],
										],
								],
							'responsive'   =>
								[
									'max' => 'mobile',
								],
							'parent'       => 'width_tablet',
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'width_mobile',
							'default'      => '',
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
					'_id'          =>
						[
							'type'    => 'hidden',
							'tab'     => 'content',
							'name'    => '_id',
							'default' => '',
						],
					'required'     =>
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
					'width'        =>
						[
							'type'         => 'select',
							'tab'          => 'content',
							'label'        => 'Column Width',
							'options'      =>
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
							'conditions'   =>
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
							'responsive'   =>
								[
									'max' => 'desktop',
								],
							'parent'       => null,
							'default'      => '100',
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'width',
							'inheritors'   =>
								[
									'width_tablet',
								],
						],
					'width_tablet' =>
						[
							'type'         => 'select',
							'tab'          => 'content',
							'label'        => 'Column Width',
							'options'      =>
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
							'conditions'   =>
								[
									'terms' =>
										[
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    =>
													[ 'hidden', 'recaptcha', 'step', 'recaptcha_v3' ],
											],
											[
												'name'     => 'field_type',
												'operator' => '!in',
												'value'    => [ 'hcaptcha' ],
											],
										],
								],
							'responsive'   =>
								[
									'max' => 'tablet',
								],
							'parent'       => 'width',
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'width_tablet',
							'default'      => '',
							'inheritors'   =>
								[
									'width_mobile',
								],
						],
					'width_mobile' =>
						[
							'type'         => 'select',
							'tab'          => 'content',
							'label'        => 'Column Width',
							'options'      =>
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
							'conditions'   =>
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
												'value'    => [ 'hcaptcha' ],
											],
										],
								],
							'responsive'   =>
								[
									'max' => 'mobile',
								],
							'parent'       => 'width_tablet',
							'tabs_wrapper' => 'form_fields_tabs',
							'inner_tab'    => 'form_fields_content_tab',
							'name'         => 'width_mobile',
							'default'      => '',
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
	public function test_filter_field_item() {
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
}
