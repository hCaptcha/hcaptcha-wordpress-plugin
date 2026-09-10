<?php
/**
 * CheckoutTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\PaidMembershipsPro;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\PaidMembershipsPro\Checkout;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionFunction;

/**
 * Test Checkout class.
 *
 * @group paid-memberships-pro
 */
class CheckoutTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'paid-memberships-pro/paid-memberships-pro.php';

	/**
	 * Hooks to replay after loading Paid Memberships Pro.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['pmpro_msg'], $GLOBALS['pmpro_msgt'] );
		unset( $_REQUEST['submit-checkout'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Checkout();

		self::assertSame(
			10,
			has_action( 'pmpro_checkout_before_submit_button', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'pmpro_checkout_after_parameters_set', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$subject = new Checkout();

		ob_start();
		$subject->add_captcha();
		$output = ob_get_clean();

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( 'hcaptcha_pmpro_checkout_nonce', $output );
	}

	/**
	 * Test hCaptcha in the live Paid Memberships Pro checkout template.
	 *
	 * @return void
	 */
	public function test_live_checkout_form(): void {
		global $pmpro_level, $wpdb;

		pmpro_db_delta();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Create a PMPro membership level fixture.
		$wpdb->insert(
			$wpdb->pmpro_membership_levels,
			[
				'name'            => 'Integration Level',
				'description'     => 'Live Paid Memberships Pro checkout.',
				'confirmation'    => '',
				'initial_payment' => 0,
				'billing_amount'  => 0,
				'cycle_number'    => 0,
				'cycle_period'    => 'Month',
				'billing_limit'   => 0,
				'trial_amount'    => 0,
				'trial_limit'     => 0,
				'allow_signups'   => 1,
			],
			[ '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%d', '%f', '%d', '%d' ]
		);

		$level_id = (int) $wpdb->insert_id;

		$pmpro_level = pmpro_getLevel( $level_id );

		$subject = new Checkout();

		$template = new ReflectionFunction( 'pmpro_loadTemplate' );

		$html = pmpro_loadTemplate( 'checkout', 'local', 'pages' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/paid-memberships-pro/' ),
			wp_normalize_path( (string) $template->getFileName() )
		);
		self::assertStringContainsString( 'id="pmpro_form"', $html );
		self::assertStringContainsString( 'name="pmpro_level" value="' . $level_id . '"', $html );
		self::assertStringContainsString( 'name="hcaptcha_pmpro_checkout_nonce"', $html );
		self::assertSame( 10, has_action( 'pmpro_checkout_before_submit_button', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		global $pmpro_msg, $pmpro_msgt;

		$_REQUEST['submit-checkout'] = '1';

		$this->prepare_verify_post( 'hcaptcha_pmpro_checkout_nonce', 'hcaptcha_pmpro_checkout' );
		$this->prepare_widget_id();

		$subject = new Checkout();

		$subject->verify();

		self::assertNull( $pmpro_msg );
		self::assertNull( $pmpro_msgt );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		global $pmpro_msg, $pmpro_msgt;

		$_REQUEST['submit-checkout'] = '1';

		$this->prepare_verify_post( 'hcaptcha_pmpro_checkout_nonce', 'hcaptcha_pmpro_checkout', false );
		$this->prepare_widget_id();

		$subject = new Checkout();

		$subject->verify();

		self::assertNotEmpty( $pmpro_msg );
		self::assertSame( 'pmpro_error', $pmpro_msgt );
	}

	/**
	 * Test verify() when a checkout form was not submitted.
	 *
	 * @return void
	 */
	public function test_verify_not_submitted(): void {
		global $pmpro_msg, $pmpro_msgt;

		$subject = new Checkout();

		$subject->verify();

		self::assertNull( $pmpro_msg );
		self::assertNull( $pmpro_msgt );
	}
	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'paid-memberships-pro/paid-memberships-pro.php' ],
				'form_id' => 'checkout',
			]
		);
	}
}
