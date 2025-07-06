<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UM;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\UM\Base;
use HCaptcha\UM\Login;
use Mockery;

/**
 * Class BaseTest.
 *
 * @group um-base
 * @group um
 */
class BaseTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ultimate-member/ultimate-member.php';

	/**
	 * Tear down the test.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		UM()->form()->errors = null;

		parent::tearDown();
	}

	/**
	 * Test set_form_id().
	 */
	public function test_set_form_id(): void {
		$subject = Mockery::mock( Base::class )->makePartial();

		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );

		// No form id set.
		$subject->set_form_id( [] );

		self::assertSame( 0, $this->get_protected_property( $subject, 'form_id' ) );

		// Form id set.
		$args = [ 'form_id' => '123' ];

		$subject->set_form_id( $args );
	}
}
