<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Divi;

use HCaptcha\Divi\Login;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionException;
use WP_Block;

/**
 * Class LoginTest.
 *
 * @group divi
 */
class LoginTest extends HCaptchaPluginWPTestCase {

	/**
	 * Theme stylesheet slug.
	 *
	 * @var string
	 */
	protected static string $theme = 'Divi';

	/**
	 * Live Divi shortcode modules used by the test.
	 *
	 * @var array<string, string>
	 */
	protected static array $theme_shortcode_classes = [
		'et_pb_login' => 'ET_Builder_Module_Login',
	];

	/**
	 * Expected incorrect usage notices caused by the late theme load.
	 *
	 * @var string[]
	 */
	protected static array $theme_expected_incorrect_usage = [ "add_theme_support( 'title-tag' )" ];

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Login();

		self::assertSame( 10, has_filter( Login::TAG . '_shortcode_output', [ $subject, 'add_hcaptcha_to_shortcode' ] ) );
	}

	/**
	 * Test the live Divi Login module.
	 *
	 * @return void
	 */
	public function test_live_login_module(): void {
		wp_set_current_user( 0 );
		$this->enable_login_integration();

		new Login();

		$output = do_shortcode( '[et_pb_login title="Login"][/et_pb_login]' );

		self::assertStringContainsString( 'et_pb_login_form', $output );
		self::assertStringContainsString( 'name="log"', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_login_nonce"', $output );
		self::assertStringContainsString( 'class="hcaptcha-signature"', $output );
	}

	/**
	 * Test the live Divi frontend builder state.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_add_hcaptcha_in_frontend_builder(): void {
		$output = 'some string';

		add_filter( 'et_fb_is_enabled', '__return_true' );

		$subject = new Login();

		self::assertTrue( et_core_is_fb_enabled() );
		self::assertSame( $output, $subject->add_hcaptcha_to_shortcode( $output, Login::TAG ) );
	}

	/**
	 * Test add_hcaptcha_to_shortcode() when the login limit is not exceeded.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_when_login_limit_is_not_exceeded(): void {
		$output = 'some string';

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$subject = new Login();

		self::assertSame( $output, $subject->add_hcaptcha_to_shortcode( $output, Login::TAG ) );
	}

	/**
	 * Test add_hcaptcha_to_block() with output from the live Login module.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_to_block(): void {
		wp_set_current_user( 0 );
		$this->enable_login_integration();

		$output = do_shortcode( '[et_pb_login title="Login"][/et_pb_login]' );

		self::assertStringNotContainsString( '<h-captcha', $output );

		$subject = new Login();
		$block   = new WP_Block(
			[
				'blockName'    => 'divi/login',
				'attrs'        => [],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			]
		);

		self::assertSame(
			$output,
			$subject->add_hcaptcha_to_block( $output, [ 'blockName' => 'core/paragraph' ], $block )
		);

		$output = $subject->add_hcaptcha_to_block( $output, [ 'blockName' => 'divi/login' ], $block );

		self::assertStringContainsString( 'et_pb_login_form', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_login_nonce"', $output );
	}

	/**
	 * Test get_active_divi_component() with the live theme.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_active_divi_component(): void {
		$subject = new Login();
		$method  = $this->set_method_accessibility( $subject, 'get_active_divi_component' );

		self::assertSame( 'Divi', get_template() );
		self::assertSame( 'divi', $method->invoke( $subject ) );
	}

	/**
	 * Enable the Divi Login integration.
	 *
	 * @return void
	 */
	private function enable_login_integration(): void {
		update_option( 'hcaptcha_settings', [ 'divi_status' => [ 'login' ] ] );
		hcaptcha()->init_hooks();
	}
}
