<?php
/**
 * ButtonTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\WooCommercePayPalPayments;

use HCaptcha\Tests\Unit\HCaptchaTestCase;
use HCaptcha\WooCommercePayPalPayments\Button;
use ReflectionClass;
use ReflectionException;

/**
 * Class ButtonTest
 *
 * @group woocommerce-paypal-payments
 */
class ButtonTest extends HCaptchaTestCase {

	/**
	 * Test disable_recaptcha().
	 *
	 * @param mixed $settings Settings.
	 * @param array $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_disable_recaptcha
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_disable_recaptcha( $settings, array $expected ): void {
		$subject = ( new ReflectionClass( Button::class ) )->newInstanceWithoutConstructor();

		self::assertSame( $expected, $subject->disable_recaptcha( $settings ) );
	}

	/**
	 * Data provider for test_disable_recaptcha().
	 *
	 * @return array
	 */
	public function dp_test_disable_recaptcha(): array {
		return [
			'not array'       => [
				false,
				[
					'enabled' => 'no',
				],
			],
			'enabled setting' => [
				[
					'enabled'     => 'yes',
					'site_key_v3' => 'v3-key',
					'site_key_v2' => 'v2-key',
				],
				[
					'enabled'     => 'no',
					'site_key_v3' => 'v3-key',
					'site_key_v2' => 'v2-key',
				],
			],
		];
	}
}
