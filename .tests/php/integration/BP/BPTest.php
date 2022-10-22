<?php
/**
 * BPTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\BP;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test bp files.
 *
 * @group bp
 */
class BPTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'buddypress/bp-loader.php';

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		global $bp;

		unset( $bp->signup );

		parent::tearDown();
	}

	/**
	 * Test hcap_bp_group_form().
	 */
	public function test_hcap_bp_group_form() {
		$nonce    = wp_nonce_field( 'hcaptcha_bp_create_group', 'hcaptcha_bp_create_group_nonce', true, false );
		$expected =
			'<div class="hcap_buddypress_group_form">' .
			$this->get_hcap_form() . $nonce .
			'</div>';

		ob_start();

		hcap_bp_group_form();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcaptcha_bp_group_verify().
	 */
	public function test_hcaptcha_bp_group_verify() {
		FunctionMocker::replace(
			'bp_is_group_creation_step',
			function ( $step_slug ) {
				return 'group-details' === $step_slug;
			}
		);

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_bp_create_group_nonce', 'hcaptcha_bp_create_group' );

		self::assertTrue( hcap_hcaptcha_bp_group_verify( null ) );
	}

	/**
	 * Test hcaptcha_bp_group_verify() when not in step.
	 */
	public function test_hcaptcha_bp_group_verify_not_in_step() {
		FunctionMocker::replace( 'bp_is_group_creation_step', false );

		self::assertFalse( hcap_hcaptcha_bp_group_verify( null ) );
	}

	/**
	 * Test hcaptcha_bp_group_verify() when not verified.
	 */
	public function test_hcaptcha_bp_group_verify_not_verified() {
		FunctionMocker::replace(
			'bp_is_group_creation_step',
			function ( $step_slug ) {
				return 'group-details' === $step_slug;
			}
		);

		FunctionMocker::replace(
			'defined',
			function ( $constant_name ) {
				return 'BP_TESTS_DIR' === $constant_name;
			}
		);

		add_filter(
			'wp_redirect',
			function ( $location, $status ) {
				return '';
			},
			10,
			2
		);

		FunctionMocker::replace( 'bp_get_groups_root_slug', '' );

		self::assertFalse( hcap_hcaptcha_bp_group_verify( null ) );

		$bp = buddypress();
		self::assertSame( 'Please complete the hCaptcha.', $bp->template_message );
		self::assertSame( 'error', $bp->template_message_type );
	}

	/**
	 * Test hcap_display_bp_register().
	 */
	public function test_hcap_display_bp_register() {
		$nonce    = wp_nonce_field( 'hcaptcha_bp_register', 'hcaptcha_bp_register_nonce', true, false );
		$expected = $this->get_hcap_form() . $nonce;

		ob_start();

		hcap_display_bp_register();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_display_bp_register() with error.
	 */
	public function test_hcap_display_bp_register_error() {
		global $bp;

		$hcaptcha_response_verify = 'some response';

		$bp->signup = (object) [
			'errors' => [
				'hcaptcha_response_verify' => $hcaptcha_response_verify,
			],
		];

		$nonce    = wp_nonce_field( 'hcaptcha_bp_register', 'hcaptcha_bp_register_nonce', true, false );
		$expected =
			'<div class="error">' .
			$hcaptcha_response_verify .
			'</div>' .
			$this->get_hcap_form() . $nonce;

		ob_start();

		hcap_display_bp_register();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_bp_register_captcha().
	 */
	public function test_hcap_verify_bp_register_captcha() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_bp_register_nonce', 'hcaptcha_bp_register' );

		self::assertTrue( hcap_verify_bp_register_captcha() );
	}

	/**
	 * Test hcap_verify_bp_register_captcha() not verified.
	 */
	public function test_hcap_verify_bp_register_captcha_not_verified() {
		global $bp;

		$bp->signup = (object) [
			'errors' => [],
		];

		$expected = (object) [
			'errors' => [
				'hcaptcha_response_verify' => 'Please complete the hCaptcha.',
			],
		];

		self::assertFalse( hcap_verify_bp_register_captcha() );

		self::assertEquals( $expected, $bp->signup );
	}
}
