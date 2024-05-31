<?php
/**
 * SettingsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\Stubs\Settings\GeneralStub;
use HCaptcha\Tests\Unit\Stubs\Settings\IntegrationsStub;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use WP_Mock;

/**
 * Class SettingsTest
 *
 * @group settings
 * @group settings-main
 */
class SettingsTest extends HCaptchaTestCase {

	/**
	 * Test constructor.
	 *
	 * @param array|null $menu_groups Menu pages classes.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @dataProvider dp_test_constructor
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_constructor( $menu_groups ) {
		$class_name = Settings::class;

		$subject = Mockery::mock( $class_name )->makePartial()->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'init' )->once();

		$constructor = ( new ReflectionClass( $class_name ) )->getConstructor();

		self::assertNotNull( $constructor );

		if ( null === $menu_groups ) {
			$menu_groups = [];

			$constructor->invoke( $subject );
		} else {
			$constructor->invoke( $subject, $menu_groups );
		}

		self::assertSame( $menu_groups, $this->get_protected_property( $subject, 'menu_groups' ) );
	}

	/**
	 * Data provider for test_constructor
	 *
	 * @return array
	 */
	public function dp_test_constructor(): array {
		return [
			[ null ],
			[ [] ],
			[ [ General::class, Integrations::class ] ],
		];
	}

	/**
	 * Test init().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init() {
		$subject = Mockery::mock( Settings::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'init';

		$menu_groups = [
			'hCaptcha' => [
				'classes' => [ GeneralStub::class, IntegrationsStub::class ],
			],
		];

		$this->set_protected_property( $subject, 'menu_groups', $menu_groups );

		$subject->$method();

		foreach ( $this->get_protected_property( $subject, 'tabs' ) as $key => $tab ) {
			self::assertInstanceOf( $menu_groups['hCaptcha']['classes'][ $key ], $tab );
		}
	}

	/**
	 * Test get_tabs().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_tabs() {
		$general      = Mockery::mock( General::class )->makePartial();
		$integrations = Mockery::mock( Integrations::class )->makePartial();

		$subject = Mockery::mock( Settings::class )->makePartial();

		$tabs = [ $general, $integrations ];
		$this->set_protected_property( $subject, 'tabs', $tabs );

		self::assertSame( $tabs, $subject->get_tabs() );
	}

	/**
	 * Test get_active_tab_name().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_active_tab_name() {
		$general_tab_name = General::class;
		$general          = Mockery::mock( $general_tab_name )->makePartial();
		$integrations     = Mockery::mock( Integrations::class )->makePartial();

		$general->shouldReceive( 'get_active_tab' )->andReturn( $general );
		$general->shouldReceive( 'tab_name' )->andReturn( $general_tab_name );
		$subject = Mockery::mock( Settings::class )->makePartial();

		$tabs = [ $general, $integrations ];
		$this->set_protected_property( $subject, 'tabs', $tabs );

		self::assertSame( $general_tab_name, $subject->get_active_tab_name() );
	}

	/**
	 * Test get_tabs_names().
	 *
	 * @return void
	 */
	public function test_is_pro() {
		$license = 'free';

		$subject = Mockery::mock( Settings::class )->makePartial();
		$subject->shouldReceive( 'get_license' )
			->with()->andReturnUsing(
				static function () use ( &$license ) {
					return $license;
				}
			);

		self::assertFalse( $subject->is_pro() );

		$license = 'pro';

		self::assertTrue( $subject->is_pro() );
	}

	/**
	 * Test is_pro_or_general().
	 *
	 * @param bool $is_pro     Is pro.
	 * @param bool $is_general Is general.
	 * @param bool $is_admin   Is admin.
	 * @param bool $expected   Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_is_pro_or_general
	 */
	public function test_is_pro_or_general( bool $is_pro, bool $is_general, bool $is_admin, bool $expected ) {
		$active_tab_name = $is_general ? 'General' : 'some';

		$subject = Mockery::mock( Settings::class )->makePartial();
		$subject->shouldReceive( 'is_pro' )->andReturn( $is_pro );
		$subject->shouldReceive( 'get_active_tab_name' )->andReturn( $active_tab_name );

		WP_Mock::userFunction( 'is_admin' )->andReturn( $is_admin );

		self::assertSame( $subject->is_pro_or_general(), $expected );
	}

	/**
	 * Data provider for test_is_pro_or_general().
	 *
	 * @return array
	 */
	public function dp_test_is_pro_or_general(): array {
		return [
			[ true, true, true, true ],
			[ true, true, false, true ],
			[ true, false, true, true ],
			[ true, false, false, true ],
			[ false, true, true, true ],
			[ false, true, false, false ],
			[ false, false, true, false ],
			[ false, false, false, false ],
		];
	}

