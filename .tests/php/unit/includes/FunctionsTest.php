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

use HCaptcha\Tests\Unit\HCaptchaTestCase;
use tad\FunctionMocker\FunctionMocker;
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
	public function test_hcap_shortcode( array $atts, array $expected ) {
		$pairs = [
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => false,
			'size'   => '',
		];
		$form  = 'some hcaptcha form content';

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
			'empty atts' => [
				[],
				[
					'action' => HCAPTCHA_ACTION,
					'name'   => HCAPTCHA_NONCE,
					'auto'   => false,
					'size'   => '',
				],
			],
			'auto truly' => [
				[
					'auto' => '1',
				],
				[
					'action' => HCAPTCHA_ACTION,
					'name'   => HCAPTCHA_NONCE,
					'auto'   => true,
					'size'   => '',
				],
			],
			'some atts'  => [
				[
					'some' => 'some attribute',
				],
				[
					'action' => HCAPTCHA_ACTION,
					'name'   => HCAPTCHA_NONCE,
					'auto'   => false,
					'size'   => '',
					'some'   => 'some attribute',
				],
			],
		];
	}

	/**
	 * Test hcap_min_suffix().
	 *
	 * @return void
	 */
	public function test_hcap_min_suffix() {
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
