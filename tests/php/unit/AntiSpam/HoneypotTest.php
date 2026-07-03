<?php
/**
 * HoneypotTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\AntiSpam;

use HCaptcha\AntiSpam\Honeypot;
use HCaptcha\Main;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use WP_Mock;

/**
 * Test Honeypot class.
 *
 * @group antispam
 * @group honeypot
 */
class HoneypotTest extends HCaptchaTestCase {

	/**
	 * Test get_protected_forms() without settings.
	 */
	public function test_get_protected_forms_without_settings(): void {
		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( null );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		self::assertSame(
			[
				'honeypot' => [],
				'fst'      => [],
			],
			Honeypot::get_protected_forms()
		);
	}

	/**
	 * Test get_protected_forms().
	 *
	 * @param bool $honeypot Honeypot status.
	 * @param bool $fst      Form submit time status.
	 *
	 * @dataProvider dp_test_get_protected_forms
	 */
	public function test_get_protected_forms( bool $honeypot, bool $fst ): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'is_on' )->with( 'honeypot' )->once()->andReturn( $honeypot );
		$settings->shouldReceive( 'is_on' )->with( 'set_min_submit_time' )->once()->andReturn( $fst );

		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		$result = Honeypot::get_protected_forms();

		self::assertSame( $honeypot, [] !== $result['honeypot'] );
		self::assertSame( $fst, [] !== $result['fst'] );
		self::assertSame( $honeypot, isset( $result['honeypot']['wp_status'] ) );
		self::assertSame( $fst, isset( $result['fst']['wp_status'] ) );
	}

	/**
	 * Data provider for test_get_protected_forms().
	 *
	 * @return array
	 */
	public function dp_test_get_protected_forms(): array {
		return [
			'both off'      => [ false, false ],
			'honeypot only' => [ true, false ],
			'fst only'      => [ false, true ],
			'both on'       => [ true, true ],
		];
	}
}