	/**
	 * Test get_config_params().
	 *
	 * @param mixed $config_params Config params.
	 * @param array $expected      Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_get_config_params
	 */
	public function test_get_config_params( $config_params, array $expected ) {
		$subject = Mockery::mock( Settings::class )->makePartial();
		$subject->shouldReceive( 'get' )->with( 'config_params' )->andReturn( $config_params );

		self::assertSame( $expected, $subject->get_config_params( $config_params ) );
	}

	/**
	 * Data provider for test_get_config_params().
	 *
	 * @return array
	 */
	public function dp_test_get_config_params(): array {
		return [
			[ null, [] ],
			[ '', [] ],
			[ 'some string', [] ],
			[ '{"some":"object"}', [ 'some' => 'object' ] ],
		];
	}

	/**
	 * Test get().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get() {
		$general_key        = 'some general key';
		$general_value      = 'some general value';
		$integrations_key   = 'some integrations key';
		$integrations_value = 'some integrations value';

		$general = Mockery::mock( General::class )->makePartial();
		$general->shouldReceive( 'get' )->andReturnUsing(
			function ( $key, $empty_value ) use ( $general_key, &$general_value ) {
				if ( $key === $general_key ) {
					return $general_value;
				}

				if ( ! is_null( $empty_value ) ) {
					return $empty_value;
				}

				return '';
			}
		);

		$integrations = Mockery::mock( Integrations::class )->makePartial();
		$integrations->shouldReceive( 'get' )->andReturnUsing(
			function ( $key, $empty_value ) use ( $integrations_key, $integrations_value ) {
				if ( $key === $integrations_key ) {
					return $integrations_value;
				}

				if ( ! is_null( $empty_value ) ) {
					return $empty_value;
				}

				return '';
			}
		);

		$general->shouldReceive( 'get_tabs' )->andReturn( [ $integrations ] );
		$integrations->shouldReceive( 'get_tabs' )->andReturn( null );

		$menu_groups = [
			'hCaptcha' => [ General::class, Integrations::class ],
		];

		$subject = Mockery::mock( Settings::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$tabs = [ $general, $integrations ];
		$this->set_protected_property( $subject, 'tabs', $tabs );
		$this->set_protected_property( $subject, 'menu_groups', $menu_groups );

		self::assertSame( $general_value, $subject->get( $general_key ) );
		self::assertSame( $integrations_value, $subject->get( $integrations_key ) );
		self::assertSame( '', $subject->get( 'non-existent key' ) );

		$empty_value = 'empty value';
		self::assertSame( $empty_value, $subject->get( 'non-existent key', $empty_value ) );

		$general_value = '';
		$empty_value   = '';
		self::assertSame( $empty_value, $subject->get( $general_key, $empty_value ) );
	}

	/**
	 * Test set().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_set() {
		$general_key      = 'some general key';
		$integrations_key = 'some integrations key';

		$general = Mockery::mock( General::class )->makePartial();
		$general->shouldReceive( 'set' )->andReturnUsing(
			function ( $key, $value ) use ( $general_key ) {
				return $key === $general_key;
			}
		);

		$integrations = Mockery::mock( Integrations::class )->makePartial();
		$integrations->shouldReceive( 'set' )->andReturnUsing(
			function ( $key, $value ) use ( $integrations_key ) {
				return $key === $integrations_key;
			}
		);

		$subject = Mockery::mock( Settings::class )->makePartial();

		self::assertFalse( $subject->set( $general_key, 'some value' ) );

		$this->set_protected_property( $subject, 'tabs', [ $general, $integrations ] );

		self::assertTrue( $subject->set( $general_key, 'some value' ) );
		self::assertFalse( $subject->set( 'unknown key', 'some value' ) );

		self::assertTrue( $subject->set( $integrations_key, 'some value' ) );
		self::assertFalse( $subject->set( 'unknown key', 'some value' ) );
	}

	/**
	 * Test is().
	 *
	 * @param string       $key      Option key.
	 * @param string|array $value    Option value.
	 * @param string       $compare  String to compare with value.
	 * @param bool         $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_is
	 */
	public function test_is( string $key, $value, string $compare, bool $expected ) {
		$subject = Mockery::mock( Settings::class )->makePartial();
		$subject->shouldReceive( 'get' )->with( $key )->andReturn( $value );

		self::assertSame( $expected, $subject->is( $key, $compare ) );
	}

