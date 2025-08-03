<?php
/**
 * FunctionsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

// phpcs:disable PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound

namespace HCaptcha\Tests\Unit\includes;

use HCaptcha\Main;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use tad\FunctionMocker\FunctionMocker;
use Mockery;
use WP_Mock;

/**
 * Test functions file.
 *
 * @group functions
 */
class FunctionsTest extends HCaptchaTestCase {

	/**
	 * Setup test class.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		WP_Mock::userFunction( 'add_shortcode' )->with( 'hcaptcha', 'hcap_shortcode' )->once();

		require_once PLUGIN_PATH . '/src/php/includes/functions.php';
	}

	/**
	 * Test hcap_shortcode().
	 *
	 * @param array $atts     Attributes.
	 * @param array $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_hcap_shortcode
	 */
	public function test_hcap_shortcode( array $atts, array $expected ): void {
		$pairs = [
			'action'  => HCAPTCHA_ACTION,
			'name'    => HCAPTCHA_NONCE,
			'auto'    => false,
			'ajax'    => false,
			'force'   => false,
			'theme'   => 'light',
			'size'    => 'normal',
			'id'      => [],
			'protect' => true,
		];
		$form  = 'some hcaptcha form content';

		$main     = Mockery::mock( Main::class )->makePartial();
		$settings = Mockery::mock( Settings::class )->makePartial();

		$main->shouldReceive( 'settings' )->andReturn( $settings );
		$settings->shouldReceive( 'is_on' )->with( 'force' )->andReturn( false );
		$settings->shouldReceive( 'get_theme' )->with()->andReturn( 'light' );
		$settings->shouldReceive( 'get' )->with( 'size' )->andReturn( 'normal' );

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );

		WP_Mock::userFunction( 'shortcode_atts' )
			->with( $pairs, $atts )
			->andReturnUsing(
				static function ( $pairs, $atts ) {
					return array_merge( $pairs, $atts );
				}
			);

		$hcap_form = FunctionMocker::replace(
			'\HCaptcha\Helpers\HCaptcha::form',
			static function () use ( $form ) {
				return $form;
			}
		);

		self::assertSame( $form, hcap_shortcode( $atts ) );

		$hcap_form->wasCalledWithOnce( [ $expected ] );
	}

	/**
	 * Data provider for test_hcap_shortcode().
	 *
	 * @return array
	 */
	public function dp_test_hcap_shortcode(): array {
		return [
			'empty atts'  => [
				[],
				[
					'action'  => HCAPTCHA_ACTION,
					'name'    => HCAPTCHA_NONCE,
					'auto'    => false,
					'ajax'    => false,
					'force'   => false,
					'theme'   => 'light',
					'size'    => 'normal',
					'id'      => [],
					'protect' => true,
				],
			],
			'auto truly'  => [
				[
					'auto' => '1',
				],
				[
					'action'  => HCAPTCHA_ACTION,
					'name'    => HCAPTCHA_NONCE,
					'auto'    => '1',
					'ajax'    => false,
					'force'   => false,
					'theme'   => 'light',
					'size'    => 'normal',
					'id'      => [],
					'protect' => true,
				],
			],
			'force truly' => [
				[
					'force' => true,
				],
				[
					'action'  => HCAPTCHA_ACTION,
					'name'    => HCAPTCHA_NONCE,
					'auto'    => false,
					'ajax'    => false,
					'force'   => true,
					'theme'   => 'light',
					'size'    => 'normal',
					'id'      => [],
					'protect' => true,
				],
			],
			'some atts'   => [
				[
					'some' => 'some attribute',
				],
				[
					'action'  => HCAPTCHA_ACTION,
					'name'    => HCAPTCHA_NONCE,
					'auto'    => false,
					'ajax'    => false,
					'force'   => false,
					'theme'   => 'light',
					'size'    => 'normal',
					'id'      => [],
					'protect' => true,
					'some'    => 'some attribute',
				],
			],
		];
	}

	/**
	 * Test hcap_min_suffix().
	 *
	 * @return void
	 */
	public function test_hcap_min_suffix(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) use ( &$script_debug ) {
				if ( 'SCRIPT_DEBUG' === $constant_name ) {
					return $script_debug;
				}

				return false;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( &$script_debug ) {
				if ( 'SCRIPT_DEBUG' === $name ) {
					return $script_debug;
				}

				return false;
			}
		);

		$script_debug = false;

		self::assertSame( '.min', hcap_min_suffix() );

		$script_debug = true;

		self::assertSame( '', hcap_min_suffix() );
	}
}
