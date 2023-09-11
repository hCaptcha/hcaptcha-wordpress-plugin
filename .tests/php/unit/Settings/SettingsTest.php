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
	 * @param array|null $menu_pages_classes Menu pages classes.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @dataProvider dp_test_constructor
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_constructor( $menu_pages_classes ) {
		$class_name = Settings::class;

		$subject = Mockery::mock( $class_name )->makePartial()->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'init' )->once();

		$constructor = ( new ReflectionClass( $class_name ) )->getConstructor();

		self::assertNotNull( $constructor );

		if ( null === $menu_pages_classes ) {
			$menu_pages_classes = [];

			$constructor->invoke( $subject );
		} else {
			$constructor->invoke( $subject, $menu_pages_classes );
		}

		self::assertSame( $menu_pages_classes, $this->get_protected_property( $subject, 'menu_pages_classes' ) );
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

		$expected = [ GeneralStub::class, IntegrationsStub::class ];
		$method   = 'init';

		$this->set_protected_property( $subject, 'menu_pages_classes', $expected );

		$subject->$method();

		foreach ( $this->get_protected_property( $subject, 'tabs' ) as $key => $tab ) {
			self::assertInstanceOf( $expected[ $key ], $tab );
		}
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

		$general = Mockery::mock( General::class );
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

		$integrations = Mockery::mock( Integrations::class );
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

		$menu_pages_classes = [
			'hCaptcha' => [ General::class, Integrations::class ],
		];

		$subject = Mockery::mock( Settings::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$tabs = [ $general, $integrations ];
		$this->set_protected_property( $subject, 'tabs', $tabs );
		$this->set_protected_property( $subject, 'menu_pages_classes', $menu_pages_classes );

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
		$general      = Mockery::mock( General::class );
		$integrations = Mockery::mock( Integrations::class );
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