	/**
	 * Data provider for test_is().
	 *
	 * @return array[]
	 */
	public function dp_test_is(): array {
		return [
			[ 'some key', 'some value', 'some value', true ],
			[ 'some key', 'some value', 'not same value', false ],
			[ 'some key', [ 'some array value' ], 'some array value', true ],
			[ 'some key', [ 'some array value' ], 'not same array value', false ],
		];
	}

	/**
	 * Test is_on().
	 *
	 * @param string       $key   Key.
	 * @param string|array $value Value.
	 *
	 * @return void
	 * @dataProvider dp_test_is_on
	 */
	public function test_is_on( string $key, $value ) {
		$subject = Mockery::mock( Settings::class )->makePartial();
		$subject->shouldReceive( 'get' )->with( $key )->andReturn( $value );

		$expected = ! empty( $value );
		self::assertSame( $expected, $subject->is_on( $key ) );
	}

	/**
	 * Data provider for test_is_on().
	 *
	 * @return array
	 */
	public function dp_test_is_on(): array {
		return [
			[ 'some key', '' ],
			[ 'some key', 'some value' ],
			[ 'some key', [] ],
			[ 'some key', [ 'some value' ] ],
		];
	}

	/**
	 * Test get keys().
	 *
	 * @param string $mode     Mode.
	 * @param array  $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_get_keys
	 */
	public function test_get_keys( string $mode, array $expected ) {
		$subject = Mockery::mock( Settings::class )->makePartial();
		$subject->shouldReceive( 'get' )->with( 'mode' )->andReturn( $mode );

		if ( General::MODE_LIVE === $mode ) {
			$subject->shouldReceive( 'get' )->with( 'site_key' )->andReturn( $expected['site_key'] );
			$subject->shouldReceive( 'get' )->with( 'secret_key' )->andReturn( $expected['secret_key'] );
		}

		self::assertSame( $expected['site_key'], $subject->get_site_key() );
		self::assertSame( $expected['secret_key'], $subject->get_secret_key() );
	}

	/**
	 * Data provider for test_get_keys().
	 *
	 * @return array
	 */
	public function dp_test_get_keys(): array {
		// String concat is used for the PHP 5.6 compatibility.
		// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
		return [
			[
				General::MODE_LIVE,
				[
					'site_key'   => 'some site key',
					'secret_key' => 'some secret key',
				],
			],
			[
				General::MODE_TEST_PUBLISHER,
				[
					'site_key'   => '10000000-ffff-ffff-ffff-000000000001',
					'secret_key' => '0' . 'x' . '0000000000000000000000000000000000000000',
				],
			],
			[
				General::MODE_TEST_ENTERPRISE_SAFE_END_USER,
				[
					'site_key'   => '20000000-ffff-ffff-ffff-000000000002',
					'secret_key' => '0' . 'x' . '0000000000000000000000000000000000000000',
				],
			],
			[
				General::MODE_TEST_ENTERPRISE_BOT_DETECTED,
				[
					'site_key'   => '30000000-ffff-ffff-ffff-000000000003',
					'secret_key' => '0' . 'x' . '0000000000000000000000000000000000000000',
				],
			],
			[
				'wrong mode',
				[
					'site_key'   => '',
					'secret_key' => '',
				],
			],
		];
		// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found
	}

	/**
	 * Test get_site_key().
	 */
	public function test_get_site_key() {
		$site_key   = 'some site key';
		$secret_key = 'some secret key';
		$subject    = Mockery::mock( Settings::class )->makePartial();

		$subject->shouldReceive( 'get' )->with( 'mode' )->andReturn( 'live' );
		$subject->shouldReceive( 'get' )->with( 'site_key' )->andReturn( $site_key );
		$subject->shouldReceive( 'get' )->with( 'secret_key' )->andReturn( $secret_key );

		WP_Mock::expectFilter( 'hcap_site_key', $site_key );

		self::assertSame( $site_key, $subject->get_site_key() );
	}

	/**
	 * Test get_secret_key().
	 */
	public function test_get_secret_key() {
		$site_key   = 'some site key';
		$secret_key = 'some secret key';
		$subject    = Mockery::mock( Settings::class )->makePartial();

		$subject->shouldReceive( 'get' )->with( 'mode' )->andReturn( 'live' );
		$subject->shouldReceive( 'get' )->with( 'site_key' )->andReturn( $site_key );
		$subject->shouldReceive( 'get' )->with( 'secret_key' )->andReturn( $secret_key );

		WP_Mock::expectFilter( 'hcap_secret_key', $secret_key );

		self::assertSame( $secret_key, $subject->get_secret_key() );
	}

