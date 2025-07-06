<?php
/**
 * MainTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit;

use HCaptcha\Main;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Main class.
 *
 * @group main
 */
class MainTest extends HCaptchaTestCase {

	/**
	 * Test declare_wc_compatibility().
	 *
	 * @return void
	 */
	public function test_declare_wc_compatibility(): void {
		$mock = Mockery::mock( 'alias:Automattic\WooCommerce\Utilities\FeaturesUtil' );
		$mock->shouldReceive( 'declare_compatibility' )
			->with( 'custom_order_tables', HCAPTCHA_TEST_FILE )
			->andReturn( true );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				if ( 'HCAPTCHA_FILE' === $name ) {
					return HCAPTCHA_TEST_FILE;
				}

				return '';
			}
		);

		$subject = new Main();
		$subject->declare_wc_compatibility();
	}
}