	/**
	 * Test get_theme().
	 *
	 * @param bool $is_custom         Is custom.
	 * @param bool $is_pro_or_general Is pro or general.
	 *
	 * @return void
	 * @dataProvider dp_test_get_theme
	 */
	public function test_get_theme( bool $is_custom, bool $is_pro_or_general ) {
		$theme         = 'some theme';
		$config_theme  = 'some config theme';
		$config_params = [
			'theme' => [
				'palette' => [
					'mode' => $config_theme,
				],
			],
		];
		$expected      = $theme;

		$subject = Mockery::mock( Settings::class )->makePartial();
		$subject->shouldReceive( 'get' )->with( 'theme' )->andReturn( $theme );
		$subject->shouldReceive( 'is_on' )->with( 'custom_themes' )->andReturn( $is_custom );
		$subject->shouldReceive( 'is_pro_or_general' )->with()->andReturn( $is_pro_or_general );

		if ( $is_custom && $is_pro_or_general ) {
			$subject->shouldReceive( 'get_config_params' )->with()->andReturn( $config_params );
			$expected = $config_theme;
		}

		WP_Mock::expectFilter( 'hcap_theme', $expected );

		self::assertSame( $expected, $subject->get_theme() );
	}

	/**
	 * Data provider for test_get_theme().
	 *
	 * @return array
	 */
	public function dp_test_get_theme(): array {
		return [
			[ false, false ],
			[ false, true ],
			[ true, false ],
			[ true, true ],
		];
	}

	/**
	 * Test get_language().
	 */
	public function test_get_language() {
		$language = 'some language';
		$subject  = Mockery::mock( Settings::class )->makePartial();

		$subject->shouldReceive( 'get' )->with( 'language' )->andReturn( $language );

		WP_Mock::expectFilter( 'hcap_language', $language );

		self::assertSame( $language, $subject->get_language() );
	}

	/**
	 * Test get_mode().
	 */
	public function test_get_mode() {
		$mode    = 'some mode';
		$subject = Mockery::mock( Settings::class )->makePartial();

		$subject->shouldReceive( 'get' )->with( 'mode' )->andReturn( $mode );

		WP_Mock::expectFilter( 'hcap_mode', $mode );

		self::assertSame( $mode, $subject->get_mode() );
	}

	/**
	 * Test get_license().
	 *
	 * @param string $license  Saved license.
	 * @param string $expected Expected license.
	 *
	 * @dataProvider dp_test_get_license
	 * @return void
	 */
	public function test_get_license( string $license, string $expected ) {
		$subject = Mockery::mock( Settings::class )->makePartial();

		$subject->shouldReceive( 'get' )->with( 'license' )->andReturn( $license );

		self::assertSame( $expected, $subject->get_license() );
	}

	/**
	 * Data provider for test_get_license().
	 *
	 * @return array
	 */
	public function dp_test_get_license(): array {
		return [
			[ 'free', 'free' ],
			[ 'pro', 'pro' ],
			[ 'enterprise', 'enterprise' ],
			[ 'wrong', 'free' ],
		];
	}

	/**
	 * Test get_default_theme().
	 */
	public function test_get_default_theme() {
		$expected = [
			'palette'   => [
				'mode'    => 'light',
				'grey'    => [
					100  => '#fafafa',
					200  => '#f5f5f5',
					300  => '#e0e0e0',
					400  => '#d7d7d7',
					500  => '#bfbfbf',
					600  => '#919191',
					700  => '#555555',
					800  => '#333333',
					900  => '#222222',
					1000 => '#14191f',
				],
				'primary' => [
					'main' => '#00838f',
				],
				'warn'    => [
					'main' => '#eb5757',
				],
				'text'    => [
					'heading' => '#555555',
					'body'    => '#555555',
				],
			],
			'component' => [
				'checkbox'     => [
					'main'  => [
						'fill'   => '#fafafa',
						'border' => '#e0e0e0',
					],
					'hover' => [
						'fill' => '#f5f5f5',
					],
				],
				'challenge'    => [
					'main'  => [
						'fill'   => '#fafafa',
						'border' => '#e0e0e0',
					],
					'hover' => [
						'fill' => '#fafafa',
					],
				],
				'modal'        => [
					'main'  => [
						'fill'   => '#ffffff',
						'border' => '#e0e0e0',
					],
					'hover' => [
						'fill' => '#f5f5f5',
					],
					'focus' => [
						'border' => '#0074bf',
					],
				],
				'breadcrumb'   => [
					'main'   => [
						'fill' => '#f5f5f5',
					],
					'active' => [
						'fill' => '#00838f',
					],
				],
				'button'       => [
					'main'   => [
						'fill' => '#ffffff',
						'icon' => '#555555',
						'text' => '#555555',
					],
					'hover'  => [
						'fill' => '#f5f5f5',
					],
					'focus'  => [
						'icon' => '#00838f',
						'text' => '#00838f',
					],
					'active' => [
						'fill' => '#f5f5f5',
						'icon' => '#555555',
						'text' => '#555555',
					],
				],
				'list'         => [
					'main' => [
						'fill'   => '#ffffff',
						'border' => '#d7d7d7',
					],
				],
				'listItem'     => [
					'main'     => [
						'fill' => '#ffffff',
						'line' => '#f5f5f5',
						'text' => '#555555',
					],
					'hover'    => [
						'fill' => '#f5f5f5',
					],
					'selected' => [
						'fill' => '#e0e0e0',
					],
				],
				'input'        => [
					'main'  => [
						'fill'   => '#fafafa',
						'border' => '#919191',
					],
					'focus' => [
						'fill'   => '#f5f5f5',
						'border' => '#333333',
					],
				],
				'radio'        => [
					'main'     => [
						'file'   => '#f5f5f5',
						'border' => '#919191',
						'check'  => '#f5f5f5',
					],
					'selected' => [
						'check' => '#00838f',
					],
				],
				'task'         => [
					'main'     => [
						'fill' => '#f5f5f5',
					],
					'selected' => [
						'border' => '#00838f',
					],
					'report'   => [
						'border' => '#eb5757',
					],
				],
				'prompt'       => [
					'main'   => [
						'fill'   => '#00838f',
						'border' => '#00838f',
						'text'   => '#ffffff',
					],
					'report' => [
						'fill'   => '#eb5757',
						'border' => '#eb5757',
						'text'   => '#ffffff',
					],
				],
				'skipButton'   => [
					'main'  => [
						'fill'   => '#919191',
						'border' => '#919191',
						'text'   => '#ffffff',
					],
					'hover' => [
						'fill'   => '#555555',
						'border' => '#919191',
						'text'   => '#ffffff',
					],
				],
				'verifyButton' => [
					'main'  => [
						'fill'   => '#00838f',
						'border' => '#00838f',
						'text'   => '#ffffff',
					],
					'hover' => [
						'fill'   => '#00838f',
						'border' => '#00838f',
						'text'   => '#ffffff',
					],
				],
				'expandButton' => [
					'main' => [
						'fill' => '#00838f',
					],
				],
				'slider'       => [
					'main'  => [
						'bar'    => '#c4c4c4',
						'handle' => '#0f8390',
					],
					'focus' => [
						'handle' => '#0f8390',
					],
				],
			],
		];

		$subject = Mockery::mock( Settings::class )->makePartial();

		self::assertSame( $expected, $subject->get_default_theme() );
	}

	/**
	 * Test set_field().
	 *
	 * @param array $has_field Tab has field.
	 * @param array $called    Tab set_field should be called.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @dataProvider dp_test_set_field
	 */
	public function test_set_field( array $has_field, array $called ) {
		$general      = Mockery::mock( General::class )->makePartial();
		$integrations = Mockery::mock( Integrations::class )->makePartial();
		$tabs         = empty( $has_field ) ? [] : [ $general, $integrations ];
		$subject      = Mockery::mock( Settings::class )->makePartial();

		$this->set_protected_property( $subject, 'tabs', $tabs );

		$key       = 'some key';
		$field_key = 'some field key';
		$value     = 'some value';

		foreach ( $tabs as $index => $tab ) {
			if ( array_key_exists( $index, $called ) && $called[ $index ] ) {
				$tab->shouldReceive( 'set_field' )->with( $key, $field_key, $value )->once()
					->andReturn( $has_field[ $index ] );
			} else {
				$tab->shouldReceive( 'set_field' )->never();
			}
		}

		$subject->set_field( $key, $field_key, $value );
	}

	/**
	 * Data provide for test_set_field().
	 *
	 * @return array
	 */
	public function dp_test_set_field(): array {
		return [
			[
				[],
				[],
			],
			[
				[ true, true ],
				[ true, false ],
			],
			[
				[ true, false ],
				[ true, false ],
			],
			[
				[ false, true ],
				[ true, true ],
			],
			[
				[ false, false ],
				[ true, true ],
			],
		];
	}
}
